<?php

namespace App\Services;

use App\Models\Customer;

class RepaymentStreakRewardService
{
    public function __construct(
        private readonly MemberEngagementService $engagement,
        private readonly GamificationSettingsService $settings,
        private readonly LoyaltyPointsService $loyalty,
    ) {}

    /** @return array{enabled: bool, count: int, points: int, milestones: list<array{count: int, points: int, reached: bool}>} */
    public function status(Customer $customer): array
    {
        $config = $this->config();
        $count = $this->engagement->repaymentStreak($customer)['count'] ?? 0;
        $milestones = collect($config['milestones'] ?? [])
            ->map(fn (array $m) => [
                'count'   => (int) ($m['count'] ?? 0),
                'points'  => (int) ($m['points'] ?? $m['percent'] ?? 0),
                'reached' => $count >= (int) ($m['count'] ?? 0),
            ])
            ->values()
            ->all();

        return [
            'enabled'      => (bool) ($config['enabled'] ?? true),
            'count'        => $count,
            'points'       => $this->earnedPoints($count),
            'milestones'   => $milestones,
            'reward_label' => (string) ($config['reward_label'] ?? __('borrower.engagement.streak.reward')),
        ];
    }

    public function afterOnTimeRepayment(Customer $customer): void
    {
        if (! ($this->config()['enabled'] ?? true)) {
            return;
        }

        $count = $this->engagement->repaymentStreak($customer)['count'] ?? 0;
        foreach ($this->config()['milestones'] ?? [] as $milestone) {
            $target = (int) ($milestone['count'] ?? 0);
            $points = (int) ($milestone['points'] ?? $milestone['percent'] ?? 0);

            if ($target <= 0 || $points <= 0 || $count < $target) {
                continue;
            }

            $this->loyalty->earnCustom(
                $customer,
                $points,
                'repayment_streak_'.$target,
                __('borrower.engagement.streak.points_awarded', ['count' => $target, 'points' => $points]),
                'repayment_streak',
                $target,
            );
        }
    }

    /** @deprecated Streak rewards are now points-based, not fee discounts. */
    public function discountForFee(Customer $customer, string $feeType, float $amountAfterPriorDiscounts): array
    {
        return ['discount' => 0.0, 'percent' => 0.0];
    }

    private function earnedPoints(int $streakCount): int
    {
        $earned = 0;

        foreach ($this->config()['milestones'] ?? [] as $milestone) {
            if ($streakCount >= (int) ($milestone['count'] ?? 0)) {
                $earned = max($earned, (int) ($milestone['points'] ?? $milestone['percent'] ?? 0));
            }
        }

        return $earned;
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return $this->settings->group('repayment_streak')
            ?: config('gamification.repayment_streak', []);
    }
}
