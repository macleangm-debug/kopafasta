<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;

class LoanApplicationProfileService
{
    public function __construct(
        private readonly BorrowerApplicationsDashboardService $dashboard,
        private readonly LoanApplicationDraftService $drafts,
        private readonly ApplicationProgressService $progress,
        private readonly ProfileValidationService $profileValidation,
        private readonly DisplayedRateService $displayedRate,
        private readonly LoanApplicationNextActionService $nextAction,
        private readonly ApplicationBorrowerStatusService $borrowerStatus,
        private readonly RepaymentScheduleGenerator $scheduleGenerator,
    ) {}

    /** @return array<string, mixed> */
    public function forDraft(Customer $customer, LoanApplicationDraft $draft): array
    {
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);
        $resumeTarget = $this->drafts->resumeTarget($customer, $draft);
        $profileCompletion = app(ProfileCompletionService::class)->calculate($customer);
        $profileSummary = [
            'percent'   => (int) ($profileCompletion['percent'] ?? 0),
            'completed' => collect($profileCompletion['sections'] ?? [])->where('complete', true)->count(),
            'missing'   => collect($profileCompletion['sections'] ?? [])->where('complete', false)->pluck('label')->values()->all(),
        ];
        $applicationSummary = $this->progress->applicationDraftProgress($customer, $draft, $product);
        $profileUrl = route('site.borrower.loan-profile.draft', $draft);
        $missingRequirements = $this->missingProfileRequirements($customer, $product, $profileUrl);
        $next = $this->nextAction->forDraft($customer, $draft, $product);
        $wizardUrl = $this->drafts->wizardApplyUrl($draft, $resumeTarget);

