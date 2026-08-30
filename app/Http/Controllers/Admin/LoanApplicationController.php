<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerGuarantor;
use App\Models\Department;
use App\Models\Lender;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\AffordabilityService;
use App\Services\ApplicationBorrowerStatusService;
use App\Services\ApplicationDisbursementReadinessService;
use App\Services\ApplicationDocumentRequestService;
use App\Services\ApplicationDocumentReviewService;
use App\Services\ApplicationOfferService;
use App\Services\AssetReservationService;
use App\Services\CapacityAutoRejectService;
use App\Services\CollateralSecureService;
use App\Services\CreditDeskAssignmentService;
use App\Services\GpsPartnerService;
use App\Services\GroupLoanMemberReviewService;
use App\Services\GroupLoanReviewService;
use App\Services\GuarantorReplacementService;
use App\Services\GuarantorSupplementService;
use App\Services\LoanAgreementService;
use App\Services\LoanApplicationReviewService;
use App\Services\LoanApplicationWorkflowService;
use App\Services\LoanOriginationService;
use App\Services\LoanRejectionReasonService;
use App\Services\ReferenceNumberService;
use App\Services\ScreeningChecklistGateService;
use App\Services\ScreeningChecklistService;
use App\Services\ScreeningPartnerAvailabilityService;
use App\Services\ScreeningReadinessService;
use App\Services\ScreeningSequenceService;
use App\Services\SmartLoanApplicationWizardService;
use App\Services\UnderwritingAnomalyService;
use App\Services\ValuationPartnerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanApplicationController extends ResourceController
{
    protected string $model = LoanApplication::class;

    protected string $routePrefix = 'admin.loan-applications';

    protected string $viewFolder = 'loan-applications';

    protected string $singular = 'application';

    protected function rules(?Model $model = null): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'loan_product_id' => ['required', 'exists:loan_products,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'application_number' => ['nullable', 'string', 'max:50'],
            'requested_amount' => ['required', 'numeric', 'min:0'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:120'],
            'recommended_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,submitted,under_review,pre_approved,approved,rejected,withdrawn,disbursed'],
            'current_stage' => ['nullable', 'string', 'max:80'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        $customers = Customer::orderBy('first_name')->limit(500)->get()
            ->mapWithKeys(fn ($c) => [$c->id => trim($c->first_name.' '.$c->last_name)]);

        $products = LoanProduct::orderBy('name')->pluck('name', 'id');
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        if ($record instanceof LoanApplication) {
            if ($record->customer_id && ! $customers->has($record->customer_id)) {
                $customer = Customer::find($record->customer_id);
                if ($customer) {
                    $customers->put($customer->id, trim($customer->first_name.' '.$customer->last_name));
                }
            }
            if ($record->loan_product_id && ! $products->has($record->loan_product_id)) {
                $product = LoanProduct::find($record->loan_product_id);
                if ($product) {
                    $products->put($product->id, $product->name);
                }
            }
            if ($record->branch_id && ! $branches->has($record->branch_id)) {
                $branch = Branch::find($record->branch_id);
                if ($branch) {
                    $branches->put($branch->id, $branch->name);
                }
            }
        }

        return [
            'customers' => $customers,
            'products' => $products,
            'branches' => $branches,
            'statuses' => [
                'draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under review',
                'pre_approved' => 'Pre-approved', 'approved' => 'Approved', 'rejected' => 'Rejected',
                'withdrawn' => 'Withdrawn', 'disbursed' => 'Disbursed',
            ],
        ];
    }

    public function edit($id)
    {
        abort(403, 'Applications are not edited in the console. Request updates from the borrower instead.');
    }

    public function update(Request $request, $id)
    {
        abort(403, 'Applications are not edited in the console. Request updates from the borrower instead.');
    }

    public function create()
    {
        $wizard = app(SmartLoanApplicationWizardService::class);
        $formData = $this->formData();

        $products = LoanProduct::where('is_active', true)->orderBy('name')->get()->map(fn (LoanProduct $p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'rate' => (float) $p->interest_rate,
            'min' => (float) $p->min_amount,
            'max' => (float) $p->max_amount,
            'tmin' => (int) $p->tenure_min_months,
            'tmax' => (int) $p->tenure_max_months,
            'desc' => $p->description,
            'requires_guarantor' => (bool) $p->requires_guarantor,
        ])->values();

        return view("admin.{$this->viewFolder}.create", [
            ...$formData,
            'products' => $products,
            'wizardSteps' => $wizard->adminStepLabels(),
            'loanPurposes' => config('loan_purposes', []),
            'wizardDataUrl' => route('admin.loan-applications.wizard-data', ['customer' => '__ID__']),
        ]);
    }

    public function wizardCustomerData(Customer $customer): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $wizard = app(SmartLoanApplicationWizardService::class);

        return response()->json([
            'eligibility' => $wizard->eligibilityForCustomer($customer),
            'profile' => $wizard->profileSections($customer),
        ]);
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['application_number']) && ! empty($data['loan_product_id'])) {
            $product = LoanProduct::find($data['loan_product_id']);
            if ($product) {
                $data['application_number'] = app(ReferenceNumberService::class)->applicationReference($product);
            }
        }

        if (empty($data['current_stage']) && ! empty($data['status'])) {
            $data['current_stage'] = $data['status'] === 'submitted' ? 'submitted' : ($data['current_stage'] ?? 'submitted');
        }

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->transform($request->validate($this->rules()));
        $record = $this->model::create($data);

        if (($data['status'] ?? '') === 'submitted') {
            $record->update(['submitted_at' => now(), 'current_stage' => $record->current_stage ?: 'submitted']);
        }

        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function show($id): View
    {
        $record = LoanApplication::query()
            ->with(['customer', 'product', 'loan', 'loanGroup.members.customer', 'loanGroup.leader', 'stageHistory.changedByUser', 'alternativeProduct', 'recommendedByUser', 'assignedAnalyst', 'collateralAsset', 'collateralAssets.customerAsset', 'assetReservation.asset.vendor', 'manualPostApprovalFees', 'valuationAssignments.vendor'])
            ->findOrFail($id);

        abort_unless(
            app(CreditDeskAssignmentService::class)->canViewApplication(auth()->user(), $record),
            403,
            'This file is not on the Management desk. Management only authorizes committee-approved loans that require Management, then runs post-approval ops.'
        );

        $workflow = app(LoanApplicationWorkflowService::class);
        $review = app(LoanApplicationReviewService::class)->dossier($record);
        $availableActions = $workflow->availableActions($record, auth()->user());
        $stageHistory = $record->stageHistory()->latest()->get();
        $auditLogs = AuditLog::query()
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->id)
            ->latest()
            ->limit(20)
            ->with('user')
            ->get();

        $letters = app(LoanAgreementService::class)->creditFileLetters($record);
        $offer = $letters['offer'];
        $contract = $letters['contract'];
        $finalContract = $letters['final'];
        $signedContract = $letters['signed'];
        $rejectionLetter = LoanAgreement::where('loan_application_id', $record->id)
            ->where('document_type', 'rejection_letter')
            ->latest('id')
            ->first();

        $documentRequests = LoanApplicationDocumentRequest::with(['uploads', 'requester', 'subjectCustomer', 'groupMember.customer'])
            ->where('loan_application_id', $record->id)
            ->latest()
            ->get();

        $affordability = app(AffordabilityService::class)->evaluate(
            $record->loadMissing(['customer', 'product'])
        );
        $rejectionReasons = app(LoanRejectionReasonService::class)->grouped();
        $rejectionAdviceOptions = app(LoanRejectionReasonService::class)->adviceOptions();
        $underwritingAnomalies = app(UnderwritingAnomalyService::class)->forApplication($record, $review);
        $groupedDocumentRequests = app(ApplicationBorrowerStatusService::class)
            ->groupedDocumentRequests($documentRequests);
        $counterOffer = app(ApplicationOfferService::class)->maxCounterOffer($record);
        $assetAlternativeProduct = LoanProduct::where('code', 'AB')->where('is_active', true)->first();
        $disbursementReadiness = app(ApplicationDisbursementReadinessService::class);

        $valuers = app(ValuationPartnerService::class)->valuersForApplication($record);
        $allValuers = app(ValuationPartnerService::class)->allActiveValuers();
        $suggestedValuer = app(ValuationPartnerService::class)->suggestValuer($record);
        $valuationReport = app(ValuationPartnerService::class)->reportForApplication($record);

        $externalLenders = Lender::query()
            ->where('status', 'active')
            ->when(
                Schema::hasColumn('lenders', 'funding_source'),
                fn ($q) => $q->where(fn ($inner) => $inner->where('funding_source', 'external')->orWhereNull('funding_source'))
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $gpsInstallers = app(GpsPartnerService::class)->installersForApplication($record);
        $suggestedGpsInstaller = app(GpsPartnerService::class)->suggestInstaller($record);
        $partnerAvailability = app(ScreeningPartnerAvailabilityService::class)->forApplication($record);
        try {
            $groupReview = app(GroupLoanReviewService::class)->dossier($record) ?? [];
        } catch (\Throwable $e) {
            report($e);
            $groupReview = [];
        }
        if (! is_array($groupReview)) {
            $groupReview = [];
        }

        $underwritingDeptId = Department::query()->where('code', 'UND')->value('id');
        $assignableAnalysts = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($underwritingDeptId) {
                $q->whereIn('role', ['credit_analyst', 'officer', 'manager']);
                if ($underwritingDeptId) {
                    $q->orWhere('department_id', $underwritingDeptId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view("admin.{$this->viewFolder}.show", compact(
            'record',
            'review',
            'availableActions',
            'stageHistory',
            'auditLogs',
            'workflow',
            'offer',
            'rejectionLetter',
            'contract',
            'finalContract',
            'signedContract',
            'documentRequests',
            'affordability',
            'rejectionReasons',
            'rejectionAdviceOptions',
            'underwritingAnomalies',
            'groupedDocumentRequests',
            'counterOffer',
            'assetAlternativeProduct',
            'disbursementReadiness',
            'valuers',
            'suggestedValuer',
            'valuationReport',
            'externalLenders',
            'gpsInstallers',
            'suggestedGpsInstaller',
            'partnerAvailability',
            'groupReview',
            'assignableAnalysts',
        ));
    }

    public function fireCapacityAutoReject(LoanApplication $loan_application): RedirectResponse
    {
        abort_unless($this->canManageCapacityAutoReject(), 403);
        $this->assertApplicationMutable($loan_application);

        app(CapacityAutoRejectService::class)->fireNow($loan_application, auth()->user());

        return back()->with('status', 'Capacity rejection feedback sent to the borrower.');
    }

    public function cancelCapacityAutoReject(LoanApplication $loan_application): RedirectResponse
    {
        abort_unless($this->canManageCapacityAutoReject(), 403);
        $this->assertApplicationMutable($loan_application);

        app(CapacityAutoRejectService::class)->cancel($loan_application, auth()->user(), 'Kept in screening by management');

        return back()->with('status', 'Auto-reject cancelled — application stays in screening.');
    }

    private function canManageCapacityAutoReject(): bool
    {
        return app(CapacityAutoRejectService::class)->canAct(auth()->user());
    }

    private function assertApplicationMutable(LoanApplication $application): void
    {
        abort_if($application->isClosed(), 403, 'This application is closed and can only be viewed.');
    }

    public function assignAnalyst(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        $this->assertApplicationMutable($loan_application);
        $data = $request->validate([
            'assigned_analyst_id' => ['nullable', 'exists:users,id'],
        ]);

        $analystId = $data['assigned_analyst_id'] ?? null;
        if ($analystId) {
            $analyst = User::query()->findOrFail($analystId);
            abort_unless(
                in_array($analyst->role, ['credit_analyst', 'officer', 'manager', 'admin', 'super_admin'], true),
                422
            );
        }

        $loan_application->forceFill([
            'assigned_analyst_id' => $analystId,
            'assigned_at' => $analystId ? now() : null,
        ])->save();

        return redirect()
            ->route("{$this->routePrefix}.show", $loan_application)
            ->with('status', $analystId
                ? 'Application assigned to analyst.'
                : 'Analyst assignment cleared.');
    }

    public function requestGuarantorSupplement(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);
        $this->assertApplicationMutable($loan_application);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(GuarantorSupplementService::class)->request(
                $loan_application,
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('borrower.guarantor_supplement.admin_success'));
    }

    public function requestCollateralSecure(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.request_documents')
            || auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(CollateralSecureService::class)->request(
                $loan_application,
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Collateral request sent to the borrower loan profile.');
    }

    public function requestValuation(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.request_documents')
            || auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(CollateralSecureService::class)->requestValuation(
                $loan_application,
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Valuation requested. The borrower (group leader on group loans) must pay the valuation fee before a valuer is assigned.');
    }

    public function requestAdditionalCollateral(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.request_documents')
            || auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $data = $request->validate([
            'instructions' => ['nullable', 'string', 'max:2000'],
            'review_person' => ['nullable', 'in:borrower,member,guarantor'],
            'review_g' => ['nullable', 'integer'],
            'review_m' => ['nullable', 'integer'],
            'subject_customer_id' => ['nullable', 'integer'],
            'loan_group_member_id' => ['nullable', 'integer'],
        ]);

        $person = match ($data['review_person'] ?? 'borrower') {
            'guarantor' => 'guarantor',
            'member' => 'member',
            default => 'borrower',
        };

        $note = trim((string) ($data['instructions'] ?? ''));
        if ($note === '') {
            $note = 'The pledged asset does not cover the requested amount. Add another asset in your profile, then choose it for this loan. Screening cannot attach it for you.';
        }

        app(ApplicationDocumentRequestService::class)->create(
            $loan_application,
            $request->user(),
            'Add collateral asset',
            $note,
            subjectKind: $person,
            subjectCustomerId: isset($data['subject_customer_id']) ? (int) $data['subject_customer_id'] : null,
            loanGroupMemberId: isset($data['loan_group_member_id']) ? (int) $data['loan_group_member_id'] : null,
        );

        $who = match ($person) {
            'guarantor' => 'guarantor',
            'member' => 'group member',
            default => filled($loan_application->loan_group_id) ? 'group leader' : 'borrower',
        };

        return back()->with('status', 'Asked the '.$who.' to add another asset. They must pick it themselves — screening cannot attach it.');
    }

    public function saveScreeningChecklist(Request $request, LoanApplication $loan_application): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $person = match ($request->input('person', 'borrower')) {
            'guarantor' => 'guarantor',
            'member' => 'member',
            default => 'borrower',
        };
        $guarantorLinkId = $person === 'guarantor' ? (int) $request->input('g') : null;
        $memberId = $person === 'member' ? (int) $request->input('m') : null;
        $json = $this->wantsChecklistJson($request);
        if ($person === 'guarantor' && $guarantorLinkId < 1) {
            $message = 'Select a guarantor before saving their review checklist.';

            return $json
                ? response()->json(['ok' => false, 'error' => $message], 422)
                : back()->with('error', $message);
        }
        if ($person === 'member' && $memberId < 1) {
            $message = 'Select a group member before saving their review checklist.';

            return $json
                ? response()->json(['ok' => false, 'error' => $message], 422)
                : back()->with('error', $message);
        }

        try {
            app(ScreeningChecklistService::class)->save(
                $loan_application,
                $request->user(),
                $request->input('items', []),
                $person,
                $guarantorLinkId ?: null,
                $memberId ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            return $json
                ? response()->json(['ok' => false, 'error' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage())->withInput();
        }

        $loan_application = $loan_application->fresh();
        $suggestion = app(ScreeningChecklistService::class)->suggestedRejection($loan_application);
        $gate = (string) $request->input('gate', '');
        $openGroup = (string) $request->input('open_group', '');
        $openItem = (string) $request->input('open_item', '');
        $returnParams = array_filter([
            'loan_application' => $loan_application,
            'workspace' => 'checklist',
            'review_person' => $person,
            'review_g' => $guarantorLinkId ?: null,
            'review_m' => $memberId ?: null,
            'desk_phase' => $request->input('desk_phase'),
            'capacity_tab' => $request->input('capacity_tab'),
            'security_tab' => $request->input('security_tab'),
            'gate' => $gate !== '' ? $gate : null,
            'open_group' => $openGroup !== '' ? $openGroup : null,
            'open_item' => $openItem !== '' ? $openItem : null,
        ]);

        if ($json) {
            return response()->json($this->screeningChecklistSavePayload(
                $loan_application,
                $person,
                $guarantorLinkId ?: null,
                $memberId ?: null,
                $suggestion,
            ));
        }

        $redirect = redirect()
            ->route('admin.loan-applications.show', $returnParams)
            ->withFragment($openItem !== '' ? 'item-'.$openItem : 'review-desk');

        if ($suggestion['prompt_reject']) {
            $redirect->with('checklist_reject_codes', $suggestion['codes'])
                ->with('checklist_reject_notes', $suggestion['summary']);
        }

        return $redirect;
    }

    private function wantsChecklistJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * @param  array{prompt_reject: bool, codes: list<string>, summary: string}  $suggestion
     * @return array<string, mixed>
     */
    private function screeningChecklistSavePayload(
        LoanApplication $application,
        string $person,
        ?int $guarantorLinkId,
        ?int $memberId,
        array $suggestion,
    ): array {
        $review = app(LoanApplicationReviewService::class)->dossier($application);
        try {
            $groupReview = app(GroupLoanReviewService::class)->dossier($application) ?? [];
        } catch (\Throwable $e) {
            report($e);
            $groupReview = [];
        }
        if (! is_array($groupReview)) {
            $groupReview = [];
        }
        $anomalies = app(UnderwritingAnomalyService::class)->forApplication($application, $review);
        $readiness = app(ScreeningReadinessService::class)->forApplication(
            $application,
            $review,
            $groupReview,
            is_array($anomalies) ? $anomalies : $anomalies->all(),
            auth()->user(),
        );
        $desk = app(ScreeningChecklistService::class)->deskViewModel(
            $application,
            $review,
            $groupReview,
            auth()->user(),
            $person,
            $guarantorLinkId,
            $memberId,
        );
        $gates = app(ScreeningChecklistGateService::class)->regroup($desk['groups'] ?? [], $application);
        $sequence = app(ScreeningSequenceService::class)->snapshot($application, $gates);
        $firstOpenByGate = [];
        foreach ($gates as $key => $gate) {
            $open = collect($gate['groups'] ?? [])->first(fn ($g) => ! ($g['complete'] ?? false))
                ?? collect($gate['groups'] ?? [])->first();
            $firstOpenByGate[$key] = $open['key'] ?? null;
        }
        $gateUi = [];
        foreach ($gates as $key => $gate) {
            $gateUi[$key] = [
                'chip' => $gate['chip'] ?? $gate['label'] ?? $key,
                'status_label' => $gate['status_label'] ?? '',
                'decided' => (int) ($gate['decided'] ?? 0),
                'total' => (int) ($gate['total'] ?? 0),
                'failed' => (int) ($gate['failed'] ?? 0),
                'locked' => (bool) ($gate['locked'] ?? false),
            ];
        }

        return [
            'ok' => true,
            'saved' => true,
            'reload' => false,
            'prompt_reject' => (bool) ($suggestion['prompt_reject'] ?? false),
            'readiness_html' => view('admin.loan-applications.review._screening_readiness', [
                'record' => $application,
                'review' => $review,
                'groupReview' => $groupReview,
                'screeningReadiness' => $readiness,
            ])->render(),
            'sequence_html' => view('admin.loan-applications.review._early_eligibility', [
                'record' => $application,
                'sequence' => $sequence,
                'gates' => $gates,
            ])->render(),
            'next_action' => $sequence['next_action'] ?? null,
            'first_open_by_gate' => $firstOpenByGate,
            'gates' => $gateUi,
            'desk' => [
                'decided' => collect($gates)->sum('decided'),
                'total' => collect($gates)->sum('total'),
                'failed' => (int) ($desk['failed'] ?? 0),
            ],
            'decision_status' => $readiness['decision_status'] ?? null,
        ];
    }

    public function waiveDiscrepancy(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $waivable = [
            'spouse_missing_on_crb',
            'spouse_mismatch',
            'marital_mismatch',
            'children_mismatch',
            'employment_soft_mismatch',
        ];
        $data = $request->validate([
            'code' => ['required', 'string', 'in:'.implode(',', $waivable)],
            'reason' => ['required', 'string', 'min:12', 'max:500'],
            'detail' => ['nullable', 'string', 'max:500'],
        ]);

        $payload = is_array($loan_application->screening_payload) ? $loan_application->screening_payload : [];
        $waivers = is_array($payload['discrepancy_waivers'] ?? null) ? $payload['discrepancy_waivers'] : [];
        $waivers[$data['code']] = [
            'by' => $request->user()->id,
            'by_name' => $request->user()->name,
            'at' => now()->toIso8601String(),
            'reason' => $data['reason'],
            'detail' => $data['detail'] ?? null,
        ];
        $payload['discrepancy_waivers'] = $waivers;
        $loan_application->forceFill(['screening_payload' => $payload])->save();

        $this->auditAdmin('admin.loan_applications.discrepancy_waived', $loan_application, [
            'code' => $data['code'],
            'reason' => $data['reason'],
        ]);

        return back()->with('status', 'Discrepancy accepted. The CRB report is unchanged.');
    }

    public function requestGuarantorChange(
        Request $request,
        LoanApplication $loan_application,
        CustomerGuarantor $customerGuarantor,
    ): RedirectResponse {
        abort_unless(auth()->user()?->hasPermission('applications.review')
            || auth()->user()?->hasPermission('applications.view'), 403);
        $this->assertApplicationMutable($loan_application);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(GuarantorSupplementService::class)->requestChange(
                $loan_application,
                $customerGuarantor,
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()
            ->with('status', __('borrower.guarantor_supplement.change_admin_success'))
            ->withFragment('borrower-file');
    }

    public function runWorkflow(Request $request, LoanApplication $loan_application, LoanApplicationWorkflowService $workflow): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);
        $this->assertApplicationMutable($loan_application);
        abort_unless(
            app(CreditDeskAssignmentService::class)->canViewApplication(auth()->user(), $loan_application),
            403,
            'This file is not on the Management desk.'
        );

        $data = $request->validate([
            'action' => ['required', 'string', 'in:'.implode(',', array_keys(LoanApplicationWorkflowService::ACTIONS))],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'rejection_reason_code' => ['nullable', 'string', 'max:80'],
            'rejection_reason_codes' => ['nullable', 'array'],
            'rejection_reason_codes.*' => ['string', 'max:80'],
            'rejection_internal_notes' => ['nullable', 'string', 'max:2000'],
            'rejection_advice_code' => ['nullable', 'string', 'max:80'],
            'rejection_advice' => ['nullable', 'string', 'max:2000'],
            'screening_rejection_reason_code' => ['nullable', 'string', 'max:80'],
            'recommendation_type' => ['nullable', 'in:approve,counter,asset_alternative'],
            'recommendation_rationale' => ['nullable', 'string', 'max:80'],
            'recommendation_notes' => ['nullable', 'string', 'max:1000'],
            'committee_rationale' => ['nullable', 'string', 'max:80'],
            'approval_reason_code' => ['nullable', 'string', 'max:80'],
            'approval_reason_notes' => ['nullable', 'string', 'max:1000'],
            'recommended_amount' => ['nullable', 'numeric', 'min:0'],
            'offered_amount' => ['nullable', 'numeric', 'min:0'],
            'offered_tenure_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'alternative_product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
            'funding_source' => ['nullable', 'in:internal,external'],
            'preferred_lender_id' => ['nullable', 'integer', 'exists:lenders,id'],
            'document_presets' => ['nullable', 'array'],
            'document_presets.*' => ['string', 'max:120'],
            'refer_back_to' => ['nullable', 'in:committee,screening'],
        ]);

        if (in_array($data['action'], ['approve', 'approve_with_conditions'], true) && application_needs_funding_choice($loan_application->product)) {
            if (empty($data['funding_source'])) {
                return back()->withErrors(['funding_source' => 'Select internal or external funding source.'])->withInput();
            }
            if ($data['funding_source'] === 'external' && empty($data['preferred_lender_id']) && (Setting::get('finance.capital_allocation_strategy') ?? 'proportional') === 'manual') {
                return back()->withErrors(['preferred_lender_id' => 'Select a capital partner when allocation strategy is manual.'])->withInput();
            }
        }

        if (in_array($data['action'], ['approve', 'approve_with_conditions'], true) && empty(trim((string) ($data['approval_reason_code'] ?? '')))) {
            return back()->withErrors(['approval_reason_code' => 'Select a reason for approval.'])->withInput();
        }

        if (in_array($data['action'], ['approve', 'approve_with_conditions'], true)
            && ($data['approval_reason_code'] ?? null) === 'custom'
            && empty(trim((string) ($data['approval_reason_notes'] ?? '')))) {
            return back()->withErrors(['approval_reason_notes' => 'Enter the custom approval reason.'])->withInput();
        }

        $rejectionCodes = collect($data['rejection_reason_codes'] ?? [])
            ->push($data['rejection_reason_code'] ?? null)
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($data['action'] === 'reject' && $rejectionCodes === []) {
            return back()->withErrors(['rejection_reason_codes' => 'Select at least one rejection reason.'])->withInput();
        }

        if ($data['action'] === 'reject'
            && ($data['rejection_advice_code'] ?? null) === 'custom'
            && empty(trim($data['rejection_advice'] ?? ''))) {
            return back()->withErrors(['rejection_advice' => 'Write the custom advice for the borrower.'])->withInput();
        }

        if ($data['action'] === 'return_for_documents' && empty(trim($data['remarks'] ?? ''))) {
            return back()->withErrors(['remarks' => 'Explain which documents the borrower must provide or update.'])->withInput();
        }

        if ($data['action'] === 'submit_recommendation' && empty($data['recommendation_type'])) {
            return back()->withErrors(['recommendation_type' => 'Select a decision: approve, counter-offer, or use Reject.'])->withInput();
        }

        if ($data['action'] === 'submit_recommendation' && empty(trim((string) ($data['remarks'] ?? '')))) {
            return back()->withErrors(['remarks' => 'Answer why you are making this decision.'])->withInput();
        }

        if ($data['action'] === 'issue_offer' && empty($data['offered_amount'])) {
            return back()->withErrors(['offered_amount' => 'Enter the offer amount.'])->withInput();
        }

        $offerService = app(ApplicationOfferService::class);
        $stage = $loan_application->current_stage ?? 'submitted';
        $isCommitteeStage = $stage === 'pre_approval';
        $screeningType = $loan_application->recommendation_type;
        $divergingCommitteeActions = ['approve', 'approve_with_conditions', 'reject', 'issue_offer', 'refer_back'];

        try {
            if ($data['action'] === 'validate_screening') {
                $result = $offerService->validateScreeningDecision($loan_application, auth()->user());
                $loan_application->refresh();

                if ($result['action'] === 'issue_offer') {
                    return redirect()
                        ->route("{$this->routePrefix}.show", $loan_application)
                        ->with('status', $result['message']);
                }

                // Final-approve path after validating screening approval
                if ($result['action'] === 'approve') {
                    if (application_needs_funding_choice($loan_application->product) && empty($data['funding_source'])) {
                        return back()
                            ->withErrors(['funding_source' => 'Select funding source to validate this approval.'])
                            ->withInput();
                    }
                    if (application_needs_funding_choice($loan_application->product)) {
                        $loan_application->update([
                            'funding_source' => $data['funding_source'],
                            'preferred_lender_id' => $data['funding_source'] === 'external'
                                ? ($data['preferred_lender_id'] ?? null)
                                : null,
                        ]);
                    }
                    $workflow->transition(
                        $loan_application->fresh(),
                        auth()->user(),
                        'approve',
                        'Committee validated the screening approval.',
                        false,
                        null,
                        null,
                        null,
                        null,
                        null,
                        'aligns_with_screening',
                        'Validated the screening approval.',
                    );

                    $loan_application = $loan_application->fresh();
                    $validatedMessage = $loan_application->current_stage === 'awaiting_management'
                        ? 'Screening decision validated — waiting for management approval.'
                        : 'Screening decision validated — application approved.';

                    return redirect()
                        ->route("{$this->routePrefix}.show", $loan_application)
                        ->with('status', $validatedMessage);
                }
            }

            if ($data['action'] === 'submit_recommendation') {
                DB::transaction(function () use ($workflow, $offerService, $loan_application, $data) {
                    $workflow->assertScreeningDocumentsReady($loan_application);
                    $offerService->submitRecommendation(
                        $loan_application,
                        auth()->user(),
                        (string) $data['recommendation_type'],
                        isset($data['recommended_amount']) ? (float) $data['recommended_amount'] : null,
                        isset($data['offered_tenure_months']) ? (int) $data['offered_tenure_months'] : null,
                        $data['remarks'] ?? null,
                        null,
                        $data['recommendation_rationale'] ?? null,
                        $data['screening_rejection_reason_code'] ?? null,
                        $data['recommendation_notes'] ?? null,
                    );
                    $workflow->transition(
                        $loan_application->fresh(),
                        auth()->user(),
                        'submit_recommendation',
                        $data['remarks'] ?? null,
                    );
                });

                return redirect()
                    ->route("{$this->routePrefix}.show", $loan_application)
                    ->with('status', (LoanApplicationWorkflowService::ACTIONS['submit_recommendation']['label'] ?? 'Action').' completed successfully.');
            }

            if ($data['action'] === 'suggest_asset_alternative') {
                $offerService->submitRecommendation(
                    $loan_application,
                    auth()->user(),
                    ApplicationOfferService::RECOMMEND_ASSET,
                    null,
                    null,
                    $data['remarks'] ?? 'Asset-backed alternative suggested after screening review.',
                    isset($data['alternative_product_id']) ? (int) $data['alternative_product_id'] : null,
                    $data['recommendation_rationale'] ?? 'differs_risk',
                    $data['screening_rejection_reason_code'] ?? null,
                );

                return redirect()
                    ->route("{$this->routePrefix}.show", $loan_application)
                    ->with('status', 'Asset-backed alternative suggested to borrower.');
            }

            if ($data['action'] === 'issue_offer') {
                if ($isCommitteeStage && filled($screeningType) && $screeningType !== ApplicationOfferService::RECOMMEND_COUNTER) {
                    $offerService->recordCommitteeDivergence(
                        $loan_application,
                        auth()->user(),
                        'issue_offer',
                        $data['committee_rationale'] ?? null,
                        $data['remarks'] ?? null,
                    );
                }

                $offerService->issueOffer(
                    $loan_application,
                    auth()->user(),
                    (float) $data['offered_amount'],
                    (int) ($data['offered_tenure_months'] ?? $loan_application->requested_tenure_months),
                    $data['remarks'] ?? null,
                );

                return redirect()
                    ->route("{$this->routePrefix}.show", $loan_application)
                    ->with('status', 'Counter-offer issued to borrower.');
            }

            if (! in_array($data['action'], ['suggest_asset_alternative', 'issue_offer', 'validate_screening'], true)) {
                if ($isCommitteeStage
                    && in_array($data['action'], $divergingCommitteeActions, true)
                    && filled($screeningType)
                ) {
                    $differs = ($data['action'] === 'reject')
                        || (in_array($data['action'], ['approve', 'approve_with_conditions'], true) && $screeningType !== ApplicationOfferService::RECOMMEND_APPROVE)
                        || ($data['action'] === 'issue_offer' && $screeningType !== ApplicationOfferService::RECOMMEND_COUNTER);

                    if ($differs) {
                        $offerService->recordCommitteeDivergence(
                            $loan_application,
                            auth()->user(),
                            $data['action'],
                            $data['committee_rationale'] ?? null,
                            $data['remarks'] ?? ($data['rejection_internal_notes'] ?? null),
                        );
                    }
                }

                if (in_array($data['action'], ['approve', 'approve_with_conditions'], true) && application_needs_funding_choice($loan_application->product)) {
                    $loan_application->update([
                        'funding_source' => $data['funding_source'],
                        'preferred_lender_id' => $data['funding_source'] === 'external'
                            ? ($data['preferred_lender_id'] ?? null)
                            : null,
                    ]);
                }

                $workflow->transition(
                    $loan_application->fresh(),
                    auth()->user(),
                    $data['action'],
                    $data['remarks'] ?? null,
                    false,
                    $rejectionCodes[0] ?? ($data['rejection_reason_code'] ?? null),
                    $data['rejection_internal_notes'] ?? null,
                    $data['rejection_advice_code'] ?? null,
                    $data['rejection_advice'] ?? null,
                    $rejectionCodes !== [] ? $rejectionCodes : null,
                    $data['approval_reason_code'] ?? null,
                    $data['approval_reason_notes'] ?? null,
                    $data['refer_back_to'] ?? null,
                );

                if ($data['action'] === 'return_for_documents' && ! empty($data['document_presets'])) {
                    app(ApplicationDocumentRequestService::class)->createMany(
                        $loan_application->fresh(),
                        auth()->user(),
                        $data['document_presets'],
                        $data['remarks'] ?? null,
                    );
                }
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $label = LoanApplicationWorkflowService::ACTIONS[$data['action']]['label'] ?? 'Action completed';

        return redirect()
            ->route("{$this->routePrefix}.show", $loan_application)
            ->with('status', $label.' completed successfully.');
    }

    public function reviewGroupMember(
        Request $request,
        LoanApplication $loan_application,
        LoanGroupMember $loan_group_member,
        GroupLoanMemberReviewService $review,
    ): RedirectResponse {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);

        $loan_application->loadMissing('loanGroup');
        abort_unless(
            $loan_application->loanGroup
            && (int) $loan_group_member->loan_group_id === (int) $loan_application->loanGroup->id,
            404,
        );

        $data = $request->validate([
            'underwriting_status' => ['required', 'string', 'in:'.implode(',', $review->allowedStatuses())],
            'underwriting_notes' => ['nullable', 'string', 'max:2000'],
            'leader_feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->reviewMember(
            $loan_group_member,
            $data['underwriting_status'],
            $data['underwriting_notes'] ?? null,
            $data['leader_feedback'] ?? null,
            auth()->user(),
        );

        return redirect()
            ->route('admin.loan-applications.show', [
                'loan_application' => $loan_application,
                'person' => 'borrower',
                'tab' => 'group',
                'm' => $loan_group_member->id,
            ])
            ->with('status', 'Group member review saved.')
            ->withFragment('borrower-file');
    }

    public function updateGroupLeaderFeedback(
        Request $request,
        LoanApplication $loan_application,
        GroupLoanMemberReviewService $review,
    ): RedirectResponse {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);

        $group = $loan_application->loanGroup;
        abort_unless($group, 404);

        $data = $request->validate([
            'leader_feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $review->updateGroupFeedback($group, $data['leader_feedback'] ?? null, auth()->user());

        return back()->with('status', 'Group feedback for leader saved.')->withFragment('review-group');
    }

    public function groupContractProgress(
        LoanApplication $loan_application,
        GroupLoanReviewService $review,
    ): JsonResponse {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);

        $dossier = $review->dossier($loan_application);
        abort_unless($dossier, 404);

        return response()->json([
            'ok' => true,
            'contract_signatures' => $dossier['contract_signatures'] ?? null,
        ]);
    }

    public function requestGroupMemberReplacement(
        Request $request,
        LoanApplication $loan_application,
        LoanGroupMember $loan_group_member,
        GroupLoanMemberReviewService $review,
    ): RedirectResponse {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);

        $loan_application->loadMissing('loanGroup');
        abort_unless(
            $loan_application->loanGroup
            && (int) $loan_group_member->loan_group_id === (int) $loan_application->loanGroup->id,
            404,
        );

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->requestReplacement(
            $loan_group_member,
            auth()->user(),
            $data['reason'] ?? null,
        );

        return back()->with('status', 'Replacement requested. The group leader has been notified.')->withFragment('review-group');
    }

    public function continueWithEligibleMembers(
        Request $request,
        LoanApplication $loan_application,
        GroupLoanMemberReviewService $review,
    ): RedirectResponse {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ]);

        try {
            $review->continueWithEligibleMembers($loan_application, auth()->user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withFragment('review-desk');
        }

        return back()->with(
            'status',
            'Failed members marked ineligible. The group continues with the remaining eligible members. Paid fees and historical screening records are kept.',
        )->withFragment('review-desk');
    }

    public function replaceGuarantor(
        Request $request,
        LoanApplication $loan_application,
        CustomerGuarantor $customer_guarantor,
        GuarantorReplacementService $replacement,
    ): RedirectResponse {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'mode' => ['required', 'in:internal,external'],
            'membership_id' => ['required_if:mode,internal', 'nullable', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['required_if:mode,internal', 'nullable', 'string', 'max:120'],
            'first_name' => ['required_if:mode,external', 'nullable', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required_if:mode,external', 'nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'relationship' => ['required_if:mode,external', 'nullable', 'string', 'max:80'],
            'region' => ['required_if:mode,external', 'nullable', 'string', 'max:80'],
            'district' => ['required_if:mode,external', 'nullable', 'string', 'max:80'],
            'channel' => ['nullable', 'in:sms,whatsapp,email'],
        ]);

        try {
            if ($data['mode'] === 'internal') {
                $replacement->replaceInternal(
                    $loan_application,
                    $customer_guarantor,
                    auth()->user(),
                    $data['reason'],
                    (string) $data['membership_id'],
                    $data['phone'],
                    (string) ($data['name'] ?? ''),
                );
            } else {
                $replacement->replaceExternal(
                    $loan_application,
                    $customer_guarantor,
                    auth()->user(),
                    $data['reason'],
                    (string) $data['first_name'],
                    $data['middle_name'] ?? null,
                    (string) $data['last_name'],
                    $data['phone'],
                    $data['email'] ?? null,
                    (string) ($data['relationship'] ?? 'Guarantor'),
                    (string) ($data['region'] ?? ''),
                    (string) ($data['district'] ?? ''),
                    $data['channel'] ?? 'sms',
                );
            }
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withFragment('review-desk');
        }

        return back()->with(
            'status',
            'Guarantor marked replaced. The replacement has a new independent screening state. This application was not restarted.',
        )->withFragment('review-desk');
    }

    public function verifyDocument(Request $request, LoanApplication $loan_application, CustomerDocument $document, ApplicationDocumentReviewService $review): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $subject = $this->resolveDocumentReviewSubject($request, $loan_application, $document);
        $review->verify($document, $loan_application, auth()->user(), $subject);

        return redirect()
            ->route("{$this->routePrefix}.show", $this->documentReviewReturnParams($request, $loan_application, $subject))
            ->with('status', 'Document marked reviewed for this application.')
            ->withFragment('review-documents');
    }

    public function verifyAllDocuments(Request $request, LoanApplication $loan_application, ApplicationDocumentReviewService $review): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $person = (string) $request->input('review_person', 'borrower');
        $subjectKind = 'borrower';
        $subjectCustomerId = (int) $loan_application->customer_id;
        $loanGroupMemberId = null;
        $loan_application->loadMissing('loanGroup.members');

        if ($person === 'member') {
            $loanGroupMemberId = (int) $request->input('review_m', 0);
            if ($loanGroupMemberId < 1) {
                $firstMember = $loan_application->loanGroup?->members
                    ?->first(fn ($row) => ($row->role ?? '') !== 'leader')
                    ?? $loan_application->loanGroup?->members?->first();
                $loanGroupMemberId = (int) ($firstMember?->id ?? 0);
            }
            if ($loanGroupMemberId > 0) {
                $subjectKind = 'member';
                $member = $loan_application->loanGroup?->members?->firstWhere('id', $loanGroupMemberId);
                if ($member?->customer_id) {
                    $subjectCustomerId = (int) $member->customer_id;
                }
            }
        } elseif ($person === 'guarantor' && (int) $request->input('review_g', 0) > 0) {
            $subjectKind = 'guarantor';
            $link = $loan_application->customerGuarantors()->with('guarantor')->find((int) $request->input('review_g'));
            if ($link?->guarantor_id) {
                $subjectCustomerId = (int) $link->guarantor_id;
            }
        }

        $subject = [
            'subject_kind' => $subjectKind,
            'subject_customer_id' => $subjectCustomerId ?: null,
            'loan_group_member_id' => $loanGroupMemberId,
        ];

        $customer = Customer::query()->findOrFail($subjectCustomerId);
        $count = $review->verifyAllPending($loan_application, $customer, auth()->user(), $subject);

        return redirect()
            ->route("{$this->routePrefix}.show", $this->documentReviewReturnParams($request, $loan_application, $subject))
            ->with('status', $count > 0
                ? "Verified {$count} document".($count === 1 ? '' : 's').' for this application.'
                : 'No pending documents to verify.')
            ->withFragment('review-documents');
    }

    public function rejectDocument(Request $request, LoanApplication $loan_application, CustomerDocument $document, ApplicationDocumentReviewService $review): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);
        $this->assertApplicationMutable($loan_application);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'fail_reason_code' => ['required', 'string', 'max:80'],
            'fail_reason_custom' => ['nullable', 'string', 'max:500'],
            'remedy' => ['nullable', 'in:request_again,none'],
            'request_again_label' => ['nullable', 'string', 'max:160'],
            'review_person' => ['nullable', 'string', 'max:20'],
            'review_g' => ['nullable', 'integer'],
            'review_m' => ['nullable', 'integer'],
        ]);

        $subject = $this->resolveDocumentReviewSubject($request, $loan_application, $document);
        $review->reject($document, $loan_application, auth()->user(), array_merge($data, $subject, [
            'remedy' => $data['remedy'] ?? 'request_again',
        ]));

        $status = (($data['remedy'] ?? 'request_again') === 'request_again')
            ? 'Document failed for this application and a replacement was requested.'
            : 'Document failed for this application.';

        return redirect()
            ->route("{$this->routePrefix}.show", $this->documentReviewReturnParams($request, $loan_application, $subject))
            ->with('status', $status)
            ->withFragment('checklist-documents');
    }

    /**
     * Keep the same person on Documents after verify / fail so the next click
     * does not 422 or load the leader file by dropping review_m / review_g.
     *
     * @param  array{subject_kind?: string, subject_customer_id?: ?int, loan_group_member_id?: ?int}  $subject
     * @return array<string, mixed>
     */
    private function documentReviewReturnParams(Request $request, LoanApplication $loan_application, array $subject = []): array
    {
        $person = (string) ($request->input('review_person') ?: ($subject['subject_kind'] ?? 'borrower'));
        if (! in_array($person, ['borrower', 'guarantor', 'member'], true)) {
            $person = 'borrower';
        }

        return array_filter([
            'loan_application' => $loan_application,
            'review_person' => $person,
            'review_g' => $request->input('review_g'),
            'review_m' => $request->input('review_m') ?: ($subject['loan_group_member_id'] ?? null),
            'workspace' => 'checklist',
            'capacity_tab' => 'documents',
        ], fn ($value) => $value !== null && $value !== '' && $value !== 0 && $value !== '0');
    }

    /**
     * @return array{subject_kind: string, subject_customer_id: ?int, loan_group_member_id: ?int}
     */
    private function resolveDocumentReviewSubject(Request $request, LoanApplication $loan_application, CustomerDocument $document): array
    {
        $person = (string) $request->input('review_person', 'borrower');
        $subjectKind = 'borrower';
        $subjectCustomerId = (int) $document->customer_id;
        $loanGroupMemberId = null;
        $loan_application->loadMissing('loanGroup.members');

        if ($person === 'member' || $loan_application->loanGroup?->members?->contains('customer_id', $document->customer_id)) {
            $loanGroupMemberId = (int) $request->input('review_m', 0);
            if ($loanGroupMemberId < 1) {
                $match = $loan_application->loanGroup?->members?->firstWhere('customer_id', $document->customer_id);
                $loanGroupMemberId = (int) ($match?->id ?? 0);
            }
            if ($loanGroupMemberId > 0) {
                $subjectKind = 'member';
                $member = $loan_application->loanGroup?->members?->firstWhere('id', $loanGroupMemberId);
                if ($member?->customer_id) {
                    $subjectCustomerId = (int) $member->customer_id;
                }
            }
        } elseif ($person === 'guarantor' && (int) $request->input('review_g', 0) > 0) {
            $subjectKind = 'guarantor';
            $link = $loan_application->customerGuarantors()->with('guarantor')->find((int) $request->input('review_g'));
            if ($link?->guarantor_id) {
                $subjectCustomerId = (int) $link->guarantor_id;
            }
        }

        return [
            'subject_kind' => $subjectKind,
            'subject_customer_id' => $subjectCustomerId ?: null,
            'loan_group_member_id' => $loanGroupMemberId,
        ];
    }

    public function createLoan(LoanApplication $loan_application, LoanOriginationService $origination): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.disburse') || auth()->user()?->hasPermission('loans.disburse'), 403);

        try {
            $loan = $origination->createFromApplication($loan_application);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan '.$loan->loan_number.' created from application. Review and disburse when ready.');
    }

    public function advanceReservation(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);

        $action = $request->validate([
            'action' => ['required', 'in:gps_installation,insurance_active,registration_complete,release'],
        ])['action'];

        $reservation = app(AssetReservationService::class)->reservationForApplication($loan_application);
        abort_unless($reservation, 404, 'No asset reservation linked to this application.');

        app(AssetReservationService::class)->advance($reservation, $action);

        if ($action === 'release') {
            app(ApplicationDisbursementReadinessService::class)->syncBorrowerProgress($loan_application->fresh());
        }

        return back()->with('status', 'Reservation status updated.');
    }

    public function updateAssetIdentifiers(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.review'), 403);

        $reservation = app(AssetReservationService::class)->reservationForApplication($loan_application);
        abort_unless($reservation?->asset, 404, 'No marketplace asset linked to this application.');

        $data = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:80'],
            'chassis_number' => ['nullable', 'string', 'max:80'],
            'engine_number' => ['nullable', 'string', 'max:80'],
            'insurance_policy_number' => ['nullable', 'string', 'max:80'],
        ]);

        $reservation->asset->update($data);

        return back()->with('status', 'Asset identifiers saved.');
    }
}
