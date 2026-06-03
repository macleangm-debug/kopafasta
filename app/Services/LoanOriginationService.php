<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanOriginationService
{
    /** Create a pending loan record from an approved application (idempotent). */
    public function createFromApplication(LoanApplication $application): Loan
    {
        $application->loadMissing(['customer', 'product', 'loan']);

        if ($application->loan) {
            return $application->loan;
        }

        if (! in_array($application->current_stage, ['disbursement'], true)
            && $application->status !== 'disbursed') {
            throw ValidationException::withMessages([
                'application' => 'Application must be at disbursement stage before creating a loan.',
            ]);
        }

        if (! $application->product) {
            throw ValidationException::withMessages([
                'application' => 'Application is missing a loan product.',
            ]);
        }

        $amount = (float) ($application->approved_amount ?: $application->recommended_amount ?: $application->requested_amount);
        $tenure = (int) ($application->requested_tenure_months ?: $application->product->tenure_min_months);
        $monthlyRate = app(DisplayedRateService::class)->displayedMonthlyRate($application->product, $amount);

        return Loan::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $application->customer_id,
            'loan_product_id'     => $application->loan_product_id,
            'loan_number'           => $this->generateLoanNumber(),
            'principal_amount'      => $amount,
            'approved_amount'       => $amount,
            'outstanding_balance'   => $amount,
            'interest_rate'         => $monthlyRate,
            'tenure_months'         => max(1, $tenure),
            'status'                => 'pending',
        ]);
    }

    protected function generateLoanNumber(): string
    {
        return 'LN-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }
}