        $stepPlan = app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product);
        $stepKeys = collect($stepPlan)->pluck('key')->all();
        $quoteStepKey = $this->drafts->quoteLikeStepKey($stepPlan);
        $editQuoteUrl = $quoteStepKey
            ? $this->drafts->wizardApplyUrlForStep($draft, $quoteStepKey, ['return_to' => 'profile'])
            : null;
        $editGuarantorUrl = in_array('guarantor', $stepKeys, true)
            ? $this->drafts->wizardApplyUrlForStep($draft, 'guarantor', ['return_to' => 'profile'])
            : null;

        return [
            'is_draft'             => true,
            'draft'                => $draft,
            'application'          => null,
            'loan'                 => null,
            'summary'              => $this->draftSummary($customer, $draft, $product),
            'status'               => $this->draftStatus($draft, $next),
            'progress'             => [
                'profile_percent'            => $profileSummary['percent'],
                'profile_complete'         => $profileSummary['percent'] >= 100,
                'application_percent'        => $applicationSummary['percent'],
                'application_status_label' => $applicationSummary['label'],
                'percent'                    => $applicationSummary['percent'],
                'completed'                  => $profileSummary['completed'],
                'missing'                    => $profileSummary['missing'],
                'timeline'                   => $applicationSummary['steps'],
            ],
            'missing_requirements' => $missingRequirements,
            'next_action'          => $next,
            'can_submit'           => (bool) ($next['can_submit'] ?? false) && ($next['code'] ?? '') === 'submit_application',
            'actions'              => $this->primaryActions($next),
            'resume_target'        => $resumeTarget,
            'wizard_url'           => $wizardUrl,
            'edit_quote_url'       => $editQuoteUrl,
            'edit_guarantor_url'   => $editGuarantorUrl,
            'snapshot'             => $this->drafts->adminSnapshot($draft),
            'product_details'      => $this->productDetailsForDraft($draft, $product),
            'document_requests'    => [],
            'underwriting_actions' => [],
            'guarantor_invitations' => collect(),
            'customer_guarantors'  => collect(),
            'product_requirements' => collect(),
            'requirement_uploads'  => collect(),
            'offer'                => null,
        ];
    }

    /** @return array<string, mixed> */
    public function forApplication(Customer $customer, LoanApplication $application): array
    {
        $application->loadMissing([
            'product.requirements',
            'documentRequests.uploads',
            'customerGuarantors.guarantor',
        ]);

        $pipelineProgress = $this->borrowerStatus->timeline($application);
        $uploads = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->where('loan_application_id', $application->id)
            ->whereNotNull('loan_product_requirement_id')
            ->latest()
            ->get()
            ->groupBy('loan_product_requirement_id');

        $requirements = $application->product?->requirements ?? collect();
        $loan = Loan::query()
            ->where('loan_application_id', $application->id)
            ->with('product')
            ->first();

        $nextDue = null;
        if ($loan) {
            $nextDue = RepaymentSchedule::query()
                ->where('loan_id', $loan->id)
                ->where('status', '!=', 'paid')
                ->orderBy('due_date')
                ->first();
        }

        $guarantorInvitations = \App\Models\GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->latest()
            ->get();

        $offer = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        $profileCompletion = app(ProfileCompletionService::class)->calculate($customer);
        $profileProgress = [
            'percent'   => (int) ($profileCompletion['percent'] ?? 0),
            'completed' => collect($profileCompletion['sections'] ?? [])->where('complete', true)->count(),
            'missing'   => collect($profileCompletion['sections'] ?? [])->where('complete', false)->pluck('label')->values()->all(),
        ];
        $missingRequirements = $this->submittedMissingRequirements($customer, $application, $requirements, $uploads);
        $next = $this->nextAction->forApplication($customer, $application, $missingRequirements);

        $pipelineSteps = collect($pipelineProgress['steps'] ?? [])
            ->map(fn (array $step) => [
                'label'    => $step['label'],
                'complete' => (bool) ($step['complete'] ?? false),
                'current'  => (bool) ($step['current'] ?? ($step['active'] ?? false)),
            ])
            ->values()
            ->all();

        $repaymentSummary = null;
        if ($loan && $this->isDisbursedApplication($application, $loan)) {
            $firstSchedule = RepaymentSchedule::query()
                ->where('loan_id', $loan->id)
                ->orderBy('installment_no')
                ->first();

            $repaymentSummary = [
                'disbursed_at'        => $loan->disbursement_date,
                'first_repayment_at'  => $firstSchedule?->due_date ?? $nextDue?->due_date,
                'frequency'           => $loan->product?->repayment_cadence ?? 'weekly',
            ];
        }

        $borrowerStatus = $this->borrowerStatus->forApplication($application);
        $disbursementChecklist = app(ApplicationDisbursementReadinessService::class)
            ->borrowerDisbursementChecklist($application);

        return [
            'is_draft'             => false,
            'draft'                => null,
            'application'          => $application,
            'loan'                 => $loan,
            'next_due'             => $nextDue,
            'summary'              => $this->applicationSummary($application, $loan),
            'status'               => [
                'code'    => $borrowerStatus['code'],
                'label'   => $borrowerStatus['label'],
                'tone'    => $borrowerStatus['tone'],
                'detail'  => $this->statusDetail($application),
            ],
            'progress'             => [
                'profile_percent'            => $profileProgress['percent'],
                'profile_complete'         => $profileProgress['percent'] >= 100,
                'application_percent'        => $pipelineProgress['percent'],
                'application_status_label'   => $borrowerStatus['label'],
                'percent'                    => $pipelineProgress['percent'],
                'completed'                  => $profileProgress['completed'],
                'missing'                    => $profileProgress['missing'],
                'timeline'                   => $pipelineSteps,
                'is_loan_progress'           => (bool) ($pipelineProgress['is_loan_progress'] ?? false),
                'timeline_title'             => ($pipelineProgress['is_loan_progress'] ?? false)
                    ? __('borrower.loan_progress.title')
                    : __('borrower.loan_profile.application_progress'),
            ],
            'missing_requirements' => $missingRequirements,
            'next_action'          => $next,
            'can_submit'           => false,
            'actions'              => $this->primaryActions($next),
            'resume_target'        => null,
            'wizard_url'           => null,
            'edit_quote_url'       => null,
            'edit_guarantor_url'   => app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($application)
                ? app(\App\Services\GuarantorSupplementService::class)->borrowerWizardUrl($application)
                : null,
            'document_requests'    => $application->documentRequests()->with('uploads')->latest()->get(),
            'document_request_groups' => $this->borrowerStatus->groupedDocumentRequests(
                $application->documentRequests()->with('uploads')->latest()->get()
            ),
            'underwriting_actions' => app(ApplicationDocumentRequestService::class)
                ->openGuidedActionsForApplication($application),
            'guarantor_invitations' => $guarantorInvitations,
            'customer_guarantors'  => $application->customerGuarantors,
            'product_requirements' => $requirements,
            'requirement_uploads'  => $uploads,
            'offer'                => $offer,
            'schedule_preview'     => $this->shouldShowSchedulePreview($application, $loan)
                ? $this->schedulePreview($application)
                : null,
            'repayment_summary'    => $repaymentSummary,
            'disbursement_details' => app(CustomerDisbursementDetailsService::class)
                ->snapshotForApplication($application),
            'disbursement_checklist' => $disbursementChecklist,
            'handover_milestones'    => app(AssetHandoverMilestoneService::class)->forApplication($application),
            'product_details'        => $this->productDetailsForApplication($application),
        ];
    }

    /**
     * Type-specific summary for draft applications (group / asset-backed / asset lending).
     *
     * @return array<string, mixed>|null
     */
    private function productDetailsForDraft(LoanApplicationDraft $draft, ?LoanProduct $product): ?array
    {
        if (! $product) {
            return null;
        }

        $payload = $draft->payload ?? [];
        $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
        $snapshot = $this->drafts->adminSnapshot($draft);

        if (is_group_loan_product($product)) {
            $group = is_array($payload['group'] ?? null) ? $payload['group'] : [];
            $progress = app(GroupMemberProgressService::class)->forDraftPayload($group);

            return [
                'type'     => 'group',
                'title'    => __('borrower.loan_profile.special.group_title'),
                'group'    => [
                    'name'                => $group['name'] ?? null,
                    'purpose'             => $group['purpose'] ?? null,
                    'amount_per_member'   => $group['amount_per_member'] ?? ($form['requested_amount'] ?? null),
                    'target_member_count' => $group['target_member_count'] ?? ($progress['target'] ?? null),
                ],
                'progress' => $progress,
            ];
        }

        if (is_asset_backed_loan_product($product->code ?? null)) {
            return [
                'type'                => 'asset_backed',
                'title'               => __('borrower.loan_profile.special.asset_backed_title'),
                'asset'               => [
                    'description' => $form['asset_description'] ?? $form['collateral_description'] ?? ($payload['asset']['description'] ?? null),
                    'type'        => $form['asset_type'] ?? ($payload['asset']['type'] ?? null),
                    'value'       => $form['asset_value'] ?? $form['estimated_value'] ?? ($payload['asset']['value'] ?? null),
                    'location'    => $form['asset_location'] ?? ($payload['asset']['location'] ?? null),
                ],
                'photos'              => $snapshot['asset_photos'] ?? [],
                'ownership_documents' => $snapshot['ownership_documents'] ?? [],
                'insurance_documents' => $snapshot['insurance_documents'] ?? [],
                'steps'               => $this->assetBackedSteps($snapshot, $form),
            ];
        }

        if (in_array((string) ($product->category ?? ''), ['asset_finance', 'asset_lending'], true)
            || filled($draft->asset_reservation_id)) {
            $reservation = null;
            if ($draft->asset_reservation_id) {
                $reservation = \App\Models\AssetReservation::query()
                    ->with('asset')
                    ->find($draft->asset_reservation_id);
            }

            return [
                'type'                => 'asset_lending',
                'title'               => __('borrower.loan_profile.special.asset_lending_title'),
                'asset'               => [
                    'name'      => $reservation?->asset?->name
                        ?? $form['asset_name']
                        ?? ($payload['asset']['name'] ?? null),
                    'reference' => $reservation?->asset?->sku
                        ?? $reservation?->asset?->reference
                        ?? null,
                    'price'     => $reservation?->asset?->price
                        ?? $form['asset_price']
                        ?? null,
                    'remaining' => $form['remaining_loan'] ?? ($payload['asset_application']['remaining_loan'] ?? null),
                ],
                'photos'              => $snapshot['asset_photos'] ?? [],
                'insurance_documents' => $snapshot['insurance_documents'] ?? [],
                'steps'               => $this->assetLendingSteps($draft, $reservation),
            ];
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function productDetailsForApplication(LoanApplication $application): ?array
    {
        $product = $application->product;
        if (! $product) {
            return null;
        }

        $meta = is_array($application->metadata ?? null) ? $application->metadata : [];

        if (is_group_loan_product($product)) {
            $progress = app(GroupMemberProgressService::class)->forLoanApplication($application);
            $group = $application->loanGroup;

            return [
                'type'     => 'group',
                'title'    => __('borrower.loan_profile.special.group_title'),
                'group'    => [
                    'name'                => $group?->name,
                    'purpose'             => $group?->purpose,
                    'amount_per_member'   => $group?->amount_per_member,
                    'target_member_count' => $group?->target_member_count ?? ($progress['target'] ?? null),
                ],
                'progress' => $progress,
            ];
        }

        if (is_asset_backed_loan_product($product->code ?? null)) {
            return [
                'type'  => 'asset_backed',
                'title' => __('borrower.loan_profile.special.asset_backed_title'),
                'asset' => [
                    'description' => $meta['asset_description'] ?? $application->purpose,
                    'type'        => $meta['asset_type'] ?? null,
                    'value'       => $meta['asset_value'] ?? null,
                    'location'    => $meta['asset_location'] ?? null,
                ],
                'steps' => [
                    ['key' => 'details', 'label' => __('borrower.loan_profile.special.step_details'), 'complete' => filled($meta['asset_description'] ?? $application->purpose)],
                    ['key' => 'valuation', 'label' => __('borrower.loan_profile.special.step_valuation'), 'complete' => ($meta['valuation_status'] ?? '') === 'complete'],
                    ['key' => 'insurance', 'label' => __('borrower.loan_profile.special.step_insurance'), 'complete' => (bool) ($meta['insurance_complete'] ?? false)],
                    ['key' => 'handover', 'label' => __('borrower.loan_profile.special.step_handover'), 'complete' => (bool) ($meta['handover_complete'] ?? false)],
                ],
            ];
        }

        if (in_array((string) ($product->category ?? ''), ['asset_finance', 'asset_lending'], true)) {
            return [
                'type'  => 'asset_lending',
                'title' => __('borrower.loan_profile.special.asset_lending_title'),
                'asset' => [
                    'name'      => $meta['asset_name'] ?? null,
                    'reference' => $meta['asset_reference'] ?? null,
                    'price'     => $meta['asset_price'] ?? null,
                ],
                'steps' => [
                    ['key' => 'reservation', 'label' => __('borrower.loan_profile.special.step_reservation'), 'complete' => true],
                    ['key' => 'application', 'label' => __('borrower.loan_profile.special.step_application'), 'complete' => true],
                    ['key' => 'gps', 'label' => __('borrower.loan_profile.special.step_gps'), 'complete' => (bool) ($meta['gps_installed'] ?? false)],
                    ['key' => 'insurance', 'label' => __('borrower.loan_profile.special.step_insurance'), 'complete' => (bool) ($meta['insurance_complete'] ?? false)],
                    ['key' => 'handover', 'label' => __('borrower.loan_profile.special.step_handover'), 'complete' => (bool) ($meta['handover_complete'] ?? false)],
                ],
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $form
     * @return list<array{key: string, label: string, complete: bool}>
     */
    private function assetBackedSteps(array $snapshot, array $form): array
    {
        $hasDetails = filled($form['asset_description'] ?? $form['collateral_description'] ?? null)
            || filled($form['asset_type'] ?? null);
        $hasPhotos = ! empty($snapshot['asset_photos']);
        $hasOwnership = ! empty($snapshot['ownership_documents']);
        $hasInsurance = ! empty($snapshot['insurance_documents']);

        return [
            ['key' => 'details', 'label' => __('borrower.loan_profile.special.step_details'), 'complete' => $hasDetails],
            ['key' => 'photos', 'label' => __('borrower.loan_profile.special.step_photos'), 'complete' => $hasPhotos],
            ['key' => 'ownership', 'label' => __('borrower.loan_profile.special.step_ownership'), 'complete' => $hasOwnership],
            ['key' => 'insurance', 'label' => __('borrower.loan_profile.special.step_insurance'), 'complete' => $hasInsurance],
        ];
    }

    /** @return list<array{key: string, label: string, complete: bool}> */
    private function assetLendingSteps(LoanApplicationDraft $draft, mixed $reservation): array
    {
        $payload = $draft->payload ?? [];
        $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];

        return [
            ['key' => 'reservation', 'label' => __('borrower.loan_profile.special.step_reservation'), 'complete' => (bool) $reservation || filled($draft->asset_reservation_id)],
            ['key' => 'amount', 'label' => __('borrower.loan_profile.special.step_amount'), 'complete' => (float) ($form['requested_amount'] ?? 0) > 0],
            ['key' => 'tenure', 'label' => __('borrower.loan_profile.special.step_tenure'), 'complete' => (int) ($form['requested_tenure_months'] ?? 0) > 0],
            ['key' => 'review', 'label' => __('borrower.loan_profile.special.step_review'), 'complete' => (string) ($draft->phase ?? '') === 'review' || (int) ($draft->step ?? 0) >= 3],
        ];
    }

    /** @return array<string, mixed> */
    private function draftSummary(Customer $customer, LoanApplicationDraft $draft, ?LoanProduct $product): array
    {
        $form = ($draft->payload ?? [])['form'] ?? [];

        return [
            'loan_type'           => $this->loanTypeLabel($product),
            'product_name'        => $product?->localizedName() ?? __('borrower.apply.title'),
            'requested_amount'    => $this->drafts->requestedAmount($draft),
            'requested_tenure'    => (int) ($form['requested_tenure_months'] ?? 0),
            'interest_rate_label' => $product
                ? $this->displayedRate->formatBorrowerRateRange($product)
                : null,
            'application_number'  => $draft->draft_reference ?: __('borrower.applications_list.draft_reference'),
            'created_at'          => $draft->created_at,
            'updated_at'          => $draft->saved_at ?? $draft->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    private function applicationSummary(LoanApplication $application, ?Loan $loan): array
    {
        $product = $application->product;

        return [
            'loan_type'           => $this->loanTypeLabel($product),
            'product_name'        => $product?->localizedName() ?? '—',
            'requested_amount'    => (float) $application->requested_amount,
            'requested_tenure'    => (int) $application->requested_tenure_months,
            'interest_rate_label' => $product
                ? $this->displayedRate->formatBorrowerRateRange($product)
                : null,
            'application_number'  => $application->application_number,
            'loan_number'         => $loan?->loan_number,
            'created_at'          => $application->created_at,
            'updated_at'          => $application->updated_at,
        ];
    }

    /**
     * @return list<array{key: string, label: string, upload_url: string, complete: bool}>
     */
    private function missingProfileRequirements(
        Customer $customer,
        ?LoanProduct $product,
        string $returnUrl,
    ): array {
        $items = [];

        foreach ($this->progress->profileRequirements($customer, $product) as $requirement) {
            if ($requirement['complete'] ?? false) {
                continue;
            }

            if (empty($requirement['action_url'])) {
                continue;
            }

            $items[] = [
                'key'        => $requirement['key'],
                'label'      => $requirement['label'],
                'upload_url' => $this->appendReturn($requirement['action_url'], $returnUrl),
                'complete'   => false,
            ];
        }

        return collect($items)->unique('key')->values()->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\LoanProductRequirement>  $requirements
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, CustomerDocument>>  $uploads
     * @return list<array{key: string, label: string, upload_url: string, complete: bool}>
     */
    private function submittedMissingRequirements(
        Customer $customer,
        LoanApplication $application,
        \Illuminate\Support\Collection $requirements,
        \Illuminate\Support\Collection $uploads,
    ): array {
        $items = [];

        foreach ($requirements->where('is_required', true) as $requirement) {
            $myUploads = $uploads->get($requirement->id, collect());
            $satisfied = $myUploads->contains(fn (CustomerDocument $doc) => in_array($doc->status, ['verified', 'approved', 'pending_review', 'pending'], true))
                || $this->profileRequirementSatisfied($customer, $requirement->name);

            if (! $satisfied) {
                $items[] = [
                    'key'        => 'requirement-'.$requirement->id,
                    'label'      => $requirement->name,
                    'upload_url' => route('site.borrower.application', $application->id).'#requirement-'.$requirement->id,
                    'complete'   => false,
                ];
            }
        }

        foreach ($application->documentRequests as $request) {
            if ($request->needsBorrowerAction()) {
                $docService = app(ApplicationDocumentRequestService::class);
                $items[] = [
                    'key'        => 'request-'.$request->id,
                    'label'      => $request->label,
                    'upload_url' => $docService->borrowerActionUrl($request),
                    'complete'   => false,
                ];
            }
        }

        return $items;
    }

    private function profileRequirementSatisfied(Customer $customer, string $requirementName): bool
    {
        $name = strtolower($requirementName);

        if (str_contains($name, 'residence') || str_contains($name, 'address')) {
            return $this->profileValidation->hasResidenceLetter($customer);
        }

        if (str_contains($name, 'income') || str_contains($name, 'bank') || str_contains($name, 'statement') || str_contains($name, 'mobile')) {
            return app(IncomeProofService::class)->satisfiesRequirement($customer);
        }

        if (str_contains($name, 'national') || str_contains($name, 'nida') || str_contains($name, 'identity')) {
            return $this->profileValidation->nationalIdUploadsComplete($customer);
        }

        return false;
    }

    /** @param  array{button_label: string, url: string, tone?: string}  $next */
    /** @return list<array{label: string, url: string, tone: string}> */
    private function primaryActions(array $next): array
    {
        return [[
            'label' => $next['button_label'],
            'url'   => $next['url'],
            'tone'  => $next['tone'] ?? 'primary',
        ]];
    }

    private function statusDetail(LoanApplication $application): ?string
    {
        return $this->borrowerStatus->borrowerDetail($application);
    }

    private function appendReturn(string $url, string $returnUrl): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'return='.urlencode($returnUrl);
    }

    /** @param  array{code?: string}  $next */
    /** @return array{code: string, label: string, tone: string} */
    private function draftStatus(LoanApplicationDraft $draft, array $next): array
    {
        $payload = $draft->payload ?? [];
        $hasSignature = filled(($payload['borrower_signature']['signature_data'] ?? null));

        if ($hasSignature) {
            return [
                'code'  => 'ready_for_submission',
                'label' => __('borrower.loan_profile.statuses.ready_for_submission'),
                'tone'  => 'emerald',
            ];
        }

        return match ($next['code'] ?? 'continue_application') {
            'sign_application' => [
                'code'  => 'ready_for_signature',
                'label' => __('borrower.loan_profile.statuses.ready_for_signature'),
                'tone'  => 'amber',
            ],
            'review_application' => [
                'code'  => 'ready_for_review',
                'label' => __('borrower.loan_profile.statuses.ready_for_review'),
                'tone'  => 'sky',
            ],
            'submit_application' => [
                'code'  => 'ready_for_submission',
                'label' => __('borrower.loan_profile.statuses.ready_for_submission'),
                'tone'  => 'emerald',
            ],
            default => [
                'code'  => 'draft',
                'label' => $this->dashboard->borrowerStatusLabel('draft'),
                'tone'  => 'gray',
            ],
        };
    }

    private function loanTypeLabel(?LoanProduct $product): string
    {
        if (! $product) {
            return __('borrower.apply.product_type.general');
        }

        $category = (string) ($product->category ?? '');

        return match ($category) {
            'salary_loan'   => __('borrower.apply.product_type.salary'),
            'business_loan' => __('borrower.apply.product_type.business'),
            'agriculture'   => __('borrower.apply.product_type.agriculture'),
            'asset_finance' => __('borrower.apply.product_type.asset'),
            'emergency'     => __('borrower.apply.product_type.emergency'),
            'group'         => __('borrower.applications_list.loan_type_group'),
            default         => ucfirst(str_replace('_', ' ', $category ?: $product->name)),
        };
    }

    private function shouldShowSchedulePreview(LoanApplication $application, ?Loan $loan): bool
    {
        if ($loan || $this->isDisbursedApplication($application, $loan)) {
            return false;
        }

        if (in_array((string) $application->status, ['approved', 'pre_approved', 'disbursed'], true)) {
            return false;
        }

        return ! in_array((string) ($application->current_stage ?? ''), ['approval', 'disbursement'], true);
    }

    private function isDisbursedApplication(LoanApplication $application, ?Loan $loan): bool
    {
        if ((string) $application->status === 'disbursed') {
            return true;
        }

        return $loan && in_array((string) $loan->status, ['active', 'disbursed'], true);
    }

    /** @return array{term_months: int, installment_amount: float, installments: list<array{label: string, total_due: float}>}|null */
    private function schedulePreview(LoanApplication $application): ?array
    {
        $product = $application->product;
        if (! $product) {
            return null;
        }

        $amount = (float) ($application->requested_amount ?? 0);
        $tenure = (int) ($application->requested_tenure_months ?? 0);
        if ($amount <= 0 || $tenure <= 0) {
            return null;
        }

        $rate = $this->displayedRate->displayedMonthlyRate($product, $amount);
        $cadence = $product->repayment_cadence ?? 'weekly';
        $rows = $this->scheduleGenerator->preview($amount, $rate, $tenure, $cadence);
        $installmentAmount = (float) ($rows[0]['total_due'] ?? 0);

        return [
            'term_months'         => $tenure,
            'installment_amount'  => $installmentAmount,
            'frequency'           => $cadence,
            'installment_count'   => count($rows),
            'total_repayable'     => round(collect($rows)->sum('total_due'), 2),
        ];
    }
}
