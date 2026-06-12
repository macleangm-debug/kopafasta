<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use Illuminate\Support\Collection;

class ApplicationBorrowerStatusService
{
    public function __construct(
        private readonly LoanRejectionReasonService $rejectionReasons,
    ) {}

    /** @return array{code: string, label: string, tone: string} */
    public function forApplication(LoanApplication $application): array
    {
        $application->loadMissing(['documentRequests', 'loan']);

        $code = $this->resolveCode($application);
        $tone = $this->toneForCode($code);

        return [
            'code'  => $code,
            'label' => $this->labelForCode($code, $application),
            'tone'  => $tone,
        ];
    }

    /** @return array{code: string, label: string, tone: string} */
    public function forDraft(LoanApplicationDraft $draft): array
    {
        return [
            'code'  => 'draft',
            'label' => __('borrower.applications_list.statuses.draft'),
            'tone'  => 'gray',
        ];
    }

    public function borrowerDetail(LoanApplication $application): ?string
    {
        if ($this->resolveCode($application) === 'rejected') {
            $label = $this->rejectionReasons->labelForCode($application->rejection_reason_code)
                ?: $application->rejection_reason
                ?: __('borrower.applications_list.rejected_default');

            return __('borrower.loan_profile.rejection_reason', ['reason' => $label]);
        }

        if (in_array($this->resolveCode($application), ['documents_requested', 'documents_resubmitted'], true)) {
            $pending = $application->documentRequests
                ->whereIn('status', ['pending', 'rejected'])
                ->pluck('label')
                ->filter()
                ->values();

            if ($pending->isNotEmpty()) {
                return __('borrower.loan_profile.underwriter_feedback', [
                    'items' => $pending->implode(', '),
                ]);
            }
        }

        return null;
    }

    /**
     * @return array{percent: int, steps: list<array{key: string, label: string, complete: bool, current: bool}>, is_loan_progress?: bool}
     */
    public function timeline(LoanApplication $application): array
    {
        $application->loadMissing(['documentRequests', 'loan']);

        if ($this->isPostApprovalPhase($application)) {
            return $this->postApprovalTimeline($application);
        }

        if ((string) $application->status === 'rejected') {
            return ['percent' => 0, 'steps' => []];
        }

        $stage = (string) ($application->current_stage ?? 'submitted');
        $requests = $application->documentRequests ?? collect();

        $hasOpenRequests = $requests->whereIn('status', ['pending', 'rejected'])->isNotEmpty();
        $hasUploadedRequests = $requests->where('status', 'uploaded')->isNotEmpty();
        $documentsComplete = ! $hasOpenRequests && ($requests->isEmpty() || $requests->every(fn ($r) => in_array($r->status, ['satisfied', 'uploaded'], true)));

        $steps = [
            ['key' => 'submitted', 'label' => __('borrower.applications_list.pipeline.submitted'), 'complete' => false, 'current' => false],
            ['key' => 'screening', 'label' => __('borrower.applications_list.pipeline.screening'), 'complete' => false, 'current' => false],
            ['key' => 'documents_submitted', 'label' => __('borrower.applications_list.pipeline.documents_submitted'), 'complete' => false, 'current' => false],
            ['key' => 'credit_review', 'label' => __('borrower.applications_list.pipeline.credit_review'), 'complete' => false, 'current' => false],
            ['key' => 'approved', 'label' => __('borrower.applications_list.pipeline.approval'), 'complete' => false, 'current' => false],
            ['key' => 'disbursed', 'label' => __('borrower.applications_list.pipeline.disbursed'), 'complete' => false, 'current' => false],
        ];

        $stageOrder = ['submitted', 'screening', 'credit_appraisal', 'pre_approval', 'approval', 'disbursement'];
        $stageIndex = array_search($stage, $stageOrder, true);
        if ($stageIndex === false) {
            $stageIndex = match ((string) $application->status) {
                'under_review' => 2,
                'pre_approved' => 3,
                'approved'     => 4,
                'disbursed'    => 5,
                default        => 0,
            };
        }

        foreach ($steps as $i => &$step) {
            match ($step['key']) {
                'submitted' => $step['complete'] = $stageIndex >= 0 || filled($application->submitted_at),
                'screening' => $step['complete'] = $stageIndex >= 1,
                'documents_submitted' => $step['complete'] = $documentsComplete || $hasUploadedRequests || $stageIndex >= 2,
                'credit_review' => $step['complete'] = $stageIndex >= 2,
                'approved' => $step['complete'] = in_array($stage, ['approval', 'disbursement'], true)
                    || in_array((string) $application->status, ['approved', 'pre_approved', 'disbursed'], true),
                'disbursed' => $step['complete'] = $this->isDisbursed($application),
                default => null,
            };
        }
        unset($step);

        $currentKey = match (true) {
            $this->isClosed($application) => 'disbursed',
            $this->isDisbursed($application) => 'disbursed',
            in_array((string) $application->status, ['approved', 'pre_approved'], true) || in_array($stage, ['approval', 'disbursement'], true) => 'approved',
            $stage === 'credit_appraisal' || (string) $application->status === 'under_review' => 'credit_review',
            $hasUploadedRequests => 'documents_submitted',
            $hasOpenRequests || (string) $application->status === 'pending_documents' => 'documents_submitted',
            $stage === 'screening' => 'screening',
            default => 'submitted',
        };

        foreach ($steps as &$step) {
            $step['current'] = $step['key'] === $currentKey && ! $step['complete'];
            if ($step['key'] === $currentKey && ! $this->isDisbursed($application) && ! $this->isClosed($application)) {
                if (! $step['complete']) {
                    $step['current'] = true;
                }
            }
        }
        unset($step);

        if ($hasOpenRequests && ! $hasUploadedRequests) {
            $steps[1]['current'] = false;
            $steps[2]['current'] = false;
            foreach ($steps as &$step) {
                $step['current'] = false;
            }
            unset($step);
            $steps[1]['current'] = (string) $application->status === 'pending_documents';
        }

        if ((string) $application->status === 'pending_documents') {
            foreach ($steps as &$step) {
                $step['current'] = false;
            }
            unset($step);
            $idx = $hasUploadedRequests ? 2 : 1;
            if (! ($steps[$idx]['complete'] ?? false)) {
                $steps[$idx]['current'] = true;
            }
        }

        $completedCount = collect($steps)->where('complete', true)->count();
        $currentIndex = collect($steps)->search(fn (array $s) => $s['current'] ?? false);
        $percent = (int) round((($completedCount + ($currentIndex !== false ? 0.5 : 0)) / max(1, count($steps))) * 100);

        return ['percent' => min(100, $percent), 'steps' => $steps];
    }

