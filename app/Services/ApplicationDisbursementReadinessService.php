<?php

namespace App\Services;

use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;

class ApplicationDisbursementReadinessService
{
    public function __construct(
        private readonly PostApprovalFeeService $fees,
        private readonly LoanPolicyService $policy,
        private readonly ApplicationOfferService $offers,
        private readonly GuarantorSignatureService $guarantorSignatures,
        private readonly CapitalPartnerAllocationService $capital,
        private readonly CustomerDisbursementDetailsService $disbursementDetails,
    ) {}

    public function offerLetter(LoanApplication $application): ?LoanAgreement
    {
        return LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();
    }

    public function loanContract(LoanApplication $application): ?LoanAgreement
    {
        return LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'loan_contract')
            ->latest('id')
            ->first();
    }

    public function offerSigned(LoanApplication $application): bool
    {
        if ((string) $application->offer_status === 'accepted') {
            return true;
        }

        $offer = $this->offerLetter($application);

        return $offer && $offer->isSigned() && ! $offer->isOfferExpired();
    }

    public function contractSigned(LoanApplication $application): bool
    {
        $contract = $this->loanContract($application);

        return $contract && $contract->isSigned();
    }

    public function hasPostApprovalFees(LoanApplication $application): bool
    {
        return LoanApplicationPostApprovalFee::query()
            ->where('loan_application_id', $application->id)
            ->exists();
    }

    public function feesPaid(LoanApplication $application): bool
    {
        return $this->fees->allPaid($application);
    }

    public function disbursementDetailsConfirmed(LoanApplication $application): bool
    {
        return $this->disbursementDetails->disbursementDetailsConfirmed($application);
    }

    public function needsDisbursementDetailsConfirmation(LoanApplication $application): bool
    {
        if (! $this->offerSigned($application)) {
            return false;
        }

        if ($this->hasPostApprovalFees($application) && ! $this->feesPaid($application)) {
            return false;
        }

        return ! $this->disbursementDetailsConfirmed($application);
    }

    /** @return array<string, mixed> */
    public function disbursementDestination(LoanApplication $application): array
    {
        return $this->disbursementDetails->snapshotForApplication($application);
    }

    public function requiresGuarantorSignature(LoanApplication $application): bool
    {
        $application->loadMissing(['product', 'customerGuarantors']);
        $product = $application->product;

        if (! $product) {
            return false;
        }

        $amount = $this->offers->effectiveAmount($application);
        if (! $this->policy->requiresGuarantorForApplication($product, $amount)) {
            return false;
        }

        return $application->customerGuarantors
            ->contains(fn ($link) => $link->status === 'approved');
    }

    public function guarantorSigned(LoanApplication $application): bool
    {
        return $this->guarantorSignatures->hasSignature($application);
    }

    public function canMarkDisbursement(LoanApplication $application): bool
    {
        if (! $this->offerSigned($application)) {
            return false;
        }

        if ($this->hasPostApprovalFees($application) && ! $this->feesPaid($application)) {
            return false;
        }

        if (! $this->disbursementDetailsConfirmed($application)) {
            return false;
        }

        if (! $this->contractSigned($application)) {
            return false;
        }

        if ($this->requiresGuarantorSignature($application) && ! $this->guarantorSigned($application)) {
            return false;
        }

        return $this->capitalAvailable($application);
    }

    public function capitalAvailable(LoanApplication $application): bool
    {
        $application->loadMissing(['loan.product']);
        $loan = $application->loan;

        if (! $loan) {
            return true;
        }

        return $this->capital->capitalReadinessForLoan($loan)['ok'];
    }

    /** @return array{ok: bool, required: float, available: float, uses_capital: bool, message: ?string}|null */
    public function capitalReadiness(LoanApplication $application): ?array
    {
        $application->loadMissing(['loan.product']);
        $loan = $application->loan;

        if (! $loan) {
            return null;
        }

        $readiness = $this->capital->capitalReadinessForLoan($loan);

        return $readiness['uses_capital'] ? $readiness : null;
    }

    /** @return list<string> */
    public function blockingMessages(LoanApplication $application): array
    {
        $messages = [];

        if (! $this->offerLetter($application)) {
            $messages[] = 'Generate and send the offer letter first.';
        } elseif ($this->offerLetter($application)?->isOfferExpired()) {
            $messages[] = 'Offer letter has expired — regenerate and reissue to the borrower.';
        } elseif (! $this->offerSigned($application)) {
            $messages[] = 'Borrower must sign the offer letter.';
        }

        if ($this->hasPostApprovalFees($application) && ! $this->feesPaid($application)) {
            $messages[] = 'Post-approval fees must be paid.';
        }

        if (($this->feesPaid($application) || ! $this->hasPostApprovalFees($application)) && ! $this->contractSigned($application)) {
            if (! $this->loanContract($application)) {
                $messages[] = 'Loan contract must be generated after post-approval fees are paid.';
            } else {
                $messages[] = 'Borrower must accept the loan contract.';
            }
        }

        if ($this->contractSigned($application) && ! $this->disbursementDetailsConfirmed($application)) {
            $messages[] = 'Borrower must confirm disbursement destination.';
        }

        if ($this->requiresGuarantorSignature($application) && ! $this->guarantorSigned($application)) {
            $messages[] = 'Guarantor must sign before disbursement.';
        }

        $capital = $this->capitalReadiness($application);
        if ($capital && ! $capital['ok'] && $capital['message']) {
            $messages[] = $capital['message'];
        }

        return $messages;
    }

    public function needsBorrowerSignature(LoanApplication $application): bool
    {
        if ((string) $application->offer_status === 'accepted') {
            return false;
        }

        if ($application->offer_status === 'declined'
            || app(ApplicationOfferService::class)->offerDeclinedByBorrower($application)) {
            return false;
        }

        $offer = $this->offerLetter($application);

        return $offer && ! $offer->isSigned() && ! $offer->isOfferExpired();
    }

    public function needsPostApprovalFees(LoanApplication $application): bool
    {
        if (! $this->offerSigned($application)) {
            return false;
        }

        return $this->hasPostApprovalFees($application) && ! $this->feesPaid($application);
    }

    public function needsContractSignature(LoanApplication $application): bool
    {
        if (! $this->offerSigned($application)) {
            return false;
        }

        if ($this->hasPostApprovalFees($application) && ! $this->feesPaid($application)) {
            return false;
        }

        return ! $this->contractSigned($application);
    }

    public function isReadyForDisbursement(LoanApplication $application): bool
    {
        return $this->canMarkDisbursement($application);
    }

    /**
     * @return array<string, array{label: string, status: string, complete: bool}>
     */
    public function disbursementChecklist(LoanApplication $application): array
    {
        $hasFees = $this->hasPostApprovalFees($application);
        $feesPaid = $this->feesPaid($application);
        $detailsConfirmed = $this->disbursementDetailsConfirmed($application);
        $contract = $this->loanContract($application);
        $contractSigned = $this->contractSigned($application);
        $canDisburse = $this->canMarkDisbursement($application);
        $disbursed = (string) $application->status === 'disbursed'
            || in_array((string) ($application->loan?->status ?? ''), ['active', 'disbursed'], true);
        $capital = $this->capitalReadiness($application);

        $checklist = [
            'post_approval_fee' => [
                'label'    => __('borrower.contract.checklist.post_approval_fee'),
                'status'   => ! $hasFees ? 'not_required' : ($feesPaid ? 'paid' : 'pending'),
                'complete' => ! $hasFees || $feesPaid,
            ],
            'destination' => [
                'label'    => __('borrower.contract.checklist.destination'),
                'status'   => $detailsConfirmed ? 'accepted' : 'pending',
                'complete' => $detailsConfirmed,
            ],
            'contract' => [
                'label'    => __('borrower.contract.checklist.contract'),
                'status'   => $contractSigned ? 'accepted' : ($contract ? 'pending' : 'not_generated'),
                'complete' => $contractSigned,
            ],
        ];

        if ($capital) {
            $checklist['capital'] = [
                'label'    => __('borrower.contract.checklist.capital'),
                'status'   => $capital['ok'] ? 'available' : 'insufficient',
                'complete' => $capital['ok'],
            ];
        }

        $checklist['disbursement'] = [
            'label'    => __('borrower.contract.checklist.disbursement'),
            'status'   => $disbursed ? 'complete' : ($canDisburse ? 'pending' : 'locked'),
            'complete' => $disbursed,
        ];

        return $checklist;
    }

    /**
     * Borrower-facing disbursement checklist — ordered pipeline without internal capital checks.
     *
     * @return array<string, array{label: string, status: string, complete: bool}>
     */
    public function borrowerDisbursementChecklist(LoanApplication $application): array
    {
        $offerSigned = $this->offerSigned($application);
        $hasFees = $this->hasPostApprovalFees($application);
        $feesPaid = $this->feesPaid($application);
        $feesComplete = $offerSigned && (! $hasFees || $feesPaid);
        $detailsConfirmed = $this->disbursementDetailsConfirmed($application);
        $contract = $this->loanContract($application);
        $contractSigned = $this->contractSigned($application);
        $canDisburse = $this->canMarkDisbursement($application);
        $disbursed = (string) $application->status === 'disbursed'
            || in_array((string) ($application->loan?->status ?? ''), ['active', 'disbursed'], true);

        $feeStatus = match (true) {
            ! $offerSigned => 'locked',
            ! $hasFees => 'not_required',
            $feesPaid => 'paid',
            default => 'pending',
        };

        $destinationStatus = match (true) {
            ! $feesComplete => 'locked',
            $detailsConfirmed => 'accepted',
            default => 'pending',
        };

        $contractStatus = match (true) {
            ! $feesComplete || ! $detailsConfirmed => 'locked',
            $contractSigned => 'accepted',
            $contract => 'pending',
            default => 'not_generated',
        };

        return [
            'offer' => [
                'label'    => __('borrower.contract.checklist.offer'),
                'status'   => $offerSigned ? 'accepted' : 'pending',
                'complete' => $offerSigned,
            ],
            'post_approval_fee' => [
                'label'    => __('borrower.contract.checklist.post_approval_fee'),
                'status'   => $feeStatus,
                'complete' => $feesComplete,
            ],
            'destination' => [
                'label'    => __('borrower.contract.checklist.destination'),
                'status'   => $destinationStatus,
                'complete' => $detailsConfirmed,
            ],
            'contract' => [
                'label'    => __('borrower.contract.checklist.contract'),
                'status'   => $contractStatus,
                'complete' => $contractSigned,
            ],
            'disbursement' => [
                'label'    => __('borrower.contract.checklist.disbursement'),
                'status'   => $disbursed ? 'complete' : ($canDisburse ? 'pending' : 'locked'),
                'complete' => $disbursed,
            ],
        ];
    }

    public function resolveBorrowerStageAfterOfferAcceptance(LoanApplication $application): string
    {
        if ($this->needsPostApprovalFees($application)) {
            return LoanApplication::BORROWER_STAGE_POST_APPROVAL_FEES;
        }

        if ($this->needsDisbursementDetailsConfirmation($application)) {
            return LoanApplication::BORROWER_STAGE_AWAITING_DISBURSEMENT_DETAILS;
        }

        if ($this->needsContractSignature($application)) {
            return LoanApplication::BORROWER_STAGE_CONTRACT;
        }

        if ($this->isReadyForDisbursement($application)) {
            return 'disbursement';
        }

        return (string) ($application->current_stage ?? 'approval');
    }

    public function resolveBorrowerCurrentAction(LoanApplication $application): ?string
    {
        if ($this->needsBorrowerSignature($application)) {
            return 'sign_offer';
        }

        if ($this->needsPostApprovalFees($application)) {
            return 'pay_post_approval_fees';
        }

        if ($this->needsDisbursementDetailsConfirmation($application)) {
            return 'confirm_disbursement_details';
        }

        if ($this->needsContractSignature($application)) {
            return 'sign_contract';
        }

        if ($this->isReadyForDisbursement($application)) {
            return 'ready_for_disbursement';
        }

        return null;
    }

    /** @return list<string> */
    public function resolveBorrowerCompletedSteps(LoanApplication $application): array
    {
        $steps = [];

        if ($this->offerSigned($application)) {
            $steps[] = 'offer_accepted';
        }

        if ($this->offerSigned($application)
            && (! $this->hasPostApprovalFees($application) || $this->feesPaid($application))) {
            $steps[] = 'post_approval_fees_paid';
        }

        if ($this->disbursementDetailsConfirmed($application)) {
            $steps[] = 'disbursement_account_confirmed';
        }

        if ($this->contractSigned($application)) {
            $steps[] = 'contract_signed';
        }

        if ((string) $application->status === 'disbursed'
            || in_array((string) ($application->loan?->status ?? ''), ['active', 'disbursed'], true)) {
            $steps[] = 'disbursed';
        }

        return $steps;
    }

    public function syncBorrowerProgress(LoanApplication $application): LoanApplication
    {
        $application = $application->fresh(['product', 'postApprovalFees', 'loan']);

        $application->update([
            'current_stage'            => $this->resolveBorrowerStageAfterOfferAcceptance($application),
            'borrower_current_action'  => $this->resolveBorrowerCurrentAction($application),
            'borrower_completed_steps' => $this->resolveBorrowerCompletedSteps($application),
        ]);

        return $application->fresh(['product', 'postApprovalFees', 'loan']);
    }

    public function borrowerPostApprovalStages(): array
    {
        return [
            LoanApplication::BORROWER_STAGE_POST_APPROVAL_FEES,
            LoanApplication::BORROWER_STAGE_AWAITING_DISBURSEMENT_DETAILS,
            LoanApplication::BORROWER_STAGE_CONTRACT,
            'approval',
            'disbursement',
        ];
    }

    /** Post-approval pipeline stage label for admin underwriting tabs. */
    public function approvedPipelineStage(LoanApplication $application): string
    {
        if ($application->offer_status === 'declined'
            || app(ApplicationOfferService::class)->offerDeclinedByBorrower($application)) {
            return 'Offer Declined';
        }

        $offer = $this->offerLetter($application);

        if (! $offer || (! $offer->isSigned() && $offer->status !== 'cancelled')) {
            return 'Offer Sent';
        }

        if (! $this->offerSigned($application)) {
            return 'Offer Sent';
        }

        if (($application->current_stage ?? '') === LoanApplication::BORROWER_STAGE_POST_APPROVAL_FEES
            || $this->needsPostApprovalFees($application)) {
            return 'Post Approval Fee';
        }

        if ($this->needsDisbursementDetailsConfirmation($application)) {
            return 'Awaiting Disbursement Details';
        }

        if ($this->needsContractSignature($application)) {
            return 'Contract';
        }

        if ($this->isReadyForDisbursement($application)) {
            return 'Ready For Disbursement';
        }

        return 'Offer Accepted';
    }

    /** Disbursement queue status for admin operations. */
    public function disbursementQueueStatus(LoanApplication $application): string
    {
        if ((string) $application->status === 'disbursed'
            || in_array((string) ($application->loan?->status ?? ''), ['active', 'closed', 'written_off', 'arrears'], true)) {
            return 'Disbursed';
        }

        if ($this->isReadyForDisbursement($application)) {
            return 'Ready';
        }

        if ($this->needsContractSignature($application)) {
            return 'Awaiting Contract';
        }

        if ($this->needsPostApprovalFees($application)) {
            return 'Awaiting Fee';
        }

        if ($this->needsDisbursementDetailsConfirmation($application)) {
            return 'Awaiting Destination';
        }

        if ($this->needsBorrowerSignature($application)) {
            return 'Awaiting Offer';
        }

        return 'In Progress';
    }

    /** Disbursement pipeline stage label for admin underwriting tabs. */
    public function disbursementPipelineStage(LoanApplication $application): string
    {
        return $this->disbursementQueueStatus($application);
    }
}
