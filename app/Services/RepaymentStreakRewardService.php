<?php

namespace App\Services;

use App\Models\Customer;

class RepaymentStreakRewardService
{
    public function __construct(
        private readonly MemberEngagementService $engagement,
        private readonly GamificationSettingsService $settings,
    ) {}

    /** @return array{enabled: bool, count: int, percent: float, max_percent: float, fee_type: string, milestones: list<array{count: int, percent: float, reached: bool}>} */
    public function status(Customer $customer): array
    {
        $config = $this->config();
        $count = $this->engagement->repaymentStreak($customer)['count'] ?? 0;
        $milestones = collect($config['milestones'] ?? [])
            ->map(fn (array $m) => [
                'count'   => (int) ($m['count'] ?? 0),
                'percent' => (float) ($m['percent'] ?? 0),
                'reached' => $count >= (int) ($m['count'] ?? 0),
            ])
            ->values()
            ->all();

        return [
            'enabled'      => (bool) ($config['enabled'] ?? true),
            'count'        => $count,
            'percent'      => $this->earnedPercent($count),
            'max_percent'  => (float) ($config['max_discount_percent'] ?? 30),
            'fee_type'     => (string) ($config['fee_type'] ?? 'application_fee'),
            'milestones'   => $milestones,
            'reward_label' => (string) ($config['reward_label'] ?? __('borrower.engagement.streak.reward')),
        ];
    }

    /** @return array{discount: float, percent: float} */
    public function discountForFee(Customer $customer, string $feeType, float $amountAfterPriorDiscounts): array
    {
        $config = $this->config();

        if (! ($config['enabled'] ?? true)) {
            return ['discount' => 0.0, 'percent' => 0.0];
        }

        if ($feeType !== ($config['fee_type'] ?? 'application_fee')) {
            return ['discount' => 0.0, 'percent' => 0.0];
        }

        $percent = $this->earnedPercent($this->engagement->repaymentStreak($customer)['count'] ?? 0);
        if ($percent <= 0 || $amountAfterPriorDiscounts <= 0) {
            return ['discount' => 0.0, 'percent' => 0.0];
        }

        $discount = round($amountAfterPriorDiscounts * ($percent / 100), 2);

        return ['discount' => $discount, 'percent' => $percent];
    }

    private function earnedPercent(int $streakCount): float
    {
        $config = $this->config();
        $max = (float) ($config['max_discount_percent'] ?? 30);
        $earned = 0.0;

        foreach ($config['milestones'] ?? [] as $milestone) {
            if ($streakCount >= (int) ($milestone['count'] ?? 0)) {
                $earned = max($earned, (float) ($milestone['percent'] ?? 0));
            }
        }

        return min($earned, $max);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return $this->settings->group('repayment_streak')
            ?: config('gamification.repayment_streak', []);
    }
}
