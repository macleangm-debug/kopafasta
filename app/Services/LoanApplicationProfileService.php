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
    ) {}

    /** @return array<string, mixed> */
    public function forDraft(Customer $customer, LoanApplicationDraft $draft): array
    {
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);
        $resumeTarget = $this->drafts->resumeTarget($customer, $draft);
        $requirementSummary = $this->progress->profileProgress($customer, $product);
        $profileUrl = route('site.borrower.loan-profile.draft', $draft);
        $missingRequirements = $this->missingProfileRequirements($customer, $product, $profileUrl);
        $next = $this->nextAction->forDraft($customer, $draft, $product);

        return [
            'is_draft'             => true,
            'draft'                => $draft,
            'application'          => null,
            'loan'                 => null,
            'summary'              => $this->draftSummary($customer, $draft, $product),
            'status'               => $this->draftStatus($draft, $next),
            'progress'             => [
                'percent'   => $requirementSummary['percent'],
                'completed' => $requirementSummary['completed'],
                'missing'   => $requirementSummary['missing'],
                'timeline'  => $this->progress->wizardTimeline($customer, $draft, $product),
            ],
            'missing_requirements' => $missingRequirements,
            'next_action'          => $next,
            'can_submit'           => (bool) ($next['can_submit'] ?? false) && ($next['code'] ?? '') === 'submit_application',
            'actions'              => $this->primaryActions($next),
            'resume_target'        => $resumeTarget,
            'wizard_url'           => $this->drafts->wizardApplyUrl($draft, $resumeTarget),
            'document_requests'    => [],
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

        $profileProgress = $this->progress->profileProgress($customer, $application->product);
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

        $borrowerStatus = $this->borrowerStatus->forApplication($application);

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
                'percent'   => $pipelineProgress['percent'],
                'completed' => $profileProgress['completed'],
                'missing'   => $profileProgress['missing'],
                'timeline'  => $pipelineSteps,
            ],
            'missing_requirements' => $missingRequirements,
            'next_action'          => $next,
            'can_submit'           => false,
            'actions'              => $this->primaryActions($next),
            'resume_target'        => null,
            'wizard_url'           => null,
            'document_requests'    => $application->documentRequests()->with('uploads')->latest()->get(),
            'document_request_groups' => $this->borrowerStatus->groupedDocumentRequests(
                $application->documentRequests()->with('uploads')->latest()->get()
            ),
            'guarantor_invitations' => $guarantorInvitations,
            'customer_guarantors'  => $application->customerGuarantors,
            'product_requirements' => $requirements,
            'requirement_uploads'  => $uploads,
            'offer'                => $offer,
        ];
    }

    /** @return array<string, mixed> */
    private function draftSummary(Customer $customer, LoanApplicationDraft $draft, ?LoanProduct $product): array
    {
        $form = ($draft->payload ?? [])['form'] ?? [];

        return [
            'loan_type'           => $this->loanTypeLabel($product),
            'product_name'        => $product?->name ?? __('borrower.apply.title'),
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
            'product_name'        => $product?->name ?? '—',
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
                $items[] = [
                    'key'        => 'request-'.$request->id,
                    'label'      => $request->label,
                    'upload_url' => route('site.borrower.application', $application->id).'#request-'.$request->id,
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
}
