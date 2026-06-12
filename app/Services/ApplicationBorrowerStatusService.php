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
     * @return array{percent: int, steps: list<array{key: string, label: string, complete: bool, current: bool}>}
     */
    public function timeline(LoanApplication $application): array
    {
        $application->loadMissing(['documentRequests', 'loan']);

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
            ['key' => 'approved', 'label' => __('borrower.applications_list.pipeline.approved'), 'complete' => false, 'current' => false],
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
            'approved', 'disbursed', 'closed' => 'emerald',
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
