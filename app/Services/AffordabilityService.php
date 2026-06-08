<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class AffordabilityService
{
    public function __construct(
        private readonly CountryCreditSettingsService $countryCredit,
    ) {}

    /**
     * Evaluate borrower affordability using the one-third income rule.
     *
     * @return array{
     *   net_income: float,
     *   existing_obligations: float,
     *   new_emi: float,
     *   proposed_installment: float,
     *   max_repayment_capacity: float,
     *   available_capacity: float,
     *   total_obligations: float,
     *   dsr: float,
     *   threshold: float,
     *   repayment_ratio: float,
     *   repayment_ratio_pct: float,
     *   verdict: 'pass'|'warn'|'fail',
     *   pass: bool,
     *   status_label: string,
     *   reason: string,
     *   evaluated_at: string
     * }
     */
    public function evaluate(LoanApplication $application): array
    {
        $customer = $application->customer;
        $product  = $application->product;

        $netIncome = (float) ($customer->monthly_income ?? 0);

        $existing = (float) RepaymentSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(30)])
            ->sum('total_due');

        $principal = (float) ($application->recommended_amount ?? $application->requested_amount);
        $tenure    = (int) ($application->requested_tenure_months ?? 0);
        $rate      = (float) ($product->interest_rate ?? 0);

        $newEmi = $this->computeEmi($principal, $rate, $tenure);

        $ratio = $this->countryCredit->repaymentRatio();
        $maxRepayment = round($netIncome * $ratio, 2);
        $availableCapacity = max(0.0, round($maxRepayment - $existing, 2));

        $total = $existing + $newEmi;
        $dsr = $netIncome > 0 ? round($total / $netIncome, 4) : 1.0;
        $threshold = $ratio;

        $verdict = 'pass';
        $statusLabel = 'Affordability Passed';
        $reason = 'Proposed installment is within available capacity.';

        if ($netIncome <= 0) {
            $verdict = 'fail';
            $statusLabel = 'Affordability Failed';
            $reason = 'No declared monthly income on file.';
        } elseif ($newEmi > $availableCapacity) {
            $verdict = 'fail';
            $statusLabel = 'Affordability Failed';
            $reason = 'Proposed installment '.format_money($newEmi).' exceeds available capacity '.format_money($availableCapacity).'.';
        } elseif ($newEmi > ($maxRepayment * 0.9)) {
            $verdict = 'warn';
            $statusLabel = 'Affordability Passed';
            $reason = 'Proposed installment is near the maximum repayment capacity.';
        }

        return [
            'net_income'              => round($netIncome, 2),
            'existing_obligations'    => round($existing, 2),
            'new_emi'                 => round($newEmi, 2),
            'proposed_installment'    => round($newEmi, 2),
            'max_repayment_capacity'  => $maxRepayment,
            'available_capacity'      => $availableCapacity,
            'total_obligations'       => round($total, 2),
            'dsr'                     => $dsr,
            'threshold'               => $threshold,
            'repayment_ratio'         => $ratio,
            'repayment_ratio_pct'     => round($ratio * 100, 2),
            'verdict'                 => $verdict,
            'pass'                    => $verdict === 'pass',
            'status_label'            => $statusLabel,
            'reason'                  => $reason,
            'evaluated_at'            => now()->toIso8601String(),
        ];
    }

    private function computeEmi(float $principal, float $monthlyRate, int $months): float
    {
        if ($principal <= 0 || $months <= 0) {
            return 0.0;
        }
        if ($monthlyRate <= 0) {
            return round($principal / $months, 2);
        }
        $pow = (1 + $monthlyRate) ** $months;

        return round($principal * $monthlyRate * $pow / ($pow - 1), 2);
    }
}
