<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;

/**
 * Read-model Source of Truth for the lending journey.
 * Aggregates application stage, offer, readiness, loan, and disbursement — does not invent a parallel status column.
 */
class LendingJourneyService
{
    public function forApplication(LoanApplication $application): array
    {
        $application->loadMissing(['customer', 'product', 'loan.disbursements', 'loan.repaymentSchedules']);
        $loan = $application->loan;
        $readiness = app(ApplicationDisbursementReadinessService::class);
        $status = app(ApplicationBorrowerStatusService::class)->forApplication($application);
        $next = $application->customer
            ? app(LoanApplicationNextActionService::class)->forApplication($application->customer, $application)
            : null;
        $authority = app(CreditAuthorityService::class);
        $disbursement = $loan?->disbursements?->sortByDesc('id')->first();

        return [
            'state' => $this->canonicalState($application, $loan, $disbursement),
            'application_status' => $application->status,
            'current_stage' => $application->current_stage,
            'offer_status' => $application->offer_status,
            'loan_status' => $loan?->status,
            'disbursement_status' => $disbursement?->status,
            'borrower_status' => $status['code'] ?? null,
            'waiting_on' => $this->waitingOn($application, $loan, $readiness, $status),
            'actor' => $this->actor($application, $readiness),
            'blocker' => $this->blocker($application, $loan, $readiness),
            'next_action' => $next,
            'automatic_transition' => $this->automaticTransition($application, $loan, $readiness),
            'management_required' => $authority->managementApprovalRequired($application),
            'offer' => $this->offerPresentation($application),
            'checklist' => $readiness->borrowerDisbursementChecklist($application),
            'completed_steps' => $readiness->resolveBorrowerCompletedSteps($application),
        ];
    }

    /** @return array<string, mixed> */
    public function offerPresentation(LoanApplication $application): array
    {
        $application->loadMissing(['product', 'customer']);
        $agreement = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();
        $snap = is_array($agreement?->snapshot) ? $agreement->snapshot : [];
        $amount = (float) ($snap['principal'] ?? app(ApplicationOfferService::class)->effectiveAmount($application));
        $tenure = (int) ($snap['tenure_months']
            ?? $application->offered_tenure_months
            ?? $application->requested_tenure_months
            ?? 0);
        $cadence = (string) ($snap['repayment_cadence'] ?? $application->product?->repayment_cadence ?? 'monthly');
        $rate = (float) ($snap['displayed_monthly_rate'] ?? $snap['interest_rate'] ?? 0);
        if ($rate <= 0 && $application->product) {
            $rate = app(DisplayedRateService::class)->displayedMonthlyRate($application->product, $amount);
        }
        $installment = (float) ($snap['estimated_emi'] ?? 0);
        if ($installment <= 0 && $tenure > 0) {
            $installment = app(AffordabilityService::class)->estimateInstallment($amount, $rate, $tenure);
        }
        $total = (float) ($snap['total_repayable'] ?? ($installment * max(1, (int) ($snap['installment_count'] ?? $tenure))));
        $commencementDays = (int) ($snap['repayment_commencement_days'] ?? 0);

        return [
            'amount' => $amount,
            'tenure_months' => $tenure,
            'frequency' => $cadence,
            'frequency_label' => $this->frequencyLabel($cadence),
            'installment' => $installment,
            'total_repayment' => $total,
            'first_payment_label' => $commencementDays > 0
                ? __('borrower.offer.first_payment_after_disbursement', ['days' => $commencementDays])
                : __('borrower.offer.first_payment_after_disbursement_generic'),
            'product_name' => $application->product?->name,
            'has_signed_offer' => (bool) $agreement?->isSigned(),
        ];
    }

    /** @return array<string, mixed> */
    public function completionSummary(Loan $loan): array
    {
        $loan->loadMissing(['product', 'repaymentSchedules', 'application']);
        $principal = (float) $loan->principal_amount;
        $repaid = (float) $loan->repaymentSchedules->sum('amount_paid');

        return [
            'loan_number' => $loan->loan_number,
            'amount_borrowed' => $principal,
            'amount_repaid' => $repaid > 0 ? $repaid : $principal,
            'completed_at' => $loan->closed_at,
            'product_name' => $loan->product?->name,
        ];
    }

