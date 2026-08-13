<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Carbon;

class GroupAffordabilityService
{
    public function __construct(
        private readonly AffordabilityService $affordability,
        private readonly CountryCreditSettingsService $countryCredit,
        private readonly GroupLendingService $groupLending,
        private readonly DisplayedRateService $rates,
        private readonly StatementCapacityService $statements,
    ) {}

    /**
     * Per-member repayment capacity vs each member's share of the group loan.
     *
     * @return array{
     *   is_group: bool,
     *   verdict: 'pass'|'warn'|'fail',
     *   pass: bool,
     *   repayment_ratio_pct: float,
     *   total_requested: float,
     *   total_installment: float,
     *   total_capacity: float,
     *   members: list<array<string, mixed>>,
     *   failed_members: list<array<string, mixed>>,
     *   reason: string,
     *   evaluated_at: string
     * }
     */
    public function evaluate(LoanApplication $application): array
    {
        $application->loadMissing(['customer', 'product', 'loanGroup.members.customer']);

        if (! $this->groupLending->isGroupProduct($application->product)) {
            $single = $this->affordability->evaluate($application);

            return [
                'is_group' => false,
                'verdict' => $single['verdict'],
                'pass' => (bool) $single['pass'],
                'repayment_ratio_pct' => (float) ($single['repayment_ratio_pct'] ?? 33.33),
                'total_requested' => (float) ($application->requested_amount ?? 0),
                'total_installment' => (float) ($single['proposed_installment'] ?? 0),
                'total_capacity' => (float) ($single['available_capacity'] ?? 0),
                'members' => [],
                'failed_members' => [],
                'reason' => $single['reason'] ?? '',
                'evaluated_at' => now()->toIso8601String(),
                'affordability' => $single,
            ];
        }

        $ratio = $this->countryCredit->repaymentRatio();
        $amount = (float) ($application->requested_amount ?? 0);
        $tenure = max(1, (int) ($application->requested_tenure_months ?? 12));
        $rateBreakdown = $this->rates->breakdown($application->product, $amount);
        $monthlyRate = (float) ($rateBreakdown['displayed_monthly_rate'] ?? $application->product?->interest_rate ?? 0);

        $group = $application->loanGroup;
        $members = $group?->members
            ?->filter(fn ($m) => ($m->member_status ?? 'active') === 'active')
            ->values() ?? collect();

        if ($members->isEmpty() && $application->customer) {
            // Fallback: treat leader as sole member until group rows exist
            $members = collect([(object) [
                'customer' => $application->customer,
                'role' => 'leader',
                'requested_amount' => $amount,
            ]]);
        }

        $rows = [];
        $failed = [];
        $totalInstallment = 0.0;
        $totalCapacity = 0.0;
        $totalRequested = 0.0;

        foreach ($members as $member) {
            /** @var Customer|null $customer */
            $customer = $member->customer ?? null;
            $share = (float) ($member->requested_amount ?? 0);
            if ($share <= 0 && $members->count() > 0) {
                $share = round($amount / max(1, $members->count()), 2);
            }
            $installment = $this->affordability->estimateInstallment($share, $monthlyRate, $tenure);
            $resolved = $customer
                ? $this->statements->resolveIncome(
                    $application,
                    $customer,
                    $this->statements->subjectForGroupMember($application, $member),
                )
                : [
                    'net_income' => 0.0,
                    'income_basis' => 'declared',
                    'declared_monthly_income' => 0.0,
                    'statement_deposits_total' => null,
                    'statement_months' => null,
                    'statement_monthly' => null,
                    'statement_weekly' => null,
                ];
            $income = (float) $resolved['net_income'];
            $existing = $customer ? $this->existingObligations($customer) : 0.0;
            $maxRepayment = round($income * $ratio, 2);
            $capacity = max(0.0, round($maxRepayment - $existing, 2));

            $verdict = 'pass';
            $reason = 'Within available capacity.';
            if ($income <= 0) {
                $verdict = 'fail';
                $reason = ($resolved['income_basis'] ?? '') === 'statement'
                    ? 'No proven monthly income from statement totals.'
                    : 'No declared monthly income on file.';
            } elseif ($installment > $capacity) {
                $verdict = 'fail';
                $reason = 'Share installment '.format_money($installment).' exceeds capacity '.format_money($capacity).'.';
            } elseif ($installment > ($maxRepayment * 0.9)) {
                $verdict = 'warn';
                $reason = 'Near maximum repayment capacity.';
            }

            $row = [
                'customer_id' => $customer?->id,
                'name' => $customer?->full_name ?: trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: 'Member',
                'role' => $member->role ?? 'member',
                'requested_amount' => $share,
                'proposed_installment' => $installment,
                'net_income' => $income,
                'available_capacity' => $capacity,
                'max_repayment_capacity' => $maxRepayment,
                'verdict' => $verdict,
                'pass' => $verdict === 'pass',
                'reason' => $reason,
                'income_basis' => $resolved['income_basis'],
                'declared_monthly_income' => (float) $resolved['declared_monthly_income'],
                'statement_monthly' => $resolved['statement_monthly'],
                'statement_weekly' => $resolved['statement_weekly'],
                'statement_deposits_total' => $resolved['statement_deposits_total'],
                'statement_months' => $resolved['statement_months'],
            ];

            $rows[] = $row;
            $totalInstallment += $installment;
            $totalCapacity += $capacity;
            $totalRequested += $share;

            if ($verdict === 'fail') {
                $failed[] = $row;
            }
        }

        $groupVerdict = 'pass';
        if ($failed !== []) {
            $groupVerdict = 'fail';
        } elseif (collect($rows)->contains(fn ($r) => ($r['verdict'] ?? '') === 'warn')) {
            $groupVerdict = 'warn';
        } elseif ($totalInstallment > $totalCapacity && $totalCapacity >= 0) {
            $groupVerdict = 'fail';
            // Aggregate fail without individual fail — mark group
        }

        $reason = match ($groupVerdict) {
            'fail' => $failed !== []
                ? 'One or more group members cannot cover their share from activity/income (max '.round($ratio * 100, 2).'% rule).'
                : 'Combined group capacity is below the total installment.',
            'warn' => 'One or more members are near the repayment capacity limit.',
            default => 'All group members are within available repayment capacity.',
        };

        return [
            'is_group' => true,
            'verdict' => $groupVerdict,
            'pass' => $groupVerdict === 'pass',
            'repayment_ratio_pct' => round($ratio * 100, 2),
            'total_requested' => round($totalRequested ?: $amount, 2),
            'total_installment' => round($totalInstallment, 2),
            'total_capacity' => round($totalCapacity, 2),
            'members' => $rows,
            'failed_members' => $failed,
            'reason' => $reason,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }

    public function maxAffordablePrincipal(LoanApplication $application, ?int $tenureMonths = null): float
    {
        $application->loadMissing(['customer', 'product', 'loanGroup.members.customer']);

        if (! $this->groupLending->isGroupProduct($application->product)) {
            return $this->affordability->maxAffordablePrincipal($application, $tenureMonths);
        }

        $evaluation = $this->evaluate($application);
        $tenure = $tenureMonths ?: max(1, (int) ($application->requested_tenure_months ?? 12));
        $amount = (float) ($application->requested_amount ?? 0);
        $rateBreakdown = $this->rates->breakdown($application->product, $amount);
        $monthlyRate = (float) ($rateBreakdown['displayed_monthly_rate'] ?? $application->product?->interest_rate ?? 0);
        $highCap = (float) ($application->product?->max_amount ?? $amount);

        $scale = 1.0;
        $anyShare = false;
        foreach ((array) ($evaluation['members'] ?? []) as $row) {
            $share = (float) ($row['requested_amount'] ?? 0);
            if ($share <= 0) {
                continue;
            }
            $anyShare = true;
            $capacity = (float) ($row['available_capacity'] ?? 0);
            $maxShare = $this->affordability->principalFromCapacity($capacity, $monthlyRate, $tenure, $highCap);
            $scale = min($scale, $maxShare / $share);
        }

        if (! $anyShare) {
            return 0.0;
        }

        return round(max(0, $amount * $scale), 2);
    }

    private function existingObligations(Customer $customer): float
    {
        return (float) RepaymentSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(30)])
            ->sum('total_due');
    }
}
