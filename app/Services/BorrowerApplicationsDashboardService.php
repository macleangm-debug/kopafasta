<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use Illuminate\Support\Collection;

class BorrowerApplicationsDashboardService
{
    public function __construct(
        private readonly LoanApplicationDraftService $drafts,
        private readonly SmartLoanApplicationWizardService $wizard,
        private readonly LoanProductReadinessService $readiness,
        private readonly ApplicationBorrowerStatusService $borrowerStatus,
    ) {}

    /**
     * Unified borrower applications list: in-progress drafts plus submitted applications.
     *
     * @return list<array<string, mixed>>
     */
    public function applicationsForCustomer(Customer $customer): array
    {
        $items = [];

        $submittedReferences = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->pluck('application_number');

        foreach ($this->drafts->listForCustomer($customer) as $draft) {
            if ($draft->draft_reference && $submittedReferences->contains($draft->draft_reference)) {
                continue;
            }

            $items[] = $this->formatDraft($customer, $draft);
        }

        $submitted = LoanApplication::query()
            ->with(['product', 'documentRequests', 'loan'])
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft'])
            ->latest()
            ->get();

        foreach ($submitted as $application) {
            $items[] = $this->formatSubmitted($application);
        }

        return collect($items)
            ->sortByDesc(fn (array $row) => $row['sort_at'])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function resumableDrafts(Customer $customer): array
    {
        return collect($this->applicationsForCustomer($customer))
            ->where('is_draft', true)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function formatDraft(Customer $customer, LoanApplicationDraft $draft): array
    {
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);
        $profileProgress = $this->draftProgress($customer, $draft, $product);
        $applicationProgress = app(ApplicationProgressService::class)
            ->applicationDraftProgress($customer, $draft, $product);
        $fee = ($draft->payload ?? [])['application_fee'] ?? null;
        $feePending = $product && quoted_application_fee($customer, $product) > 0
            && ! app(ApplicationFeePaymentService::class)->isFeeSatisfied($fee, quoted_application_fee($customer, $product));

        $resumeTarget = $this->drafts->resumeTarget($customer, $draft);

        return [
            'is_draft'           => true,
            'id'                 => 'draft-'.$draft->id,
            'loan_type'          => $this->loanTypeLabel($product),
            'application_number' => $draft->draft_reference ?: __('borrower.applications_list.draft_reference'),
            'product_name'       => $product?->name ?? __('borrower.apply.title'),
            'requested_amount'   => $this->drafts->requestedAmount($draft),
            'requested_tenure_months' => (int) (($draft->payload ?? [])['form']['requested_tenure_months'] ?? 0),
            'status'             => 'draft',
            'status_label'       => $this->borrowerStatus->forDraft($draft)['label'],
            'status_tone'        => 'gray',
            'profile_percent'    => $profileProgress['percent'],
            'profile_complete'   => $profileProgress['percent'] >= 100,
            'application_percent'=> $applicationProgress['percent'],
            'application_status' => $applicationProgress['label'],
            'progress_percent'   => $applicationProgress['percent'],
            'progress_steps'     => $applicationProgress['steps'] ?: $profileProgress['steps'],
            'current_step'       => $applicationProgress['label'],
            'created_at'         => $draft->created_at,
            'updated_at'         => $draft->saved_at ?? $draft->updated_at,
            'sort_at'            => ($draft->saved_at ?? $draft->updated_at)?->timestamp ?? 0,
            'detail'             => $feePending
                ? __('borrower.applications_list.draft_fee_pending')
                : __('borrower.applications_list.draft_in_progress'),
            'action_url'         => $this->drafts->resumeUrl($customer, $draft),
            'action_label'       => __('borrower.applications_list.open'),
            'saved_at_human'     => optional($draft->saved_at)->diffForHumans(),
        ];
    }

    /** @return array<string, mixed> */
    public function formatSubmitted(LoanApplication $application): array
    {
        $pipelineProgress = $this->submittedProgress($application);
        $profileProgress = app(ApplicationProgressService::class)
            ->profileProgress($application->customer, $application->product);
        $borrowerStatus = $this->borrowerStatus->forApplication($application);
        $statusCode = $borrowerStatus['code'];
        $needsDocuments = in_array($statusCode, ['documents_requested', 'documents_resubmitted'], true);

        return [
            'is_draft'           => false,
            'id'                 => $application->id,
            'loan_type'          => $this->loanTypeLabel($application->product),
            'application_number' => $application->application_number,
            'product_name'       => $application->product->name ?? '—',
            'requested_amount'   => (float) $application->requested_amount,
            'requested_tenure_months' => (int) $application->requested_tenure_months,
            'status'             => $statusCode,
            'status_label'       => $borrowerStatus['label'],
            'status_tone'        => $borrowerStatus['tone'],
            'profile_percent'    => $profileProgress['percent'],
            'profile_complete'   => $profileProgress['percent'] >= 100,
            'application_percent'=> $pipelineProgress['percent'],
            'application_status' => $borrowerStatus['label'],
            'progress_percent'   => $pipelineProgress['percent'],
            'progress_steps'     => $pipelineProgress['steps'],
            'created_at'         => $application->created_at,
            'updated_at'         => $application->updated_at,
            'last_updated_human' => optional($application->updated_at)->diffForHumans(),
            'sort_at'            => ($application->submitted_at ?? $application->updated_at)?->timestamp ?? 0,
            'detail'             => $this->borrowerStatus->borrowerDetail($application),
            'action_url'         => route('site.borrower.application', $application->id),
            'action_label'       => $needsDocuments || in_array($statusCode, ['submitted', 'screening', 'credit_review', 'documents_requested', 'documents_resubmitted'], true)
                ? __('borrower.applications_list.view')
                : __('borrower.applications_list.open'),
            'receipt_url'        => route('site.apply.success', $application->id),
        ];
    }

    /**
     * @return array{percent: int, steps: list<array{label: string, complete: bool}>}
     */
    public function draftProgress(Customer $customer, LoanApplicationDraft $draft, ?LoanProduct $product): array
    {
        if (! $product) {
            return ['percent' => 0, 'steps' => []];
        }

        return app(ApplicationProgressService::class)->draftProgress($customer, $draft, $product);
    }

    /**
     * @return array{percent: int, steps: list<array{label: string, complete: bool, active?: bool}>}
     */
    public function submittedProgress(LoanApplication $application): array
    {
        return $this->borrowerStatus->timeline($application);
    }

    public function borrowerStatusLabel(string $status, ?string $stage = null): string
    {
        if ($status === 'draft') {
            return __('borrower.applications_list.statuses.draft');
        }

        return match (true) {
            $status === 'rejected' => __('borrower.applications_list.statuses.rejected'),
            $status === 'disbursed' => __('borrower.applications_list.statuses.disbursed'),
            $stage === 'screening' => __('borrower.applications_list.statuses.screening'),
            $stage === 'credit_appraisal' || $status === 'under_review' => __('borrower.applications_list.statuses.credit_review'),
            in_array($stage, ['approval', 'disbursement', 'pre_approval'], true)
                || in_array($status, ['approved', 'pre_approved'], true) => __('borrower.applications_list.statuses.approved'),
            $status === 'pending_documents' => __('borrower.applications_list.statuses.documents_requested'),
            in_array($status, ['submitted', 'pending'], true) => __('borrower.applications_list.statuses.submitted'),
            default => display_label($stage ?: $status, 'application_status'),
        };
    }

    public function statusTone(string $status): string
    {
        return match ($status) {
            'rejected' => 'red',
            'approved', 'disbursed', 'closed' => 'emerald',
            'draft', 'submitted' => 'amber',
            'documents_requested', 'documents_resubmitted' => 'orange',
            default => 'sky',
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

    /**
     * @param  array{phase: string, step_key: string|null, step: int, reason: string|null}  $resumeTarget
     * @param  array{percent: int, steps: list<array{label: string, complete: bool}>}  $progress
     */
    private function draftCurrentStepLabel(
        Customer $customer,
        LoanApplicationDraft $draft,
        ?LoanProduct $product,
        array $resumeTarget,
        array $progress,
    ): string {
        if (($resumeTarget['reason'] ?? null) === 'profile_incomplete') {
            return __('borrower.applications_list.profile_completion');
        }

        if (! $product) {
            return __('borrower.applications_list.draft_in_progress');
        }

        $wizardSteps = collect($this->wizard->borrowerStepPlan($customer, $product))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values();

        $stepKey = $resumeTarget['step_key'] ?? (($draft->payload ?? [])['step_key'] ?? null);
        $stepIndex = $this->resolveWizardStepIndex($wizardSteps, $stepKey, (int) ($resumeTarget['step'] ?? $draft->step));

        if (($resumeTarget['phase'] ?? '') === 'application' && $wizardSteps->has($stepIndex)) {
            return (string) $wizardSteps[$stepIndex]['label'];
        }

        foreach ($progress['steps'] as $step) {
            if (! ($step['complete'] ?? false)) {
                return (string) $step['label'];
            }
        }

        return __('borrower.applications_list.draft_in_progress');
    }

    /** @param  Collection<int, array{key: string, label: string}>  $wizardSteps */
    private function resolveWizardStepIndex(Collection $wizardSteps, ?string $stepKey, int $fallbackIndex): int
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