    public function canonicalState(LoanApplication $application, ?Loan $loan, ?Disbursement $disbursement): string
    {
        if ($loan && $loan->status === 'closed') {
            return 'completed';
        }
        if ($loan && in_array($loan->status, ['active', 'arrears', 'disbursed'], true)) {
            return 'active_loan';
        }
        if ($disbursement && $disbursement->status === Disbursement::STATUS_FAILED) {
            return 'disbursement_failed';
        }
        if ($disbursement && in_array($disbursement->status, [Disbursement::STATUS_QUEUED, Disbursement::STATUS_PROCESSING], true)) {
            return 'disbursement_processing';
        }
        if ((string) $application->status === 'disbursed') {
            return 'active_loan';
        }

        return match ((string) $application->current_stage) {
            'submitted' => 'waiting_screening',
            'screening', 'credit_appraisal' => 'screening',
            'pre_approval' => 'waiting_committee',
            'awaiting_management' => 'waiting_management',
            'approval', 'post_approval_fees', 'awaiting_disbursement_details', 'contract_generation' => 'post_approval',
            'disbursement' => 'ready_for_disbursement',
            'rejected' => 'rejected',
            default => (string) ($application->current_stage ?: 'submitted'),
        };
    }

    private function waitingOn(
        LoanApplication $application,
        ?Loan $loan,
        ApplicationDisbursementReadinessService $readiness,
        array $status,
    ): string {
        if ($loan && $loan->status === 'closed') {
            return 'none';
        }
        $code = (string) ($status['code'] ?? '');
        $borrowerCodes = [
            'awaiting_offer', 'awaiting_signature', 'offer_accepted', 'post_approval_fees',
            'awaiting_disbursement_details', 'awaiting_contract', 'awaiting_valuation_fee',
            'awaiting_guarantor', 'documents_requested', 'pending_documents',
        ];
        if (in_array($code, $borrowerCodes, true) || $readiness->needsBorrowerSignature($application)
            || $readiness->needsPostApprovalFees($application)
            || $readiness->needsDisbursementDetailsConfirmation($application)
            || $readiness->needsContractSignature($application)) {
            return 'borrower';
        }

        return match ((string) $application->current_stage) {
            'submitted', 'screening', 'credit_appraisal' => 'screening',
            'pre_approval' => 'committee',
            'awaiting_management' => 'management',
            'approval', 'disbursement' => 'operations',
            default => 'staff',
        };
    }

    private function actor(LoanApplication $application, ApplicationDisbursementReadinessService $readiness): string
    {
        if ($readiness->needsBorrowerSignature($application)
            || $readiness->needsPostApprovalFees($application)
            || $readiness->needsDisbursementDetailsConfirmation($application)
            || $readiness->needsContractSignature($application)) {
            return 'borrower';
        }

        return match ((string) $application->current_stage) {
            'submitted', 'screening', 'credit_appraisal' => 'credit_analyst',
            'pre_approval' => 'credit_committee',
            'awaiting_management' => 'manager',
            'approval', 'disbursement' => 'credit_management',
            default => 'staff',
        };
    }

    private function blocker(
        LoanApplication $application,
        ?Loan $loan,
        ApplicationDisbursementReadinessService $readiness,
    ): ?string {
        if ($loan && in_array($loan->status, ['active', 'closed', 'arrears'], true)) {
            return null;
        }
        if ($readiness->needsBorrowerSignature($application)) {
            return 'offer_acceptance';
        }
        if ($readiness->needsPostApprovalFees($application)) {
            return 'post_approval_fee';
        }
        if ($readiness->needsDisbursementDetailsConfirmation($application)) {
            return 'destination_verification';
        }
        if ($readiness->needsContractSignature($application)) {
            return 'contract_signature';
        }
        if ((string) $application->current_stage === 'awaiting_management') {
            return 'management_approval';
        }
        if ((string) $application->current_stage === 'pre_approval') {
            return 'committee_decision';
        }
        if (in_array((string) $application->current_stage, ['submitted', 'screening', 'credit_appraisal'], true)) {
            return 'screening_recommendation';
        }

        $blocking = $readiness->blockingMessages($application);

        return $blocking[0] ?? null;
    }

    private function automaticTransition(
        LoanApplication $application,
        ?Loan $loan,
        ApplicationDisbursementReadinessService $readiness,
    ): ?string {
        if ($loan && (float) $loan->outstanding_balance <= 0 && $loan->status !== 'closed') {
            return 'close_facility';
        }
        if ($readiness->canMarkDisbursement($application) && (string) $application->current_stage !== 'disbursement') {
            return 'ready_for_disbursement';
        }
        if ((string) $application->current_stage === 'pre_approval'
            && app(ApplicationOfferService::class)->canFinalApprove($application)) {
            return 'committee_to_offer';
        }

        return null;
    }

    private function frequencyLabel(string $cadence): string
    {
        $labels = __('borrower.agreement.repayment_cadences');
        if (is_array($labels) && isset($labels[$cadence])) {
            return (string) $labels[$cadence];
        }

        return ucfirst(str_replace('_', ' ', $cadence));
    }
}
