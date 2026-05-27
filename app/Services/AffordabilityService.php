<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;

class AffordabilityService
{
    /**
     * Evaluate borrower affordability (DSR) for a loan application.
     *
     * @return array{
     *   net_income: float,
     *   existing_obligations: float,
     *   new_emi: float,
     *   total_obligations: float,
     *   dsr: float,
     *   threshold: float,
     *   verdict: 'pass'|'warn'|'fail',
     *   reason: string,
     *   evaluated_at: string
     * }
     */
    public function evaluate(LoanApplication $application): array
    {
        $customer = $application->customer;
        $product  = $application->product;

        $netIncome = (float) ($customer->monthly_income ?? 0);

        // Sum existing obligations (next 30 days of pending/partial/overdue installments)
        $existing = (float) RepaymentSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(30)])
            ->sum('total_due');

        $principal = (float) ($application->recommended_amount ?? $application->requested_amount);
        $tenure    = (int) ($application->requested_tenure_months ?? 0);
        $rate      = (float) ($product->interest_rate ?? 0); // monthly decimal

        $newEmi = $this->computeEmi($principal, $rate, $tenure);

        $threshold = $this->thresholdSetting();
        $warnAt    = max(0.0, $threshold - 0.1);

        $total = $existing + $newEmi;
        $dsr   = $netIncome > 0 ? round($total / $netIncome, 4) : 1.0;

        $verdict = 'pass';
        $reason  = 'Within debt-service threshold.';
        if ($netIncome <= 0) {
            $verdict = 'fail';
            $reason  = 'No declared monthly income on file.';
        } elseif ($dsr > $threshold) {
            $verdict = 'fail';
            $reason  = 'Debt-service ratio '.($this->pct($dsr)).' exceeds limit '.($this->pct($threshold)).'.';
        } elseif ($dsr > $warnAt) {
            $verdict = 'warn';
            $reason  = 'Debt-service ratio '.($this->pct($dsr)).' is near limit '.($this->pct($threshold)).'.';
        }

        return [
            'net_income'           => round($netIncome, 2),
            'existing_obligations' => round($existing, 2),
            'new_emi'              => round($newEmi, 2),
            'total_obligations'    => round($total, 2),
            'dsr'                  => $dsr,
            'threshold'            => $threshold,
            'verdict'              => $verdict,
            'reason'               => $reason,
            'evaluated_at'         => now()->toIso8601String(),
        ];
    }

    private function computeEmi(float $principal, float $monthlyRate, int $months): float
    {
        if ($principal <= 0 || $months <= 0) return 0.0;
        if ($monthlyRate <= 0) return round($principal / $months, 2);
        $pow = (1 + $monthlyRate) ** $months;
        return round($principal * $monthlyRate * $pow / ($pow - 1), 2);
    }

    private function thresholdSetting(): float
    {
        $val = optional(SystemSetting::where('key', 'credit.dsr_max')->first())->value;
        $f = is_numeric($val) ? (float) $val : 0.5;
        return $f > 1 ? $f / 100 : $f; // allow stored as 50 or 0.5
    }

    private function pct(float $v): string
    {
        return number_format($v * 100, 1).'%';
    }
}
