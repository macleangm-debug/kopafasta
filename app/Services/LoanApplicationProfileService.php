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
        private readonly LoanProductReadinessService $readiness,
        private readonly SmartLoanApplicationWizardService $wizard,
        private readonly ApplicationRequirementsService $requirements,
        private readonly IncomeProofService $incomeProof,
        private readonly ProfileValidationService $profileValidation,
        private readonly ProfileCompletionService $profileCompletion,
        private readonly DisplayedRateService $displayedRate,
    ) {}

    /** @return array<string, mixed> */
    public function forDraft(Customer $customer, LoanApplicationDraft $draft): array
    {
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);
        $resumeTarget = $this->drafts->resumeTarget($customer, $draft);
        $requirementSummary = $this->requirementSummary($customer, $product, $draft);
        $progress = $this->dashboard->draftProgress($customer, $draft, $product);
        $profileUrl = route('site.borrower.loan-profile.draft', $draft);

        return [
            'is_draft'             => true,
            'draft'                => $draft,
            'application'          => null,
            'loan'                 => null,
            'summary'              => $this->draftSummary($customer, $draft, $product),
            'status'               => [
                'code'  => 'draft',
                'label' => $this->dashboard->borrowerStatusLabel('draft'),
                'tone'  => 'gray',
            ],
            'progress'             => [
                'percent'   => $progress['percent'],
                'completed' => $requirementSummary['completed'],
                'missing'   => $requirementSummary['missing'],
            ],
            'missing_requirements' => $this->missingRequirementsWithUpload($customer, $profileUrl),
            'actions'              => $this->draftActions($customer, $draft, $product, $resumeTarget, $requirementSummary),
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
            'customerGuarantors.guarantorCustomer',
        ]);

        $progress = $this->dashboard->submittedProgress($application);
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

        $pipelineSteps = collect($progress['steps'] ?? [])
            ->map(fn (array $step) => [
                'label'    => $step['label'],
                'complete' => (bool) ($step['complete'] ?? false),
            ])
            ->values()
            ->all();

        $completed = collect($pipelineSteps)->where('complete', true)->pluck('label')->all();
        $missing = collect($pipelineSteps)->where('complete', false)->pluck('label')->all();

        return [
            'is_draft'             => false,
            'draft'                => null,
            'application'          => $application,
            'loan'                 => $loan,
            'next_due'             => $nextDue,
            'summary'              => $this->applicationSummary($application),
            'status'               => [
                'code'    => (string) $application->status,
                'label'   => $this->dashboard->borrowerStatusLabel((string) $application->status, $application->current_stage),
                'tone'    => $this->dashboard->statusTone((string) $application->status),
                'detail'  => $this->statusDetail($application),
            ],
            'progress'             => [
                'percent'   => $progress['percent'],
                'completed' => $completed,
                'missing'   => $missing,
            ],
            'missing_requirements' => $this->submittedMissingRequirements($application, $requirements, $uploads),
            'actions'              => $this->submittedActions($application, $offer),
            'resume_target'        => null,
            'wizard_url'           => null,
            'document_requests'    => $application->documentRequests()->with('uploads')->latest()->get(),
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
        $rate = $product ? $this->displayedRate->displayedMonthlyRate($product) : null;

        return [
            'loan_type'           => $this->loanTypeLabel($product),
            'product_name'        => $product?->name ?? __('borrower.apply.title'),
            'requested_amount'    => $this->drafts->requestedAmount($draft),
            'requested_tenure'    => (int) ($form['requested_tenure_months'] ?? 0),
            'interest_rate_label' => $product
                ? $this->displayedRate->formatBorrowerRateRange($product)
                : null,
            'application_number'  => __('borrower.applications_list.draft_reference'),
            'created_at'          => $draft->created_at,
            'updated_at'          => $draft->saved_at ?? $draft->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    private function applicationSummary(LoanApplication $application): array
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
            'created_at'          => $application->created_at,
            'updated_at'          => $application->updated_at,
        ];
    }

    /**
     * @return array{completed: list<string>, missing: list<string>, profile_incomplete: bool, docs_incomplete: bool}
     */
    private function requirementSummary(Customer $customer, ?LoanProduct $product, LoanApplicationDraft $draft): array
    {
        $completed = [];
        $missing = [];

        $profileSections = [
            'personal'  => __('borrower.loan_profile.sections.personal'),
            'activity'  => __('borrower.loan_profile.sections.employment'),
            'kin'       => __('borrower.loan_profile.sections.kin'),
            'residence' => __('borrower.loan_profile.sections.residence'),
        ];

        $calculated = collect($this->profileCompletion->calculate($customer)['sections'])->keyBy('key');
        foreach (['personal', 'activity', 'residence'] as $key) {
            $label = $profileSections[$key];
            if ((bool) ($calculated[$key]['complete'] ?? false)) {
                $completed[] = $label;
            } else {
                $missing[] = $label;
            }
        }

        if ($this->profileValidation->isKinComplete($customer)) {
            $completed[] = $profileSections['kin'];
        } else {
            $missing[] = $profileSections['kin'];
        }

        if ($this->incomeProof->satisfiesRequirement($customer)) {
            $completed[] = __('borrower.loan_profile.sections.proof_of_income');
        } else {
            $missing[] = __('borrower.loan_profile.sections.proof_of_income');
        }

        if ($this->profileValidation->requiresResidenceLetter()) {
            if ($this->profileValidation->hasDocument($customer, 'residence_letter')) {
                $completed[] = __('borrower.profile.residence_letter');
            } else {
                $missing[] = __('borrower.profile.residence_letter');
            }
        }

        if ($product) {
            $wizardSteps = collect($this->wizard->borrowerStepPlan($customer, $product))
                ->reject(fn (array $step) => $step['key'] === 'product')
                ->values();

            $payload = $draft->payload ?? [];
            $stepKey = $payload['step_key'] ?? null;
            $currentIndex = $this->resolveWizardStepIndex($wizardSteps, $stepKey, (int) $draft->step);

            if ($draft->phase === 'application' || ! empty($payload['application_started'])) {
                foreach ($wizardSteps as $index => $step) {
                    $label = (string) $step['label'];
                    if ($index < $currentIndex) {
                        $completed[] = $label;
                    } elseif (! in_array($label, $completed, true) && ! in_array($label, $missing, true)) {
                        $missing[] = $label;
                    }
                }
            }
        }

        $completed = array_values(array_unique($completed));
        $missing = array_values(array_diff(array_unique($missing), $completed));

        return [
            'completed'          => $completed,
            'missing'            => $missing,
            'profile_incomplete' => ! empty(array_intersect($missing, array_values($profileSections))),
            'docs_incomplete'    => in_array(__('borrower.loan_profile.sections.proof_of_income'), $missing, true)
                || in_array(__('borrower.profile.residence_letter'), $missing, true),
        ];
    }

    /**
     * @return list<array{key: string, label: string, upload_url: string, complete: bool}>
     */
    private function missingRequirementsWithUpload(Customer $customer, string $returnUrl): array
    {
        $items = [];

        if (! $this->incomeProof->satisfiesRequirement($customer)) {
            if (! $this->incomeProof->hasPrimaryProof($customer)) {
                $items[] = [
                    'key'        => 'bank_statement',
                    'label'      => __('borrower.profile.income_bank_statement'),
                    'upload_url' => $this->profileUrl('kyc', $returnUrl),
                    'complete'   => false,
                ];
                $items[] = [
                    'key'        => 'mobile_money_statement',
                    'label'      => __('borrower.profile.income_mobile_money_statement'),
                    'upload_url' => $this->profileUrl('kyc', $returnUrl),
                    'complete'   => false,
                ];
            }
        }

        if ($this->profileValidation->requiresResidenceLetter()
            && ! $this->profileValidation->hasDocument($customer, 'residence_letter')) {
            $items[] = [
                'key'        => 'residence_letter',
                'label'      => __('borrower.profile.residence_letter'),
                'upload_url' => $this->profileUrl('residence', $returnUrl),
                'complete'   => false,
            ];
        }

        $profileSections = collect($this->profileCompletion->displaySections($customer, false));
        foreach ($profileSections as $section) {
            if (($section['status'] ?? '') === 'complete') {
                continue;
            }

            if (in_array($section['key'], ['documents', 'face', 'identity'], true)) {
                continue;
            }

            $items[] = [
                'key'        => $section['key'],
                'label'      => $section['label'],
                'upload_url' => $this->appendReturn($section['action_url'] ?? route('site.borrower.profile'), $returnUrl),
                'complete'   => false,
            ];
        }

        return collect($items)
            ->unique('key')
            ->filter(fn (array $item) => ! ($item['complete'] ?? false))
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\LoanProductRequirement>  $requirements
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, CustomerDocument>>  $uploads
     * @return list<array{key: string, label: string, upload_url: string, complete: bool}>
     */
    private function submittedMissingRequirements(
        LoanApplication $application,
        \Illuminate\Support\Collection $requirements,
        \Illuminate\Support\Collection $uploads,
    ): array {
        $items = [];

        foreach ($requirements->where('is_required', true) as $requirement) {
            $myUploads = $uploads->get($requirement->id, collect());
            $satisfied = $myUploads->contains(fn (CustomerDocument $doc) => in_array($doc->status, ['verified', 'approved', 'pending_review', 'pending'], true));

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

    /**
     * @param  array{completed: list<string>, missing: list<string>, profile_incomplete: bool, docs_incomplete: bool}  $requirementSummary
     * @param  array{phase: string, step_key: string|null, step: int, reason: string|null}  $resumeTarget
     * @return list<array{label: string, url: string, tone: string}>
     */
    private function draftActions(
        Customer $customer,
        LoanApplicationDraft $draft,
        ?LoanProduct $product,
        array $resumeTarget,
        array $requirementSummary,
    ): array {
        $actions = [];
        $profileUrl = route('site.borrower.loan-profile.draft', $draft);
        $wizardUrl = $this->drafts->wizardApplyUrl($draft, $resumeTarget);

        if ($requirementSummary['profile_incomplete']) {
            $firstProfile = collect($this->profileCompletion->displaySections($customer))
                ->first(fn (array $section) => ($section['status'] ?? '') !== 'complete');

            $actions[] = [
                'label' => __('borrower.loan_profile.actions.complete_profile'),
                'url'   => $this->appendReturn($firstProfile['action_url'] ?? route('site.borrower.profile'), $profileUrl),
                'tone'  => 'primary',
            ];
        }

        if ($requirementSummary['docs_incomplete']) {
            $firstDoc = collect($this->missingRequirementsWithUpload($customer, $profileUrl))->first();
            $actions[] = [
                'label' => __('borrower.loan_profile.actions.upload_documents'),
                'url'   => $firstDoc['upload_url'] ?? $this->profileUrl('kyc', $profileUrl),
                'tone'  => 'secondary',
            ];
        }

        if (! $requirementSummary['profile_incomplete']) {
            $actions[] = [
                'label' => __('borrower.loan_profile.actions.continue_application'),
                'url'   => $wizardUrl,
                'tone'  => 'primary',
            ];
        }

        $checklist = $this->requirements->checklist($customer);
        if ($checklist['can_apply'] && ($resumeTarget['phase'] ?? '') === 'application') {
            $actions[] = [
                'label' => __('borrower.loan_profile.actions.review_application'),
                'url'   => $wizardUrl.'&step_key=review',
                'tone'  => 'secondary',
            ];
        }

        return $actions;
    }

    /** @return list<array{label: string, url: string, tone: string}> */
    private function submittedActions(LoanApplication $application, ?\App\Models\LoanAgreement $offer): array
    {
        $actions = [];
        $status = (string) $application->status;

        if (in_array($status, ['pending_documents', 'submitted', 'pending', 'under_review', 'awaiting_guarantor'], true)) {
            $actions[] = [
                'label' => __('borrower.loan_profile.actions.upload_documents'),
                'url'   => route('site.borrower.application', $application->id).'#documents',
                'tone'  => 'primary',
            ];
        }

        if ($offer && ! $offer->isSigned()) {
            $actions[] = [
                'label' => __('borrower.application.review_sign'),
                'url'   => route('site.borrower.application.agreement', $application->id),
                'tone'  => 'primary',
            ];
        }

        if ($status === 'disbursed') {
            $loan = Loan::query()->where('loan_application_id', $application->id)->first();
            if ($loan) {
                $actions[] = [
                    'label' => __('borrower.loan_profile.actions.view_schedule'),
                    'url'   => route('site.borrower.schedule', $loan->id),
                    'tone'  => 'secondary',
                ];
            }
        }

        if ($actions === []) {
            $actions[] = [
                'label' => __('borrower.applications_list.view'),
                'url'   => route('site.borrower.application', $application->id),
                'tone'  => 'secondary',
            ];
        }

        return $actions;
    }

    private function statusDetail(LoanApplication $application): ?string
    {
        return match ((string) $application->status) {
            'rejected'          => $application->rejection_reason ?? __('borrower.applications_list.rejected_default'),
            'pending_documents' => __('borrower.applications_list.documents_required'),
            default             => null,
        };
    }

    private function profileUrl(string $section, string $returnUrl): string
    {
        return $this->appendReturn(route('site.borrower.profile', ['section' => $section]), $returnUrl);
    }

    private function appendReturn(string $url, string $returnUrl): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'return='.urlencode($returnUrl);
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

    /** @param  \Illuminate\Support\Collection<int, array{key: string, label: string}>  $wizardSteps */
    private function resolveWizardStepIndex(\Illuminate\Support\Collection $wizardSteps, ?string $stepKey, int $fallbackIndex): int
    {
        if ($stepKey) {
            $byKey = $wizardSteps->search(fn (array $step) => $step['key'] === $stepKey);
            if ($byKey !== false) {
                return (int) $byKey;
            }
        }

        return max(0, min($fallbackIndex, max(0, $wizardSteps->count() - 1)));
    }
}
