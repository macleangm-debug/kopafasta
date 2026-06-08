<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\CrbCreditCheckService;
use App\Services\LoanApplicationReviewService;
use App\Services\LoanApplicationWorkflowService;
use App\Services\LoanOriginationService;
use App\Services\ReferenceNumberService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'customer_id'              => ['required', 'exists:customers,id'],
            'loan_product_id'          => ['required', 'exists:loan_products,id'],
            'branch_id'                => ['nullable', 'exists:branches,id'],
            'application_number'       => ['nullable', 'string', 'max:50'],
            'requested_amount'         => ['required', 'numeric', 'min:0'],
            'requested_tenure_months'  => ['required', 'integer', 'min:1', 'max:120'],
            'recommended_amount'       => ['nullable', 'numeric', 'min:0'],
            'status'                   => ['required', 'in:draft,submitted,under_review,pre_approved,approved,rejected,withdrawn,disbursed'],
            'current_stage'            => ['nullable', 'string', 'max:80'],
            'purpose'                  => ['nullable', 'string', 'max:500'],
            'rejection_reason'         => ['nullable', 'string', 'max:500'],
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
            'products'  => $products,
            'branches'  => $branches,
            'statuses'  => [
                'draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under review',
                'pre_approved' => 'Pre-approved', 'approved' => 'Approved', 'rejected' => 'Rejected',
                'withdrawn' => 'Withdrawn', 'disbursed' => 'Disbursed',
            ],
        ];
    }

    public function edit($id)
    {
        $record = LoanApplication::findOrFail($id);

        return view("admin.{$this->viewFolder}.edit", ['record' => $record] + $this->formData($record));
    }

    public function create()
    {
        $wizard = app(SmartLoanApplicationWizardService::class);
        $formData = $this->formData();

        $products = LoanProduct::where('is_active', true)->orderBy('name')->get()->map(fn (LoanProduct $p) => [
            'id'                 => $p->id,
            'code'               => $p->code,
            'name'               => $p->name,
            'rate'               => (float) $p->interest_rate,
            'min'                => (float) $p->min_amount,
            'max'                => (float) $p->max_amount,
            'tmin'               => (int) $p->tenure_min_months,
            'tmax'               => (int) $p->tenure_max_months,
            'desc'               => $p->description,
            'requires_guarantor' => (bool) $p->requires_guarantor,
        ])->values();

        return view("admin.{$this->viewFolder}.create", [
            ...$formData,
            'products'      => $products,
            'wizardSteps'   => $wizard->adminStepLabels(),
            'loanPurposes'  => config('loan_purposes', []),
            'wizardDataUrl' => route('admin.loan-applications.wizard-data', ['customer' => '__ID__']),
        ]);
    }

    public function wizardCustomerData(Customer $customer): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $wizard = app(SmartLoanApplicationWizardService::class);

        return response()->json([
            'eligibility' => $wizard->eligibilityForCustomer($customer),
            'profile'     => $wizard->profileSections($customer),
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
            ->with(['customer', 'product', 'loan', 'stageHistory.changedByUser'])
            ->findOrFail($id);

        $workflow = app(LoanApplicationWorkflowService::class);
        $review = app(LoanApplicationReviewService::class)->dossier($record);
        $availableActions = $workflow->availableActions($record, auth()->user());
        $stageHistory = $record->stageHistory()->latest()->get();
        $auditLogs = \App\Models\AuditLog::query()
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->id)
            ->latest()
            ->limit(20)
            ->with('user')
            ->get();

        $offer = \App\Models\LoanAgreement::where('loan_application_id', $record->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        $documentRequests = \App\Models\LoanApplicationDocumentRequest::with(['uploads', 'requester'])
            ->where('loan_application_id', $record->id)
            ->latest()
            ->get();

        $affordability = app(\App\Services\AffordabilityService::class)->evaluate(
            $record->loadMissing(['customer', 'product'])
        );
        $rejectionReasons = app(\App\Services\LoanRejectionReasonService::class)->grouped();
        $groupedDocumentRequests = app(\App\Services\ApplicationBorrowerStatusService::class)
            ->groupedDocumentRequests($documentRequests);

        return view("admin.{$this->viewFolder}.show", compact(
            'record',
            'review',
            'availableActions',
            'stageHistory',
            'auditLogs',
            'workflow',
            'offer',
            'documentRequests',
            'affordability',
            'rejectionReasons',
            'groupedDocumentRequests',
        ));
    }

    public function runWorkflow(Request $request, LoanApplication $loan_application, LoanApplicationWorkflowService $workflow): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $data = $request->validate([
            'action'                   => ['required', 'string', 'in:'.implode(',', array_keys(LoanApplicationWorkflowService::ACTIONS))],
            'remarks'                  => ['nullable', 'string', 'max:1000'],
            'rejection_reason_code'    => ['nullable', 'string', 'max:80'],
            'rejection_internal_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['action'] === 'reject' && empty(trim($data['rejection_reason_code'] ?? ''))) {
            return back()->withErrors(['rejection_reason_code' => 'Select a rejection reason.'])->withInput();
        }

        try {
            $workflow->transition(
                $loan_application,
                auth()->user(),
                $data['action'],
                $data['remarks'] ?? null,
                false,
                $data['rejection_reason_code'] ?? null,
                $data['rejection_internal_notes'] ?? null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $label = LoanApplicationWorkflowService::ACTIONS[$data['action']]['label'] ?? 'Action completed';

        return redirect()
            ->route("{$this->routePrefix}.show", $loan_application)
            ->with('status', $label.' completed successfully.');
    }

    public function refreshCrb(LoanApplication $loan_application, CrbCreditCheckService $crbCredit): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $customer = $loan_application->customer;
        abort_unless($customer, 404);

        $history = $crbCredit->refreshCreditReport($customer);
        $crbCredit->attachToApplication($loan_application, $history, [
            'reused'    => false,
            'refreshed' => true,
            'error'     => $history ? null : 'CRB refresh failed.',
        ]);

        return back()->with(
            'status',
            $history ? 'CRB report refreshed and attached to this application.' : 'CRB refresh could not be completed.',
        );
    }

    public function createLoan(LoanApplication $loan_application, LoanOriginationService $origination): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.disburse') || auth()->user()?->hasPermission('loans.disburse'), 403);

        try {
            $loan = $origination->createFromApplication($loan_application);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan '.$loan->loan_number.' created from application. Review and disburse when ready.');
    }
}
