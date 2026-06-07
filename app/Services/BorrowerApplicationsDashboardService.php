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
    ) {}

    /**
     * Unified borrower applications list: in-progress drafts plus submitted applications.
     *
     * @return list<array<string, mixed>>
     */
    public function applicationsForCustomer(Customer $customer): array
    {
        $items = [];

        foreach ($this->drafts->listForCustomer($customer) as $draft) {
            $items[] = $this->formatDraft($customer, $draft);
        }

        $submitted = LoanApplication::query()
            ->with('product')
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
        $progress = $this->draftProgress($customer, $draft, $product);
        $fee = ($draft->payload ?? [])['application_fee'] ?? null;
        $feePending = $product && quoted_application_fee($customer, $product) > 0
            && ! app(ApplicationFeePaymentService::class)->isFeeSatisfied($fee, quoted_application_fee($customer, $product));

        return [
            'is_draft'           => true,
            'id'                 => 'draft-'.$draft->id,
            'loan_type'          => $this->loanTypeLabel($product),
            'application_number' => __('borrower.applications_list.draft_reference'),
            'product_name'       => $product?->name ?? __('borrower.apply.title'),
            'requested_amount'   => $this->drafts->requestedAmount($draft),
            'requested_tenure_months' => (int) (($draft->payload ?? [])['form']['requested_tenure_months'] ?? 0),
            'status'             => 'draft',
            'status_label'       => $this->borrowerStatusLabel('draft'),
            'status_tone'        => 'gray',
            'progress_percent'   => $progress['percent'],
            'progress_steps'     => $progress['steps'],
            'created_at'         => $draft->created_at,
            'updated_at'         => $draft->saved_at ?? $draft->updated_at,
            'sort_at'            => ($draft->saved_at ?? $draft->updated_at)?->timestamp ?? 0,
            'detail'             => $feePending
                ? __('borrower.applications_list.draft_fee_pending')
                : __('borrower.applications_list.draft_in_progress'),
            'action_url'         => $this->drafts->resumeUrl($draft),
            'action_label'       => __('borrower.applications_list.resume'),
            'saved_at_human'     => optional($draft->saved_at)->diffForHumans(),
        ];
    }

    /** @return array<string, mixed> */
    public function formatSubmitted(LoanApplication $application): array
    {
        $progress = $this->submittedProgress($application);
        $status = (string) $application->status;
        $needsDocuments = $status === 'pending_documents';

        return [
            'is_draft'           => false,
            'id'                 => $application->id,
            'loan_type'          => $this->loanTypeLabel($application->product),
            'application_number' => $application->application_number,
            'product_name'       => $application->product->name ?? '—',
            'requested_amount'   => (float) $application->requested_amount,
            'requested_tenure_months' => (int) $application->requested_tenure_months,
            'status'             => $status,
            'status_label'       => $this->borrowerStatusLabel($status, $application->current_stage),
            'status_tone'        => $this->statusTone($status),
            'progress_percent'   => $progress['percent'],
            'progress_steps'     => $progress['steps'],
            'created_at'         => $application->created_at,
            'updated_at'         => $application->updated_at,
            'sort_at'            => ($application->submitted_at ?? $application->updated_at)?->timestamp ?? 0,
            'detail'             => $status === 'rejected'
                ? ($application->rejection_reason ?? __('borrower.applications_list.rejected_default'))
                : ($needsDocuments ? __('borrower.applications_list.documents_required') : null),
            'action_url'         => route('site.borrower.application', $application->id),
            'action_label'       => $needsDocuments || in_array($status, ['submitted', 'pending', 'pending_documents', 'under_review', 'awaiting_guarantor'], true)
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

        $assessment = $this->readiness->assess($customer, $product);
        $milestones = [];

        foreach ($assessment['requirements'] as $requirement) {
            if (! empty($requirement['application_step'])) {
                continue;
            }
            $milestones[] = [
                'label'    => (string) $requirement['label'],
                'complete' => (bool) $requirement['complete'],
            ];
        }

        $wizardSteps = collect($this->wizard->borrowerStepPlan($customer, $product))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values();

        $payload = $draft->payload ?? [];
        $stepKey = $payload['step_key'] ?? null;
        $savedStep = (int) $draft->step;
        $currentWizardIndex = $this->resolveWizardStepIndex($wizardSteps, $stepKey, $savedStep);

        if ($draft->phase === 'application' || ! empty($payload['application_started'])) {
            foreach ($wizardSteps as $index => $step) {
                $milestones[] = [
                    'label'    => (string) $step['label'],
                    'complete' => $index < $currentWizardIndex,
                ];
            }
        } else {
            foreach ($wizardSteps as $step) {
                $milestones[] = [
                    'label'    => (string) $step['label'],
                    'complete' => false,
                ];
            }
        }

        $completed = collect($milestones)->where('complete', true)->count();
        $total = max(1, $milestones->count());

        return [
            'percent' => (int) round(($completed / $total) * 100),
            'steps'   => $milestones,
        ];
    }

    /**
     * @return array{percent: int, steps: list<array{label: string, complete: bool, active?: bool}>}
     */
    public function submittedProgress(LoanApplication $application): array
    {
        $status = (string) $application->status;

        if ($status === 'rejected') {
            return ['percent' => 0, 'steps' => []];
        }

        if ($status === 'disbursed') {
            return [
                'percent' => 100,
                'steps'   => [['label' => display_label('disbursed', 'application_status'), 'complete' => true]],
            ];
        }

        $stage = (string) ($application->current_stage ?? $status);
        $pipeline = [
            'submitted'          => __('borrower.applications_list.pipeline.submitted'),
            'awaiting_guarantor' => __('borrower.applications_list.pipeline.awaiting_guarantor'),
            'screening'          => __('borrower.applications_list.pipeline.screening'),
            'credit_appraisal'   => __('borrower.applications_list.pipeline.underwriting'),
            'pre_approval'       => __('borrower.applications_list.pipeline.pre_approval'),
            'approval'           => __('borrower.applications_list.pipeline.approval'),
            'disbursement'       => __('borrower.applications_list.pipeline.disbursement'),
        ];

        $stageOrder = array_keys($pipeline);
        $currentIndex = array_search($stage, $stageOrder, true);

        if ($currentIndex === false) {
            $currentIndex = match ($status) {
                'under_review'   => array_search('credit_appraisal', $stageOrder, true),
                'pre_approved' => array_search('pre_approval', $stageOrder, true),
                'approved'     => array_search('approval', $stageOrder, true),
                'pending_documents' => array_search('screening', $stageOrder, true),
                default        => 0,
            };
        }

        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $steps = [];
        foreach ($pipeline as $key => $label) {
            $index = array_search($key, $stageOrder, true);
            $steps[] = [
                'label'    => $label,
                'complete' => $index !== false && $index < $currentIndex,
                'active'   => $index === $currentIndex,
            ];
        }

        $percent = (int) round((($currentIndex + 1) / max(1, count($pipeline))) * 100);

        return ['percent' => min(100, $percent), 'steps' => $steps];
    }

    public function borrowerStatusLabel(string $status, ?string $stage = null): string
    {
        return match ($status) {
            'draft'              => __('borrower.applications_list.statuses.draft'),
            'submitted', 'pending' => __('borrower.applications_list.statuses.submitted'),
            'awaiting_guarantor' => __('borrower.applications_list.statuses.awaiting_guarantor'),
            'pending_documents'  => __('borrower.applications_list.statuses.pending_documents'),
            'under_review'       => __('borrower.applications_list.statuses.underwriting'),
            'pre_approved'       => __('borrower.applications_list.statuses.pre_approved'),
            'approved'           => __('borrower.applications_list.statuses.approved'),
            'rejected'           => __('borrower.applications_list.statuses.rejected'),
            'disbursed'          => __('borrower.applications_list.statuses.disbursed'),
            'cancelled'          => display_label('cancelled', 'application_status'),
            default              => display_label($stage ?: $status, 'application_status'),
        };
    }

    public function statusTone(string $status): string
    {
        return match ($status) {
            'rejected' => 'red',
            'approved', 'disbursed' => 'emerald',
            'submitted', 'pending', 'draft' => 'amber',
            'pending_documents' => 'orange',
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
