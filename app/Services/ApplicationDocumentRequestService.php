<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ApplicationDocumentRequestService
{
    /** @var list<string> */
    public const PRESET_LABELS = [
        'Insurance About To Expire',
        'New Insurance Certificate',
        'New Ownership Document',
        'New Asset Photo',
        'Updated National ID',
        'Image Not Clear',
        'Ownership Certificate Missing Page',
        'Signature Not Visible',
        'Updated Bank Statement',
        'Updated Mobile Money Statement',
        'Additional Income Proof',
        'Business Registration Document',
        'Business Photos',
        'Supplier Invoices',
        'Tax Documents',
        'Employment Confirmation Letter',
        'Guarantor residence letter',
        'Updated employment contract',
        'Latest salary slip',
    ];

    /** @var list<string> */
    public const ASSET_BACKED_PRESET_LABELS = [
        'Insurance About To Expire',
        'New Insurance Certificate',
        'New Ownership Document',
        'New Asset Photo',
        'Updated National ID',
        'Image Not Clear',
    ];

    /** Collateral requests for loans that are not already asset-backed / asset-lending. */
    /** @var list<string> */
    public const COLLATERAL_PRESET_LABELS = [
        'Add collateral asset',
        'Updated collateral ownership document',
        'Updated collateral insurance certificate',
        'New collateral photo',
        'Collateral valuation document',
    ];

    /** @return array<string, string> preset => default borrower instructions */
    public static function presetInstructions(): array
    {
        return [
            'Insurance About To Expire'      => 'Your asset insurance is expiring soon. Please upload an updated insurance certificate.',
            'New Insurance Certificate'      => 'Please upload a clear copy of the current insurance certificate for this asset.',
            'New Ownership Document'         => 'Please upload the ownership or logbook document for this asset.',
            'New Asset Photo'                => 'Please upload a clear, recent photo of the asset.',
            'Updated National ID'            => 'Please upload a clear copy of your national ID.',
            'New National ID photo'          => 'Underwriting needs a clearer national ID photo. Please upload again from your profile.',
            'New face verification photo'    => 'Underwriting needs new face verification photos. Please recapture them in your profile.',
            'Identity verification photo'    => 'Please upload a new identity verification photo holding your national ID.',
            'Image Not Clear'                => 'The uploaded image is not clear enough. Please re-upload a sharper photo.',
            'Ownership Certificate Missing Page' => 'The ownership certificate appears incomplete. Please upload all pages.',
            'Signature Not Visible'          => 'Your signature is not clear enough for legal documents. Please update it once in your profile — it will be used on all contracts.',
            'Updated Bank Statement'         => 'Please upload an updated bank statement covering the latest period.',
            'Updated Mobile Money Statement' => 'Please upload an updated mobile money statement.',
            'Additional Income Proof'        => 'Please upload additional proof of income for this application.',
            'Business Registration Document' => 'Please upload your business registration document.',
            'Business Photos'                => 'Please upload clear photos of your business premises or activity.',
            'Supplier Invoices'              => 'Please upload the relevant supplier invoices for this loan.',
            'Tax Documents'                  => 'Please upload the requested tax documents.',
            'Employment Confirmation Letter' => 'Please upload an employment confirmation letter from your employer.',
            'Guarantor residence letter'     => 'Please upload a residence letter for your guarantor.',
            'Updated employment contract'    => 'Please upload your updated employment contract.',
            'Latest salary slip'             => 'Please upload your latest salary slip.',
            'Add collateral asset'           => 'Underwriting needs collateral for this loan. Please add a collateral asset in your profile with ownership and insurance documents.',
            'Updated collateral ownership document' => 'Please upload an updated ownership or logbook document for your collateral asset.',
            'Updated collateral insurance certificate' => 'Please upload a current insurance certificate for your collateral asset.',
            'New collateral photo'           => 'Please upload a clear, recent photo of your collateral asset.',
            'Collateral valuation document'  => 'Please upload a valuation or appraisal document for your collateral.',
        ];
    }

    /**
     * Deep-link borrowers to profile for identity/signature/face requests,
     * otherwise to the application document request anchor.
     */
    public function borrowerActionUrl(LoanApplicationDocumentRequest $request): string
    {
        $application = $request->application;
        $label = mb_strtolower((string) $request->label);

        if (str_contains($label, 'signature')) {
            return route('site.borrower.profile', ['section' => 'personal']).'?focus=signature#profile-signature';
        }
        if (str_contains($label, 'national id') || str_contains($label, 'nida')) {
            return route('site.borrower.profile', ['section' => 'personal']).'?focus=identity#profile-identity';
        }
        if (str_contains($label, 'face') || str_contains($label, 'selfie') || str_contains($label, 'identity verification photo')) {
            return route('site.borrower.profile', ['section' => 'personal']).'?focus=face#profile-face';
        }
        if (
            str_contains($label, 'bank statement')
            || str_contains($label, 'mobile money')
            || str_contains($label, 'salary slip')
            || str_contains($label, 'income proof')
            || str_contains($label, 'employment confirmation')
            || str_contains($label, 'employment contract')
        ) {
            return route('site.borrower.profile', ['section' => 'activity']).'?focus=income#profile-income-statement';
        }
        if (str_contains($label, 'collateral') || str_contains($label, 'add collateral')) {
            return route('site.borrower.profile', ['section' => 'assets', 'add' => 1]);
        }
        if (str_contains($label, 'asset photo') || str_contains($label, 'ownership document') || str_contains($label, 'insurance')) {
            return route('site.borrower.profile', ['section' => 'assets']);
        }

        if ($application) {
            return route('site.borrower.application', $application).'#request-'.$request->id;
        }

        return route('site.borrower.loans', ['tab' => 'applications']);
    }

    public function isProfileGuidedRequest(LoanApplicationDocumentRequest $request): bool
    {
        return in_array($this->borrowerActionKind($request), ['signature', 'face', 'identity', 'collateral', 'income'], true);
    }

    /** Classify UW requests for borrower CTAs (docs, signature, face, identity, clarification). */
    public function borrowerActionKind(LoanApplicationDocumentRequest $request): string
    {
        $label = mb_strtolower((string) $request->label);

        if (str_contains($label, 'signature')) {
            return 'signature';
        }
        if (str_contains($label, 'face') || str_contains($label, 'selfie') || str_contains($label, 'identity verification photo')) {
            return 'face';
        }
        if (str_contains($label, 'national id') || str_contains($label, 'nida')) {
            return 'identity';
        }
        if (
            str_contains($label, 'bank statement')
            || str_contains($label, 'mobile money')
            || str_contains($label, 'salary slip')
            || str_contains($label, 'income proof')
            || str_contains($label, 'employment confirmation')
            || str_contains($label, 'employment contract')
        ) {
            return 'income';
        }
        if (str_contains($label, 'collateral') || str_contains($label, 'add collateral')
            || str_contains($label, 'asset photo') || str_contains($label, 'ownership document')
            || str_contains($label, 'insurance')) {
            return 'collateral';
        }
        if ($request->type === 'clarification') {
            return 'clarification';
        }

        return 'document';
    }

    /**
     * Guided CTA payload for View Application / loan preview cards.
     *
     * @return array{id: int, kind: string, label: string, cta_label: string, url: string, status: string, rejected: bool, instructions: ?string}
     */
    public function borrowerGuidedAction(LoanApplicationDocumentRequest $request): array
    {
        $kind = $this->borrowerActionKind($request);
        $rejected = $request->status === 'rejected';

        $ctaLabel = match ($kind) {
            'signature' => __('borrower.loan_profile.uw_cta.update_signature'),
            'face' => __('borrower.loan_profile.uw_cta.recapture_face'),
            'identity' => __('borrower.loan_profile.uw_cta.update_identity'),
            'collateral' => __('borrower.loan_profile.uw_cta.add_collateral'),
            'income' => __('borrower.loan_profile.uw_cta.update_income'),
            'clarification' => __('borrower.loan_profile.uw_cta.respond'),
            default => $rejected
                ? __('borrower.loan_profile.reupload')
                : __('borrower.loan_profile.upload'),
        };

        return [
            'id'           => (int) $request->id,
            'kind'         => $kind,
            'label'        => (string) $request->label,
            'cta_label'    => $ctaLabel,
            'url'          => $this->borrowerActionUrl($request),
            'status'       => (string) $request->status,
            'rejected'     => $rejected,
            'instructions' => $request->instructions,
        ];
    }

    /**
     * Open underwriting requests as guided borrower CTAs (pending / rejected).
     *
     * @return list<array{id: int, kind: string, label: string, cta_label: string, url: string, status: string, rejected: bool, instructions: ?string}>
     */
    public function openGuidedActionsForApplication(LoanApplication $application): array
    {
        if (in_array((string) $application->status, ['withdrawn', 'rejected', 'disbursed'], true)) {
            return [];
        }

        $application->loadMissing('documentRequests');

        return $application->documentRequests
            ->filter(fn (LoanApplicationDocumentRequest $request) => $request->needsBorrowerAction())
            ->values()
            ->map(fn (LoanApplicationDocumentRequest $request) => $this->borrowerGuidedAction($request))
            ->all();
    }

    public function __construct(private readonly NotificationService $notifier) {}

    public function create(
        LoanApplication $application,
        User $requester,
        string $label,
        ?string $instructions = null,
        ?\DateTimeInterface $dueAt = null,
        string $type = 'document',
        string $subjectKind = 'borrower',
        ?int $subjectCustomerId = null,
        ?int $loanGroupMemberId = null,
    ): LoanApplicationDocumentRequest {
        $instructions ??= self::presetInstructions()[$label] ?? null;
        [$subjectKind, $subjectCustomerId, $loanGroupMemberId] = $this->resolveSubject(
            $application,
            $subjectKind,
            $subjectCustomerId,
            $loanGroupMemberId,
        );

        $request = LoanApplicationDocumentRequest::create([
            'loan_application_id'   => $application->id,
            'subject_kind'          => $subjectKind,
            'subject_customer_id'   => $subjectCustomerId,
            'loan_group_member_id'  => $loanGroupMemberId,
            'requested_by'          => $requester->id,
            'type'                  => $type,
            'label'                 => $label,
            'instructions'          => $instructions,
            'status'                => 'pending',
            'due_at'                => $dueAt,
        ]);

        $this->syncApplicationStatus($application->fresh());
        $this->notifyBorrower($request);
        app(ProfileRevisionService::class)->applyForDocumentRequest($application->fresh(), $request);

        return $request;
    }

    /**
     * @param  list<string>  $labels
     * @return Collection<int, LoanApplicationDocumentRequest>
     */
    public function createMany(
        LoanApplication $application,
        User $requester,
        array $labels,
        ?string $instructions = null,
        ?\DateTimeInterface $dueAt = null,
        string $type = 'document',
        string $subjectKind = 'borrower',
        ?int $subjectCustomerId = null,
        ?int $loanGroupMemberId = null,
    ): Collection {
        $labels = collect($labels)
            ->map(fn ($label) => trim((string) $label))
            ->filter()
            ->unique()
            ->values()
            ->all();

        [$subjectKind, $subjectCustomerId, $loanGroupMemberId] = $this->resolveSubject(
            $application,
            $subjectKind,
            $subjectCustomerId,
            $loanGroupMemberId,
        );

        $created = collect();

        foreach ($labels as $label) {
            $request = LoanApplicationDocumentRequest::create([
                'loan_application_id'  => $application->id,
                'subject_kind'         => $subjectKind,
                'subject_customer_id'  => $subjectCustomerId,
                'loan_group_member_id' => $loanGroupMemberId,
                'requested_by'         => $requester->id,
                'type'                 => $type,
                'label'                => $label,
                'instructions'         => $instructions ?: (self::presetInstructions()[$label] ?? null),
                'status'               => 'pending',
                'due_at'               => $dueAt,
            ]);
            $created->push($request);
            $this->notifyBorrower($request, inApp: false);
        }

        $application = $application->fresh();
        $this->syncApplicationStatus($application);

        if ($created->isNotEmpty()) {
            $this->notifyBorrowerBatch($application->loadMissing('customer'), $created);
            app(ProfileRevisionService::class)->applyForLabels($application, $labels);
        }

        return $created;
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?int}
     */
    private function resolveSubject(
        LoanApplication $application,
        string $subjectKind = 'borrower',
        ?int $subjectCustomerId = null,
        ?int $loanGroupMemberId = null,
    ): array {
        if ($subjectKind === 'member') {
            $application->loadMissing('loanGroup.members');
            $member = $application->loanGroup?->members->firstWhere('id', $loanGroupMemberId);
            if (! $member) {
                throw new \InvalidArgumentException('Selected group member was not found on this application.');
            }

            return ['member', $member->customer_id ? (int) $member->customer_id : null, (int) $member->id];
        }

        if ($subjectKind === 'guarantor' && $subjectCustomerId) {
            return ['guarantor', $subjectCustomerId, null];
        }

        return ['borrower', $application->customer_id ? (int) $application->customer_id : null, null];
    }

    public function notifyBorrower(LoanApplicationDocumentRequest $request, bool $inApp = true): void
    {
        $application = $request->application()->with(['customer', 'loanGroup'])->first();
        if (! $application) {
            return;
        }

        $request->loadMissing(['subjectCustomer', 'groupMember.customer']);

        $subject = $request->subjectCustomer
            ?? ($request->subject_customer_id ? Customer::find($request->subject_customer_id) : null)
            ?? $application->customer;

        if ($subject) {
            $this->notifyDocumentRequestCustomer($subject, $application, $request, $inApp, forLeader: false);
        }

        $leader = $application->customer;
        if (
            $request->subject_kind === 'member'
            && $subject
            && $leader
            && (int) $leader->id !== (int) $subject->id
        ) {
            $this->notifyDocumentRequestCustomer($leader, $application, $request, $inApp, forLeader: true);
        }
    }

    private function notifyDocumentRequestCustomer(
        Customer $customer,
        LoanApplication $application,
        LoanApplicationDocumentRequest $request,
        bool $inApp = true,
        bool $forLeader = false,
    ): void {
        $uploadUrl = $this->borrowerActionUrl($request);
        $instructions = $request->instructions ?: 'Please upload the requested item.';
        $cta = $this->isProfileGuidedRequest($request)
            ? __('borrower.notifications.profile_revision_cta')
            : __('borrower.notifications.document_request_cta');

        $subjectName = $request->subjectCustomer?->first_name
            ?? $request->groupMember?->customer?->first_name
            ?? 'a group member';

        $fallbackBody = $forLeader
            ? "Hi {$customer->first_name}, underwriting requested \"{$request->label}\" from {$subjectName} for application {$application->application_number}. Open: {$uploadUrl}"
            : "Hi {$customer->first_name}, underwriting needs \"{$request->label}\" for application {$application->application_number}. Open: {$uploadUrl}";

        $this->notifier->notifyCustomer($customer, 'application_document_request', [
            'name'               => $customer->first_name ?? 'Customer',
            'application_number' => $application->application_number,
            'label'              => $request->label,
            'instructions'       => $forLeader
                ? "Requested from {$subjectName}: {$instructions}"
                : $instructions,
            'due_date'           => optional($request->due_at)->format('d M Y') ?? 'as soon as possible',
            'upload_url'         => $uploadUrl,
            '_fallback_body'     => $fallbackBody,
            '_fallback_subject'  => $forLeader
                ? 'Document requested from a group member'
                : 'Document requested for your loan application',
        ]);

        if ($inApp) {
            $params = [
                'application' => $application->application_number,
                'label' => $request->label,
            ];
            $body = $forLeader
                ? __('borrower.notifications.document_request_body', $params).' ('.$subjectName.')'
                : __('borrower.notifications.document_request_body', $params);

            $this->notifier->notifyInApp(
                $customer,
                $body,
                'document_request',
                'application_document_request',
                __('borrower.notifications.document_request_title'),
                $uploadUrl,
                $cta,
                [
                    'title_key' => 'borrower.notifications.document_request_title',
                    'body_key'  => 'borrower.notifications.document_request_body',
                    'params'    => $params,
                ],
            );
        }
    }

    /** @param  Collection<int, LoanApplicationDocumentRequest>  $requests */
    private function notifyBorrowerBatch(LoanApplication $application, Collection $requests): void
    {
        $application->loadMissing('customer');
        $leader = $application->customer;
        $uploadUrl = route('site.borrower.application', $application);

        /** @var array<int, Collection<int, LoanApplicationDocumentRequest>> $byCustomerId */
        $byCustomerId = [];

        foreach ($requests as $req) {
            $subjectId = (int) ($req->subject_customer_id ?: $application->customer_id);
            if ($subjectId > 0) {
                $byCustomerId[$subjectId] ??= collect();
                $byCustomerId[$subjectId]->push($req);
            }

            if (
                $req->subject_kind === 'member'
                && $leader
                && (int) $leader->id !== $subjectId
            ) {
                $byCustomerId[(int) $leader->id] ??= collect();
                $byCustomerId[(int) $leader->id]->push($req);
            }
        }

        if ($byCustomerId === [] && $leader) {
            $byCustomerId[(int) $leader->id] = $requests;
        }

        foreach ($byCustomerId as $customerId => $customerRequests) {
            $customer = ((int) $leader?->id === (int) $customerId)
                ? $leader
                : Customer::find($customerId);

            if (! $customer) {
                continue;
            }

            $unique = $customerRequests->unique('id')->values();
            $count = $unique->count();
            $labels = $unique->pluck('label')->take(3)->implode(', ');
            $suffix = $count > 3 ? '…' : '';

            $this->notifier->notifyInApp(
                $customer,
                __('borrower.notifications.document_request_batch_body', [
                    'application' => $application->application_number,
                    'count' => $count,
                    'labels' => $labels.$suffix,
                ]),
                'document_request',
                'application_document_request',
                __('borrower.notifications.document_request_title'),
                $uploadUrl,
                __('borrower.notifications.document_request_cta'),
                [
                    'title_key' => 'borrower.notifications.document_request_title',
                    'body_key'  => 'borrower.notifications.document_request_batch_body',
                    'params'    => [
                        'application' => $application->application_number,
                        'count' => $count,
                        'labels' => $labels.$suffix,
                    ],
                ],
            );
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function recordUploads(
        LoanApplicationDocumentRequest $request,
        Customer $actor,
        array $files,
        ?Customer $subjectCustomer = null,
    ): Collection {
        $application = $request->application;
        $documentCustomerId = (int) (
            $request->subject_customer_id
            ?? $subjectCustomer?->id
            ?? $actor->id
        );

        $stored = collect();

        foreach ($files as $file) {
            $path = $file->store(
                "borrower/{$documentCustomerId}/applications/{$application->id}/requests/{$request->id}",
                'public'
            );

            $stored->push(CustomerDocument::create([
                'customer_id'                          => $documentCustomerId,
                'loan_application_id'                  => $application->id,
                'loan_application_document_request_id' => $request->id,
                'document_type_id'                     => null,
                'file_path'                            => $path,
                'status'                               => 'pending_review',
            ]));
        }

        $request->update([
            'status'                   => 'uploaded',
            'admin_notes'              => null,
            'uploaded_by_customer_id'  => $actor->id,
        ]);

        $this->syncApplicationStatus($application->fresh());
        app(NotificationCtaService::class)->consumeDocumentRequestCtas((int) $application->id, (int) $request->id);

        return $stored;
    }

    public function recordClarification(
        LoanApplicationDocumentRequest $request,
        string $response,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'borrower_response' => $response,
            'status'            => 'uploaded',
            'admin_notes'       => null,
        ]);

        $this->syncApplicationStatus($request->application->fresh());

        return $request->fresh();
    }

    public function markSatisfied(
        LoanApplicationDocumentRequest $request,
        User $admin,
        ?string $notes = null,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'status'       => 'satisfied',
            'satisfied_by' => $admin->id,
            'satisfied_at' => now(),
            'admin_notes'  => $notes,
        ]);

        $request->uploads()->where('status', 'pending_review')->update([
            'status'      => 'verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $this->syncApplicationStatus($request->application->fresh());
        app(NotificationCtaService::class)->consumeDocumentRequestCtas(
            (int) $request->loan_application_id,
            (int) $request->id,
        );

        return $request->fresh();
    }

    public function reject(
        LoanApplicationDocumentRequest $request,
        User $admin,
        string $notes,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'status'      => 'rejected',
            'admin_notes' => $notes,
        ]);

        $request->uploads()->where('status', 'pending_review')->update([
            'status' => 'rejected',
            'notes'  => $notes,
        ]);

        $application = $request->application()->with('customer')->first();
        $customer = $application?->customer;

        if ($customer) {
            $uploadUrl = route('site.borrower.application', $application);

            $this->notifier->notifyCustomer($customer, 'application_document_request', [
                'name'               => $customer->first_name ?? 'Customer',
                'application_number' => $application->application_number,
                'label'              => $request->label,
                'instructions'       => "Please re-upload. Reason: {$notes}",
                'due_date'           => optional($request->due_at)->format('d M Y') ?? 'as soon as possible',
                'upload_url'         => $uploadUrl,
                '_fallback_body'     => "Hi {$customer->first_name}, your upload for \"{$request->label}\" was rejected. {$notes}. Open your application to re-upload: {$uploadUrl}",
                '_fallback_subject'  => 'Document upload rejected — action required',
            ]);

            $this->notifier->notifyInApp(
                $customer,
                __('borrower.notifications.document_request_body', [
                    'application' => $application->application_number,
                    'label' => $request->label,
                ]).' '.$notes,
                'document_request',
                'application_document_request',
                __('borrower.notifications.document_request_title'),
                $uploadUrl,
                __('borrower.notifications.document_request_cta'),
            );
        }

        $this->syncApplicationStatus($application->fresh());

        return $request->fresh();
    }

    public function syncApplicationStatus(LoanApplication $application): void
    {
        if (in_array($application->status, ['rejected', 'approved', 'disbursed'], true)) {
            return;
        }

        $needsAction = $application->documentRequests()
            ->whereIn('status', ['pending', 'rejected'])
            ->exists();

        if ($needsAction) {
            if ($application->status !== 'pending_documents') {
                $application->update(['status' => 'pending_documents']);
            }

            return;
        }

        if ($application->status !== 'pending_documents') {
            return;
        }

        $status = match ($application->current_stage) {
            'credit_appraisal', 'pre_approval', 'approval', 'disbursement' => 'under_review',
            'screening' => 'submitted',
            default     => 'submitted',
        };

        $application->update(['status' => $status]);
    }

    public function openRequestsForCustomer(Customer $customer): Collection
    {
        return LoanApplicationDocumentRequest::query()
            ->where(function ($q) use ($customer) {
                $q->where('subject_customer_id', $customer->id)
                    ->orWhere(function ($inner) use ($customer) {
                        $inner->whereNull('subject_customer_id')
                            ->whereHas('application', fn ($aq) => $aq->where('customer_id', $customer->id));
                    })
                    ->orWhere(function ($inner) use ($customer) {
                        $inner->where('subject_kind', 'member')
                            ->whereHas('application', fn ($aq) => $aq->where('customer_id', $customer->id));
                    });
            })
            ->whereIn('status', ['pending', 'rejected'])
            ->with(['application.product', 'subjectCustomer'])
            ->latest()
            ->get();
    }

    public function customerCanViewApplication(Customer $customer, LoanApplication $application): bool
    {
        if ((int) $application->customer_id === (int) $customer->id) {
            return true;
        }

        $application->loadMissing('loanGroup.members');

        return (bool) $application->loanGroup?->members->contains(
            fn ($member) => (int) $member->customer_id === (int) $customer->id
                && ($member->member_status ?? 'active') === 'active'
        );
    }

    public function customerCanFulfillRequest(Customer $customer, LoanApplicationDocumentRequest $request): bool
    {
        $request->loadMissing('application');

        if ($request->subject_customer_id) {
            if ((int) $request->subject_customer_id === (int) $customer->id) {
                return true;
            }
        } elseif ((int) $request->application?->customer_id === (int) $customer->id) {
            return true;
        }

        return $request->subject_kind === 'member'
            && (int) $request->application?->customer_id === (int) $customer->id;
    }

    public function pendingReviewCount(): int
    {
        return LoanApplicationDocumentRequest::where('status', 'uploaded')->count();
    }

    /**
     * Files to show for a request: loan uploads first, else profile-linked docs.
     *
     * @return Collection<int, CustomerDocument>
     */
    public function displayDocumentsForRequest(LoanApplicationDocumentRequest $request, Customer $customer): Collection
    {
        $request->loadMissing('uploads');
        if ($request->uploads->isNotEmpty()) {
            return $request->uploads->values();
        }

        if ($this->borrowerActionKind($request) !== 'income'
            && ! $this->isProfileGuidedRequest($request)) {
            return collect();
        }

        $codes = app(ProfileRevisionService::class)->documentCodesForLabel((string) $request->label);
        if ($codes === []) {
            if ($this->borrowerActionKind($request) === 'income') {
                $codes = ['bank_statement', 'mobile_money_statement', 'salary_slip', 'employment_contract'];
            } else {
                return collect();
            }
        }

        return app(ProfileDocumentService::class)
            ->latestByCodes($customer, $codes)
            ->values();
    }

    /**
     * When the borrower uploads profile-guided income docs, mark matching open
     * underwriting requests as uploaded so the loan profile stops showing Pending.
     *
     * @param  list<string>  $uploadedCodes
     */
    public function markIncomeRequestsUploadedFromProfile(Customer $customer, array $uploadedCodes): int
    {
        $uploadedCodes = array_values(array_filter(array_map('strval', $uploadedCodes)));
        if ($uploadedCodes === []) {
            return 0;
        }

        $revision = app(ProfileRevisionService::class);
        $marked = 0;

        $requests = LoanApplicationDocumentRequest::query()
            ->whereHas('application', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                    ->whereNotIn('status', ['withdrawn', 'rejected', 'disbursed']);
            })
            ->whereIn('status', ['pending', 'rejected'])
            ->with('application')
            ->get();

        foreach ($requests as $request) {
            if ($this->borrowerActionKind($request) !== 'income') {
                continue;
            }

            $labelCodes = $revision->documentCodesForLabel((string) $request->label);
            $matches = $labelCodes === []
                ? true
                : count(array_intersect($labelCodes, $uploadedCodes)) > 0;

            if (! $matches) {
                continue;
            }

            $request->update([
                'status' => 'uploaded',
                'admin_notes' => null,
            ]);
            $marked++;

            if ($application = $request->application) {
                $this->syncApplicationStatus($application->fresh());
                app(NotificationCtaService::class)->consumeDocumentRequestCtas(
                    (int) $application->id,
                    (int) $request->id,
                );
            }
        }

        if ($marked > 0) {
            $revision->clearForTarget($customer->fresh(), 'income');
        }

        return $marked;
    }

    /**
     * In-app reminder for open document requests due tomorrow.
     * Lists the requested documents and links to the application.
     */
    public function sendDueTomorrowReminders(): int
    {
        $start = now()->addDay()->startOfDay();
        $end = now()->addDay()->endOfDay();
        $dueOn = $start->toDateString();
        $sent = 0;

        LoanApplicationDocumentRequest::query()
            ->with(['application.customer'])
            ->whereIn('status', ['pending', 'rejected'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$start, $end])
            ->orderBy('id')
            ->get()
            ->groupBy('loan_application_id')
            ->each(function (Collection $requests) use (&$sent, $dueOn) {
                $application = $requests->first()?->application;
                $customer = $application?->customer;
                if (! $application || ! $customer) {
                    return;
                }

                if (in_array($application->status, ['withdrawn', 'rejected', 'disbursed', 'expired'], true)) {
                    return;
                }

                $template = 'application_document_request_reminder_1';
                if (NotificationLog::query()
                    ->where('customer_id', $customer->id)
                    ->where('channel', 'in_app')
                    ->where('template', $template)
                    ->where('meta->loan_application_id', $application->id)
                    ->where('meta->due_on', $dueOn)
                    ->exists()) {
                    return;
                }

                $count = $requests->count();
                $labels = $requests->pluck('label')->filter()->take(7)->implode(', ');
                $suffix = $count > 7 ? '…' : '';
                $dueDate = optional($requests->first()->due_at)
                    ->timezone(config('app.timezone'))
                    ->format('d M Y');

                $params = [
                    'application' => $application->application_number,
                    'count' => $count,
                    'labels' => $labels.$suffix,
                    'date' => $dueDate ?: $dueOn,
                ];

                $uploadUrl = route('site.borrower.application', $application);

                $this->notifier->notifyInApp(
                    $customer,
                    __('borrower.notifications.document_request_reminder_body', $params),
                    'document_request',
                    $template,
                    __('borrower.notifications.document_request_reminder_title'),
                    $uploadUrl,
                    __('borrower.notifications.document_request_cta'),
                    [
                        'title_key' => 'borrower.notifications.document_request_reminder_title',
                        'body_key' => 'borrower.notifications.document_request_reminder_body',
                        'params' => $params,
                        'loan_application_id' => $application->id,
                        'due_on' => $dueOn,
                    ],
                );

                $sent++;
            });

        return $sent;
    }
}
