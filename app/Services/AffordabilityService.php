<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Carbon;

class AffordabilityService
{
    public function __construct(
        private readonly CountryCreditSettingsService $countryCredit,
        private readonly StatementCapacityService $statements,
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
     *   evaluated_at: string,
     *   income_basis: 'statement'|'declared',
     *   declared_monthly_income: float,
     *   statement_monthly: float|null,
     *   statement_weekly: float|null,
     *   statement_deposits_total: float|null,
     *   statement_months: int|null,
     *   max_affordable_principal: float,
     *   repayment_cadence: string
     * }
     */
    public function evaluate(LoanApplication $application, bool $declaredOnly = false): array
    {
        $customer = $application->customer;
        $product  = $application->product;

        $resolved = $customer
            ? ($declaredOnly
                ? $this->statements->declaredBundle($customer)
                : $this->statements->resolveIncome($application, $customer, 'borrower'))
            : [
                'net_income' => 0.0,
                'income_basis' => 'declared',
                'declared_monthly_income' => 0.0,
                'statement_deposits_total' => null,
                'statement_months' => null,
                'statement_monthly' => null,
                'statement_weekly' => null,
            ];
        $netIncome = (float) $resolved['net_income'];

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
            $reason = $resolved['income_basis'] === 'statement'
                ? 'No proven monthly income from the statement totals.'
                : 'No declared monthly income on file.';
        } elseif ($newEmi > $availableCapacity) {
            $verdict = 'fail';
            $statusLabel = 'Affordability Failed';
            $reason = 'Proposed installment '.format_money($newEmi).' exceeds available capacity '.format_money($availableCapacity).'.';
        } elseif ($newEmi > ($maxRepayment * 0.9)) {
            $verdict = 'warn';
            $statusLabel = 'Affordability Passed';
            $reason = 'Proposed installment is near the maximum repayment capacity.';
        }

        $highCap = (float) ($product?->max_amount ?? $application->requested_amount ?? $availableCapacity * max(1, $tenure));
        $maxPrincipal = $this->principalFromCapacity($availableCapacity, $rate, max(1, $tenure), $highCap);

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
            'income_basis'            => $resolved['income_basis'],
            'declared_monthly_income' => round((float) $resolved['declared_monthly_income'], 2),
            'statement_monthly'       => $resolved['statement_monthly'],
            'statement_weekly'        => $resolved['statement_weekly'],
            'statement_deposits_total'=> $resolved['statement_deposits_total'],
            'statement_months'        => $resolved['statement_months'],
            'max_affordable_principal'=> $maxPrincipal,
            'repayment_cadence'       => (string) ($product?->repayment_cadence ?? 'monthly'),
        ];
    }

    public function estimateInstallment(float $principal, float $monthlyRate, int $months): float
    {
        return $this->computeEmi($principal, $monthlyRate, $months);
    }

    public function maxAffordablePrincipal(LoanApplication $application, ?int $tenureMonths = null): float
    {
        $application->loadMissing(['customer', 'product']);
        $evaluation = $this->evaluate($application);
        $capacity = (float) ($evaluation['available_capacity'] ?? 0);

        if ($capacity <= 0) {
            return 0.0;
        }

        $tenure = $tenureMonths ?: (int) ($application->requested_tenure_months ?? 12);
        $rate = (float) ($application->product?->interest_rate ?? 0);
        $high = (float) ($application->product?->max_amount ?? $application->requested_amount ?? $capacity * $tenure);

        return $this->principalFromCapacity($capacity, $rate, $tenure, $high);
    }

    public function principalFromCapacity(float $capacity, float $monthlyRate, int $months, float $highCap): float
    {
        if ($capacity <= 0 || $months <= 0) {
            return 0.0;
        }

        if ($monthlyRate <= 0) {
            return round($capacity * $months, 2);
        }

        $low = 0.0;
        $high = max($capacity, $highCap);
        $best = 0.0;

        for ($i = 0; $i < 32; $i++) {
            $mid = ($low + $high) / 2;
            $emi = $this->computeEmi($mid, $monthlyRate, $months);

            if ($emi <= $capacity) {
                $best = $mid;
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return round($best, 2);
    }

    /**
     * Evaluate whether a member can absorb additional guarantee exposure.
     *
     * @return array<string, mixed>
     */
    public function evaluateForGuarantor(
        Customer $guarantor,
        float $additionalMonthlyExposure = 0,
        ?LoanApplication $application = null,
        ?int $guarantorLinkId = null,
    ): array {
        $resolved = ($application && $guarantorLinkId)
            ? $this->statements->resolveIncome($application, $guarantor, 'guarantor:'.$guarantorLinkId)
            : [
                'net_income' => $this->statements->declaredMonthly($guarantor),
                'income_basis' => 'declared',
                'declared_monthly_income' => $this->statements->declaredMonthly($guarantor),
                'statement_deposits_total' => null,
                'statement_months' => null,
                'statement_monthly' => null,
                'statement_weekly' => null,
            ];
        $netIncome = (float) $resolved['net_income'];

        $ownObligations = (float) RepaymentSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $guarantor->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(30)])
            ->sum('total_due');

        $guaranteeExposure = app(LoanPolicyService::class)->activeGuaranteeExposure($guarantor);
        $estimatedGuaranteeEmi = $guaranteeExposure > 0 ? round($guaranteeExposure / 12, 2) : 0.0;
        $existing = round($ownObligations + $estimatedGuaranteeEmi, 2);
        $newEmi = round($additionalMonthlyExposure, 2);

        $ratio = $this->countryCredit->repaymentRatio();
        $maxRepayment = round($netIncome * $ratio, 2);
        $availableCapacity = max(0.0, round($maxRepayment - $existing, 2));
        $total = $existing + $newEmi;
        $dsr = $netIncome > 0 ? round($total / $netIncome, 4) : 1.0;

        $verdict = 'pass';
        $statusLabel = 'Capacity available';
        $reason = 'Guarantor has capacity for this exposure.';

        if ($netIncome <= 0) {
            $verdict = 'fail';
            $statusLabel = 'No income on file';
            $reason = $resolved['income_basis'] === 'statement'
                ? 'Guarantor has no proven monthly income from statement totals.'
                : 'Guarantor has no declared monthly income.';
        } elseif ($newEmi > $availableCapacity) {
            $verdict = 'fail';
            $statusLabel = 'Insufficient capacity';
            $reason = 'Additional exposure exceeds available repayment capacity.';
        } elseif ($newEmi > ($maxRepayment * 0.9)) {
            $verdict = 'warn';
            $reason = 'Guarantor is near maximum repayment capacity.';
        }

        return [
            'net_income'             => round($netIncome, 2),
            'existing_obligations'   => $existing,
            'guarantee_exposure'     => round($guaranteeExposure, 2),
            'estimated_guarantee_emi'=> $estimatedGuaranteeEmi,
            'additional_exposure'    => $newEmi,
            'max_repayment_capacity' => $maxRepayment,
            'available_capacity'     => $availableCapacity,
            'dsr'                    => $dsr,
            'verdict'                => $verdict,
            'pass'                   => $verdict === 'pass',
            'status_label'           => $statusLabel,
            'reason'                 => $reason,
            'income_basis'           => $resolved['income_basis'],
            'declared_monthly_income'=> round((float) $resolved['declared_monthly_income'], 2),
            'statement_monthly'      => $resolved['statement_monthly'],
            'statement_weekly'       => $resolved['statement_weekly'],
            'statement_deposits_total' => $resolved['statement_deposits_total'],
            'statement_months'       => $resolved['statement_months'],
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
