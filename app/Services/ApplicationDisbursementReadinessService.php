<?php

namespace App\Services;

use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;

class ApplicationDisbursementReadinessService
{
    public function __construct(
        private readonly PostApprovalFeeService $fees,
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
        $offer = $this->offerLetter($application);

        return $offer && $offer->isSigned();
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

    public function canMarkDisbursement(LoanApplication $application): bool
    {
        if (! $this->offerSigned($application)) {
            return false;
        }

        return $this->feesPaid($application);
    }

    /** @return list<string> */
    public function blockingMessages(LoanApplication $application): array
    {
        $messages = [];

        if (! $this->offerLetter($application)) {
            $messages[] = 'Generate and send the offer letter first.';
        } elseif (! $this->offerSigned($application)) {
            $messages[] = 'Borrower must sign the offer letter.';
        }

        if ($this->hasPostApprovalFees($application) && ! $this->feesPaid($application)) {
            $messages[] = 'Post-approval fees must be paid.';
        }

        return $messages;
    }

    public function needsBorrowerSignature(LoanApplication $application): bool
    {
        $offer = $this->offerLetter($application);

        return $offer && ! $offer->isSigned();
    }

    public function needsPostApprovalFees(LoanApplication $application): bool
    {
        return $this->hasPostApprovalFees($application) && ! $this->feesPaid($application);
    }
}
