<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanGroup;

class GroupScoringService
{
    /**
     * @param  list<array<string, mixed>>  $members
     * @return array{
     *     member_completion_percent: float,
     *     average_credit_score: float|null,
     *     average_income: float|null,
     *     group_risk_score: int,
     *     risk_band: string,
     *     member_count: int,
     *     target_member_count: int,
     *     verified_members: int,
     *     members_with_credit_score: int,
     *     members_with_income: int,
     *     computed_at: string
     * }
     */
    public function score(array $members, int $targetCount, ?LoanApplication $application = null): array
    {
        $targetCount = max(1, $targetCount);
        $progress = app(GroupMemberProgressService::class)->summarize($members, $targetCount);

        $memberCompletionPercent = round(
            (($progress['profiles_complete'] * 0.4) + ($progress['verified'] * 0.6)) / $targetCount * 100,
            1,
        );

        $creditScores = [];
        $incomes = [];

        foreach ($members as $member) {
            $customerId = (int) ($member['customer_id'] ?? 0);
            if ($customerId <= 0) {
                continue;
            }

            $customer = Customer::find($customerId);
            if (! $customer) {
                continue;
            }

            $score = $this->resolveCreditScore($customer, $application);
            if ($score !== null) {
                $creditScores[] = $score;
            }

            $income = $this->resolveMonthlyIncome($customer);
            if ($income > 0) {
                $incomes[] = $income;
            }
        }

        $avgCredit = count($creditScores) > 0
            ? round(array_sum($creditScores) / count($creditScores), 1)
            : null;

        $avgIncome = count($incomes) > 0
            ? round(array_sum($incomes) / count($incomes), 2)
            : null;

        $riskScore = $this->compositeRiskScore($memberCompletionPercent, $avgCredit, $avgIncome);

        return [
            'member_completion_percent'   => $memberCompletionPercent,
            'average_credit_score'        => $avgCredit,
            'average_income'              => $avgIncome,
            'group_risk_score'            => $riskScore,
            'risk_band'                   => $this->riskBand($riskScore),
            'member_count'                => count($members),
            'target_member_count'         => $targetCount,
            'verified_members'            => $progress['verified'],
            'members_with_credit_score'   => count($creditScores),
            'members_with_income'         => count($incomes),
            'computed_at'                 => now()->toIso8601String(),
        ];
    }

    public function scoreForGroup(LoanGroup $group, ?LoanApplication $application = null): array
    {
        $rows = app(GroupApplicationStatusService::class)->memberRowsFromGroup($group);
        $target = (int) ($group->target_member_count ?: count($rows));

        return $this->score($rows, max(1, $target), $application);
    }

    public function scoreFromDraftPayload(array $groupPayload): array
    {
        $members = is_array($groupPayload['members'] ?? null) ? $groupPayload['members'] : [];
        $target = max(1, (int) ($groupPayload['target_member_count'] ?? count($members)));

        return $this->score($members, $target);
    }

    private function resolveCreditScore(Customer $customer, ?LoanApplication $application): ?float
    {
        if ($application) {
            $memberCrb = collect($application->credit_appraisal_payload['group_member_crb'] ?? [])
                ->firstWhere('customer_id', $customer->id);

            if (isset($memberCrb['score']) && $memberCrb['score'] !== null) {
                return (float) $memberCrb['score'];
            }
        }

        $latest = app(CrbCreditCheckService::class)->latest($customer);

        return $latest?->score !== null ? (float) $latest->score : null;
    }

    private function resolveMonthlyIncome(Customer $customer): float
    {
        $income = (float) ($customer->monthly_income ?? 0);
        if ($income > 0) {
            return $income;
        }

        if (filled($customer->income_range)) {
            return (float) (config('income_ranges.'.$customer->income_range.'.midpoint') ?? 0);
        }

        return 0;
    }

    private function compositeRiskScore(float $completionPercent, ?float $avgCredit, ?float $avgIncome): int
    {
        $weights = config('group_application.scoring_weights', [
            'completion' => 35,
            'credit'     => 40,
            'income'     => 25,
        ]);

        $score = ($completionPercent / 100) * (float) $weights['completion'];

        if ($avgCredit !== null) {
            $min = (float) config('group_application.crb_score_min', 300);
            $max = (float) config('group_application.crb_score_max', 850);
            $normalized = min(1, max(0, ($avgCredit - $min) / max(1, $max - $min)));
            $score += $normalized * (float) $weights['credit'];
        }

        if ($avgIncome !== null && $avgIncome > 0) {
            $incomeMin = (float) config('group_application.income_score_min', 200_000);
            $incomeMax = (float) config('group_application.income_score_max', 5_000_000);
            $normalized = min(1, max(0, ($avgIncome - $incomeMin) / max(1, $incomeMax - $incomeMin)));
            $score += $normalized * (float) $weights['income'];
        }

        return (int) round(min(100, max(0, $score)));
    }

    private function riskBand(int $riskScore): string
    {
        $low = (int) config('group_application.risk_bands.low', 70);
        $medium = (int) config('group_application.risk_bands.medium', 45);

        if ($riskScore >= $low) {
            return 'low';
        }

        if ($riskScore >= $medium) {
            return 'medium';
        }

        return 'high';
    }
}