    /**
     * @return array{percent: int, steps: list<array{key: string, label: string, complete: bool, current: bool}>, is_loan_progress: bool}
     */
    public function postApprovalTimeline(LoanApplication $application): array
    {
        $readiness = app(ApplicationDisbursementReadinessService::class);
        $hasFees = $readiness->hasPostApprovalFees($application);
        $feesPaid = $readiness->feesPaid($application);
        $contractSigned = $readiness->contractSigned($application);
        $disbursed = $this->isDisbursed($application);
        $activeLoan = $disbursed && ! $this->isClosed($application);

        $steps = [
            ['key' => 'submitted', 'label' => __('borrower.loan_progress.submitted'), 'complete' => true, 'current' => false],
            ['key' => 'screening', 'label' => __('borrower.loan_progress.screening'), 'complete' => true, 'current' => false],
            ['key' => 'credit_review', 'label' => __('borrower.loan_progress.credit_review'), 'complete' => true, 'current' => false],
            ['key' => 'approval', 'label' => __('borrower.loan_progress.approval'), 'complete' => true, 'current' => false],
            [
                'key'      => 'post_approval_fee',
                'label'    => __('borrower.loan_progress.post_approval_fee'),
                'complete' => ! $hasFees || $feesPaid,
                'current'  => false,
            ],
            [
                'key'      => 'contract',
                'label'    => __('borrower.loan_progress.contract'),
                'complete' => $contractSigned,
                'current'  => false,
            ],
            [
                'key'      => 'disbursement',
                'label'    => __('borrower.loan_progress.disbursement'),
                'complete' => $disbursed,
                'current'  => false,
            ],
            [
                'key'      => 'active_loan',
                'label'    => __('borrower.loan_progress.active_loan'),
                'complete' => $activeLoan,
                'current'  => false,
            ],
        ];

        $currentKey = match (true) {
            $activeLoan => 'active_loan',
            $disbursed => 'active_loan',
            $readiness->isReadyForDisbursement($application) => 'disbursement',
            $readiness->needsContractSignature($application) => 'contract',
            $readiness->needsDisbursementDetailsConfirmation($application) => 'contract',
            $readiness->needsPostApprovalFees($application) => 'post_approval_fee',
            default => 'post_approval_fee',
        };

        foreach ($steps as &$step) {
            if (($step['complete'] ?? false) || $step['key'] === 'active_loan') {
                $step['current'] = false;

                continue;
            }

            $step['current'] = $step['key'] === $currentKey;
        }
        unset($step);

        if ($activeLoan) {
            foreach ($steps as &$step) {
                $step['current'] = $step['key'] === 'active_loan' && ! ($step['complete'] ?? false);
            }
            unset($step);
        }

        $completedCount = collect($steps)->where('complete', true)->count();
        $currentIndex = collect($steps)->search(fn (array $s) => $s['current'] ?? false);
        $percent = (int) round((($completedCount + ($currentIndex !== false ? 0.5 : 0)) / max(1, count($steps))) * 100);

        return [
            'percent'           => min(100, $percent),
            'steps'             => $steps,
            'is_loan_progress'  => true,
        ];
    }

    private function isPostApprovalPhase(LoanApplication $application): bool
    {
        if ($this->isDisbursed($application) || $this->isClosed($application)) {
            return true;
        }

        return in_array((string) $application->status, ['approved', 'pre_approved', 'disbursed'], true)
            || in_array((string) ($application->current_stage ?? ''), ['approval', 'disbursement'], true);
    }

