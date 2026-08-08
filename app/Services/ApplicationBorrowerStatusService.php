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
        if ($this->resolveCode($application) === 'offer_declined') {
            return __('borrower.loan_profile.offer_declined_detail');
        }

        if ($this->resolveCode($application) === 'withdrawn') {
            return __('borrower.loan_profile.withdrawn_detail');
        }

        if ($this->resolveCode($application) === 'rejected') {
            $codes = $this->rejectionReasons->normalizeCodes(
                $application->rejection_reason_codes,
                $application->rejection_reason_code,
            );
            $custom = trim((string) ($application->rejection_reason ?? ''));
            $isCapacity = in_array(CapacityAutoRejectService::REASON_CODE, $codes, true)
                || data_get($application->screening_payload, 'capacity_auto_reject.status') === CapacityAutoRejectService::STATUS_FIRED;

            $label = ($isCapacity && $custom !== '')
                ? $custom
                : $this->rejectionReasons->formatReasonsForBorrower(
                    $application->rejection_reason_codes,
                    $application->rejection_reason_code,
                    $application->rejection_reason,
                );

            $detail = __('borrower.loan_profile.rejection_reason', ['reason' => $label]);
            $advice = $this->rejectionReasons->resolveBorrowerAdvice(
                $application->rejection_advice_code,
                $application->rejection_advice,
            );
            if ($advice) {
                $detail .= "\n".__('borrower.loan_profile.rejection_advice', ['advice' => $advice]);
            }

            return $detail;
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

            if ($this->resolveCode($application) === 'documents_resubmitted') {
                return __('borrower.loan_profile.documents_resubmitted_detail');
            }
        }

        $code = $this->resolveCode($application);
        if ($code === 'awaiting_valuation_fee') {
            $amount = (int) quoted_valuation_fee($application->customer);

            return __('borrower.collateral_secure.valuation_fee_next_action', [
                'amount' => format_money($amount),
            ]);
        }

        // Generic SLA copy is not shown as the status detail — next-action steps cover what to do.
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

        $steps = [
            ['key' => 'submitted', 'label' => __('borrower.applications_list.pipeline.submitted'), 'complete' => false, 'current' => false],
            ['key' => 'under_review', 'label' => __('borrower.applications_list.pipeline.under_review'), 'complete' => false, 'current' => false],
        ];

        if ($hasOpenRequests || $hasUploadedRequests || (string) $application->status === 'pending_documents') {
            $steps[] = ['key' => 'documents_requested', 'label' => __('borrower.applications_list.pipeline.documents_requested'), 'complete' => false, 'current' => false];
        }

        $steps[] = ['key' => 'approved', 'label' => __('borrower.applications_list.pipeline.approved'), 'complete' => false, 'current' => false];

        $isApproved = in_array((string) $application->status, ['approved', 'pre_approved', 'awaiting_offer'], true)
            || in_array($stage, ['approval', 'disbursement', 'pre_approval'], true)
            || $application->offer_status === 'pending_borrower';

        $steps[0]['complete'] = filled($application->submitted_at);

        $underReviewComplete = $isApproved
            || $hasOpenRequests
            || $hasUploadedRequests
            || in_array($stage, ['screening', 'credit_appraisal', 'pre_approval', 'approval', 'disbursement'], true)
            || in_array((string) $application->status, ['under_review', 'pending_documents', 'awaiting_offer', 'approved', 'pre_approved'], true);

        $steps[1]['complete'] = $underReviewComplete;

        foreach ($steps as $index => &$step) {
            if ($step['key'] === 'documents_requested') {
                $step['complete'] = $hasUploadedRequests && ! $hasOpenRequests;
            }
            if ($step['key'] === 'approved') {
                $step['complete'] = $isApproved;
            }
        }
        unset($step);

        $currentKey = match (true) {
            $isApproved => 'approved',
            $hasOpenRequests || (string) $application->status === 'pending_documents' => 'documents_requested',
            $hasUploadedRequests => 'documents_requested',
            $underReviewComplete => 'under_review',
            default => 'submitted',
        };

        if (! collect($steps)->contains(fn (array $s) => $s['key'] === $currentKey)) {
            $currentKey = $underReviewComplete ? 'under_review' : 'submitted';
        }

        foreach ($steps as &$step) {
            $step['current'] = $step['key'] === $currentKey && ! ($step['complete'] ?? false);
        }
        unset($step);

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
        $application->loadMissing('loan');
        $readiness = app(ApplicationDisbursementReadinessService::class);
        $offerSigned = $readiness->offerSigned($application);
        $hasFees = $readiness->hasPostApprovalFees($application);
        $feesPaid = $readiness->feesPaid($application);
        $feesComplete = $offerSigned && (! $hasFees || $feesPaid);
        $destinationConfirmed = $readiness->isAssetLendingApplication($application)
            || $readiness->disbursementDetailsConfirmed($application);
        $destinationComplete = $feesComplete && $destinationConfirmed;
        $contractSigned = $readiness->contractSigned($application);
        $disbursed = $this->isDisbursed($application);
        $activeLoan = $disbursed && ! $this->isClosed($application);

        $steps = [
            ['key' => 'submitted', 'label' => __('borrower.loan_progress.submitted'), 'complete' => true, 'current' => false],
            ['key' => 'approved', 'label' => __('borrower.loan_progress.approval'), 'complete' => true, 'current' => false],
            [
                'key'      => 'accept_offer',
                'label'    => __('borrower.loan_progress.accept_offer'),
                'complete' => $offerSigned,
                'current'  => false,
            ],
            [
                'key'      => 'post_approval_fee',
                'label'    => __('borrower.loan_progress.post_approval_fee'),
                'complete' => $feesComplete,
                'current'  => false,
            ],
            [
                'key'      => 'destination',
                'label'    => __('borrower.loan_progress.destination'),
                'complete' => $destinationComplete,
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
                'label'    => $disbursed && $application->disbursed_at
                    ? __('borrower.loan_progress.disbursement').' · '.$application->disbursed_at->format('d M Y')
                    : __('borrower.loan_progress.disbursement'),
                'complete' => $disbursed,
                'current'  => false,
            ],
            [
                'key'      => 'active_loan',
                'label'    => $activeLoan && $application->loan
                    ? __('borrower.loan_progress.active_loan').' · '.$application->loan->loan_number
                    : __('borrower.loan_progress.active_loan'),
                'complete' => $activeLoan,
                'current'  => false,
            ],
        ];

        $currentKey = match (true) {
            $activeLoan => 'active_loan',
            $disbursed => 'active_loan',
            $readiness->isReadyForDisbursement($application) => 'disbursement',
            $readiness->needsContractSignature($application) => 'contract',
            $readiness->needsDisbursementDetailsConfirmation($application) => 'destination',
            $readiness->needsPostApprovalFees($application) => 'post_approval_fee',
            $readiness->needsBorrowerSignature($application) => 'accept_offer',
            default => 'accept_offer',
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
            || (string) $application->offer_status === 'accepted'
            || in_array((string) ($application->current_stage ?? ''), app(ApplicationDisbursementReadinessService::class)->borrowerPostApprovalStages(), true);
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

        if ($status === 'awaiting_guarantor' || $stage === 'awaiting_guarantor') {
            return 'awaiting_guarantor';
        }

        if ($application->offer_status === 'declined' || $this->offerCancelledByBorrower($application)) {
            return 'offer_declined';
        }

        if ($status === 'withdrawn' && $application->offer_status === 'declined') {
            return 'offer_declined';
        }

        if ($status === 'withdrawn') {
            return 'withdrawn';
        }

        if ($this->isClosed($application)) {
            return 'closed';
        }

        if ($this->isDisbursed($application)) {
            return 'disbursed';
        }

        $requests = $application->relationLoaded('documentRequests')
            ? $application->documentRequests
            : $application->documentRequests()->get();

        // Open underwriting requests always win over "approved" labels — borrower should
        // never see Approved while still asked for documents or other UW actions.
        $secure = app(CollateralSecureService::class);
        if ($secure->needsValuationFeePayment($application)) {
            return 'awaiting_valuation_fee';
        }

        if ($requests->where('status', 'uploaded')->isNotEmpty()) {
            return 'documents_resubmitted';
        }

        if ($status === 'pending_documents' || $requests->whereIn('status', ['pending', 'rejected'])->isNotEmpty()) {
            return 'documents_requested';
        }

        if ($status === 'awaiting_offer' || $application->offer_status === 'pending_borrower') {
            return 'awaiting_offer';
        }

        if ($application->offer_status === 'accepted'
            || in_array($status, ['approved', 'pre_approved'], true)
            || in_array($stage, app(\App\Services\ApplicationDisbursementReadinessService::class)->borrowerPostApprovalStages(), true)
            || in_array($stage, ['pre_approval'], true)) {
            // Committee / pre-approval is still "under review" to the borrower until final approve.
            if (in_array($status, ['pre_approved'], true) || $stage === 'pre_approval') {
                return 'under_review';
            }

            $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);

            if ($readiness->needsBorrowerSignature($application)) {
                return 'awaiting_signature';
            }

            if ($readiness->needsPostApprovalFees($application)) {
                return 'offer_accepted';
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

        if ($stage === 'credit_appraisal' || $status === 'under_review' || $stage === 'screening' || $stage === 'pre_approval') {
            return 'under_review';
        }

        // Borrower-facing: once submitted, stay on one "credit review" label through
        // screening / underwriting until approved, rejected, or a document request.
        if (in_array($status, ['submitted', 'pending'], true)) {
            return 'under_review';
        }

        return 'under_review';
    }

    private function labelForCode(string $code, LoanApplication $application): string
    {
        return match ($code) {
            'draft'                 => __('borrower.applications_list.statuses.draft'),
            'submitted'             => __('borrower.applications_list.statuses.under_review'),
            'awaiting_guarantor'    => __('borrower.applications_list.statuses.awaiting_guarantor'),
            'under_review'          => __('borrower.applications_list.statuses.under_review'),
            'screening'             => __('borrower.applications_list.statuses.under_review'),
            'documents_requested'   => __('borrower.applications_list.statuses.documents_requested'),
            'documents_resubmitted' => __('borrower.applications_list.statuses.documents_resubmitted'),
            'awaiting_valuation_fee'=> __('borrower.applications_list.statuses.awaiting_valuation_fee'),
            'credit_review'         => __('borrower.applications_list.statuses.under_review'),
            'awaiting_offer'        => __('borrower.applications_list.statuses.awaiting_offer'),
            'offer_accepted'        => __('borrower.applications_list.statuses.offer_accepted'),
            'offer_declined'        => __('borrower.applications_list.statuses.offer_declined'),
            'withdrawn'             => __('borrower.applications_list.statuses.withdrawn'),
            'awaiting_signature'    => __('borrower.applications_list.statuses.awaiting_signature'),
            'post_approval_fees'    => __('borrower.applications_list.statuses.post_approval_fees'),
            'awaiting_disbursement_details' => __('borrower.applications_list.statuses.awaiting_disbursement_details'),
            'awaiting_contract'     => __('borrower.applications_list.statuses.awaiting_contract'),
            'ready_for_disbursement'=> __('borrower.applications_list.statuses.ready_for_disbursement'),
            'approved'              => __('borrower.applications_list.statuses.approved'),
            'rejected'              => __('borrower.applications_list.statuses.not_approved'),
            'disbursed'             => __('borrower.applications_list.statuses.disbursed'),
            'closed'                => __('borrower.applications_list.statuses.closed'),
            default                 => ucfirst(str_replace('_', ' ', $code)),
        };
    }

    private function toneForCode(string $code): string
    {
        return match ($code) {
            'rejected' => 'red',
            'offer_declined', 'withdrawn' => 'red',
            'awaiting_guarantor' => 'amber',
            'awaiting_offer' => 'amber',
            'offer_accepted', 'post_approval_fees' => 'sky',
            'awaiting_signature', 'awaiting_disbursement_details', 'awaiting_contract' => 'sky',
            'approved', 'ready_for_disbursement', 'disbursed', 'closed' => 'emerald',
            'draft', 'submitted' => 'amber',
            'under_review', 'screening', 'credit_review' => 'sky',
            'documents_requested', 'documents_resubmitted' => 'orange',
            'awaiting_valuation_fee' => 'amber',
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

    private function offerCancelledByBorrower(LoanApplication $application): bool
    {
        return app(ApplicationOfferService::class)->offerDeclinedByBorrower($application);
    }
}
