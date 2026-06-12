<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use Illuminate\Validation\ValidationException;

class LoanOriginationService
{
    /** Create a pending loan record from an approved application (idempotent). */
    public function createFromApplication(LoanApplication $application): Loan
    {
        $application->loadMissing(['customer', 'product', 'loan']);

        if ($application->loan) {
            $loan = $application->loan;
            $this->releasePendingCapitalIfAllocated($loan);

            return $loan;
        }

        if (! in_array($application->current_stage, ['approval', 'disbursement'], true)
            && ! in_array($application->status, ['approved', 'disbursed'], true)) {
            throw ValidationException::withMessages([
                'application' => 'Application must be approved before creating a loan.',
            ]);
        }

        if (! $application->product) {
            throw ValidationException::withMessages([
                'application' => 'Application is missing a loan product.',
            ]);
        }

        $amount = app(ApplicationOfferService::class)->effectiveAmount($application);
        $tenure = (int) ($application->offered_tenure_months ?? $application->requested_tenure_months ?: $application->product->tenure_min_months);
        $product = $application->product;
        $monthlyRate = app(DisplayedRateService::class)->displayedMonthlyRate($product, $amount);
        $penaltyDefaults = LoanPenaltyPolicy::defaultsForProduct($product);

        $loan = Loan::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $application->customer_id,
            'loan_product_id'     => $application->loan_product_id,
            'loan_number'         => app(ReferenceNumberService::class)->loanReference($product),
            'principal_amount'      => $amount,
            'approved_amount'       => $amount,
            'outstanding_balance'   => $amount,
            'interest_rate'         => $monthlyRate,
            'default_grace_days'    => $penaltyDefaults['default_grace_days'],
            'penalty_rate_percent'  => $penaltyDefaults['penalty_rate_percent'],
            'penalty_basis'         => $penaltyDefaults['penalty_basis'],
            'tenure_months'         => max(1, $tenure),
            'status'                => 'pending',
        ]);

        return $loan;
    }

    /** Release legacy approval-time capital reservations on pending loans. */
    public function releasePendingCapitalIfAllocated(Loan $loan): void
    {
        if ($loan->status !== 'pending' || ! $loan->capitalAllocations()->exists()) {
            return;
        }

        app(CapitalPartnerAllocationService::class)->releaseAllocationForLoan($loan);
    }
}