    /** @return array{pending: Collection, uploaded: Collection, completed: Collection, rejected: Collection} */
    public function groupedDocumentRequests(Collection $requests): array
    {
        return [
            'pending'   => $requests->where('status', 'pending')->values(),
            'uploaded'  => $requests->where('status', 'uploaded')->values(),
            'completed' => $requests->where('status', 'satisfied')->values(),
            'rejected'  => $requests->where('status', 'rejected')->values(),
        ];
    }

    private function resolveCode(LoanApplication $application): string
    {
        $status = (string) $application->status;
        $stage = (string) ($application->current_stage ?? 'submitted');

        if ($status === 'rejected' || $stage === 'rejected') {
            return 'rejected';
        }

        if ($this->isClosed($application)) {
            return 'closed';
        }

        if ($this->isDisbursed($application)) {
            return 'disbursed';
        }

        if ($status === 'awaiting_offer' || $application->offer_status === 'pending_borrower') {
            return 'awaiting_offer';
        }

        if (in_array($status, ['approved', 'pre_approved'], true) || in_array($stage, ['approval', 'disbursement', 'pre_approval'], true)) {
            $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);

            if ($readiness->needsBorrowerSignature($application)) {
                return 'awaiting_signature';
            }

            if ($readiness->needsPostApprovalFees($application)) {
                return 'post_approval_fees';
            }

            if ($readiness->needsDisbursementDetailsConfirmation($application)) {
                return 'awaiting_disbursement_details';
            }

            if ($readiness->needsContractSignature($application)) {
                return 'awaiting_contract';
            }

            if ($readiness->isReadyForDisbursement($application)) {
                return 'ready_for_disbursement';
            }

            return 'approved';
        }

        $requests = $application->relationLoaded('documentRequests')
            ? $application->documentRequests
            : $application->documentRequests()->get();

        if ($requests->where('status', 'uploaded')->isNotEmpty()) {
            return 'documents_resubmitted';
        }

        if ($status === 'pending_documents' || $requests->whereIn('status', ['pending', 'rejected'])->isNotEmpty()) {
            return 'documents_requested';
        }

        if ($stage === 'credit_appraisal' || $status === 'under_review') {
            return 'credit_review';
        }

        if ($stage === 'screening') {
            return 'screening';
        }

        if (in_array($status, ['submitted', 'pending'], true) && $stage === 'submitted') {
            return 'submitted';
        }

        return 'submitted';
    }

    private function labelForCode(string $code, LoanApplication $application): string
    {
        return match ($code) {
            'draft'                 => __('borrower.applications_list.statuses.draft'),
            'submitted'             => __('borrower.applications_list.statuses.submitted'),
            'screening'             => __('borrower.applications_list.statuses.screening'),
            'documents_requested'   => __('borrower.applications_list.statuses.documents_requested'),
            'documents_resubmitted' => __('borrower.applications_list.statuses.documents_resubmitted'),
            'credit_review'         => __('borrower.applications_list.statuses.credit_review'),
            'awaiting_offer'        => __('borrower.applications_list.statuses.awaiting_offer'),
            'awaiting_signature'    => __('borrower.applications_list.statuses.awaiting_signature'),
            'post_approval_fees'    => __('borrower.applications_list.statuses.post_approval_fees'),
            'awaiting_disbursement_details' => __('borrower.applications_list.statuses.awaiting_disbursement_details'),
            'awaiting_contract'     => __('borrower.applications_list.statuses.awaiting_contract'),
            'ready_for_disbursement'=> __('borrower.applications_list.statuses.ready_for_disbursement'),
            'approved'              => __('borrower.applications_list.statuses.approved'),
            'rejected'              => __('borrower.applications_list.statuses.rejected'),
            'disbursed'             => __('borrower.applications_list.statuses.disbursed'),
            'closed'                => __('borrower.applications_list.statuses.closed'),
            default                 => ucfirst(str_replace('_', ' ', $code)),
        };
    }

    private function toneForCode(string $code): string
    {
        return match ($code) {
            'rejected' => 'red',
            'awaiting_offer' => 'amber',
            'awaiting_signature', 'post_approval_fees', 'awaiting_disbursement_details', 'awaiting_contract' => 'sky',
            'approved', 'ready_for_disbursement', 'disbursed', 'closed' => 'emerald',
            'draft', 'submitted' => 'amber',
            'documents_requested', 'documents_resubmitted' => 'orange',
            default => 'sky',
        };
    }

    private function isDisbursed(LoanApplication $application): bool
    {
        if ((string) $application->status === 'disbursed') {
            return true;
        }

        $loan = $application->relationLoaded('loan') ? $application->loan : $application->loan()->first();

        return $loan && in_array((string) $loan->status, ['active', 'disbursed'], true);
    }

    private function isClosed(LoanApplication $application): bool
    {
        $loan = $application->relationLoaded('loan') ? $application->loan : $application->loan()->first();

        return $loan && in_array((string) $loan->status, ['closed', 'settled', 'paid_off'], true);
    }
}
