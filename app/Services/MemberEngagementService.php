<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Carbon;

class MemberEngagementService
{
    public function __construct(
        private readonly GamificationSettingsService $settings,
        private readonly ProfileCompletionService $profileCompletion,
        private readonly ReferralService $referrals,
    ) {}

    /** @return array<string, mixed> */
    public function summary(Customer $customer): array
    {
        $profile = $this->profileCompletion->calculate($customer);
        $percent = (int) ($profile['percent'] ?? 0);

        return [
            'profile_completion' => $percent,
            'profile_strength'   => $this->profileStrength($percent),
            'trust_score'        => $this->trustScore($customer),
            'referral'           => $this->referralProgress($customer),
            'repayment_streak'   => $this->repaymentStreak($customer),
            'loyalty_points'     => (int) ($customer->loyalty_points ?? 0),
            'community'          => $this->communityMilestone($customer),
        ];
    }

    /** @return array{stars: float, filled: int, max: int, percent: int, factors: list<array{key: string, label: string, score: int, max: int}>} */
    public function trustScore(Customer $customer): array
    {
        $config = $this->settings->group('trust_score');
        $weights = $config['weights'] ?? config('gamification.trust_score.weights', []);
        $maxStars = (int) ($config['max_stars'] ?? 5);

        $factors = [
            'on_time_payments' => [
                'label' => __('borrower.engagement.trust.on_time_payments'),
                'score' => $this->onTimePaymentScore($customer),
                'max'   => 100,
            ],
            'profile_completion' => [
                'label' => __('borrower.engagement.trust.profile_completion'),
                'score' => $this->profileCompletionTrustScore($customer),
                'max'   => 100,
            ],
            'referrals' => [
                'label' => __('borrower.engagement.trust.referrals'),
                'score' => min(100, $this->referrals->successfulReferralCount($customer) * 20),
                'max'   => 100,
            ],
            'account_age' => [
                'label' => __('borrower.engagement.trust.account_age'),
                'score' => $this->accountAgeScore($customer),
                'max'   => 100,
            ],
            'successful_loans' => [
                'label' => __('borrower.engagement.trust.successful_loans'),
                'score' => min(100, $this->successfulLoansCount($customer) * 25),
                'max'   => 100,
            ],
        ];

        $weighted = 0.0;
        $weightTotal = max(1, array_sum($weights));

        foreach ($factors as $key => $factor) {
            $weight = (float) ($weights[$key] ?? 0);
            $weighted += ($factor['score'] / max(1, $factor['max'])) * $weight;
        }

        $percent = (int) round(($weighted / $weightTotal) * 100);
        $filled = (int) round(($percent / 100) * $maxStars);

        return [
            'stars'    => round($filled + (($percent % (100 / $maxStars)) / (100 / $maxStars)), 1),
            'filled'   => $filled,
            'max'      => $maxStars,
            'percent'  => $percent,
            'factors'  => collect($factors)->map(fn (array $f, string $key) => [
                'key'   => $key,
                'label' => $f['label'],
                'score' => $f['score'],
                'max'   => $f['max'],
            ])->values()->all(),
            'benefits' => $config['benefits'] ?? config('gamification.trust_score.benefits', []),
        ];
    }

    /** @return array{key: string, label: string, min: int, max: int|null, benefits: list<string>} */
    public function referralLevel(Customer $customer): array
    {
        $count = $this->referrals->successfulReferralCount($customer);
        $levels = $this->settings->referralLevels();
        $benefits = array_replace(
            config('gamification.referral_level_benefits', []),
            $this->settings->group('referral_level_benefits') ?: []
        );

        foreach (array_reverse($levels) as $level) {
            $min = (int) ($level['min_referrals'] ?? 0);
            $max = isset($level['max_referrals']) ? (int) $level['max_referrals'] : null;

            if ($count >= $min && ($max === null || $count <= $max)) {
                $key = (string) ($level['key'] ?? 'bronze');

                return [
                    'key'      => $key,
                    'label'    => (string) ($level['label'] ?? ucfirst($key)),
                    'min'      => $min,
                    'max'      => $max,
                    'count'    => $count,
                    'benefits' => $benefits[$key] ?? $benefits['benefits'][$key] ?? [],
                ];
            }
        }

        return [
            'key'      => 'bronze',
            'label'    => 'Bronze',
            'min'      => 0,
            'max'      => 5,
            'count'    => $count,
            'benefits' => $benefits['bronze'] ?? [],
        ];
    }

    /** @return array{current: int, target: int, next_reward: string|null, progress_percent: int, level: array<string, mixed>} */
    public function referralProgress(Customer $customer): array
    {
        $count = $this->referrals->successfulReferralCount($customer);
        $milestones = $this->settings->referralMilestones();
        $next = collect($milestones)->first(fn (array $m) => $count < (int) ($m['target'] ?? 0));
        $target = (int) ($next['target'] ?? ($milestones[0]['target'] ?? 5));
        $previousTarget = collect($milestones)
            ->filter(fn (array $m) => $count >= (int) ($m['target'] ?? 0))
            ->max('target') ?? 0;

        $span = max(1, $target - (int) $previousTarget);
        $progress = min(100, (int) round((($count - $previousTarget) / $span) * 100));

        return [
            'current'           => $count,
            'target'            => $target,
            'next_reward'       => $next['reward_label'] ?? null,
            'progress_percent'  => $progress,
            'level'             => $this->referralLevel($customer),
        ];
    }

    /** @return array{key: string, label: string, percent: int} */
    public function profileStrength(int $percent): array
    {
        $tiers = $this->settings->profileStrengthTiers();

        foreach ($tiers as $tier) {
            $min = (int) ($tier['min_percent'] ?? 0);
            $max = (int) ($tier['max_percent'] ?? 100);

            if ($percent >= $min && $percent <= $max) {
                return [
                    'key'     => (string) ($tier['key'] ?? 'bronze'),
                    'label'   => (string) ($tier['label'] ?? 'Bronze'),
                    'percent' => $percent,
                ];
            }
        }

        return ['key' => 'bronze', 'label' => 'Bronze', 'percent' => $percent];
    }

    /** @return array{count: int, reward_label: string|null, emoji: string} */
    public function repaymentStreak(Customer $customer): array
    {
        $config = $this->settings->group('repayment_streak');
        $count = $this->consecutiveOnTimeRepayments($customer);

        return [
            'count'         => $count,
            'reward_label'  => $config['reward_label'] ?? __('borrower.engagement.streak.reward'),
            'emoji'         => '🔥',
            'enabled'       => (bool) ($config['enabled'] ?? true),
        ];
    }

    /** @return array{target: int, current: int, title: string, rewards: list<string>} */
    public function communityMilestone(Customer $customer): array
    {
        $milestones = $this->settings->group('community_milestones')['milestones']
            ?? config('gamification.community_milestones', []);
        $current = $this->referrals->successfulReferralCount($customer);
        $active = collect($milestones)->first() ?? ['target' => 5, 'title' => 'Help 5 people join', 'rewards' => []];

        return [
            'target'  => (int) ($active['target'] ?? 5),
            'current' => $current,
            'title'   => (string) ($active['title'] ?? ''),
            'rewards' => $active['rewards'] ?? [],
        ];
    }

    private function profileCompletionTrustScore(Customer $customer): int
    {
        $percent = (int) ($this->profileCompletion->calculate($customer)['percent'] ?? 0);

        // Missing physical NIDA photos reduces trust until underwriting collects them.
        if ($customer->no_physical_nida_card) {
            $percent = max(0, $percent - 15);
        }

        return $percent;
    }

    private function onTimePaymentScore(Customer $customer): int
    {
        $schedules = RepaymentSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereIn('status', ['paid', 'partial', 'overdue'])
            ->get();

        if ($schedules->isEmpty()) {
            return 50;
        }

        $onTime = $schedules->where('status', 'paid')
            ->filter(fn (RepaymentSchedule $s) => $s->paid_at && $s->due_date && Carbon::parse($s->paid_at)->lte(Carbon::parse($s->due_date)->endOfDay()))
            ->count();

        return (int) round(($onTime / max(1, $schedules->count())) * 100);
    }

    private function accountAgeScore(Customer $customer): int
    {
        $created = $customer->created_at ?? now();
        $months = Carbon::parse($created)->diffInMonths(now());

        return min(100, (int) round($months * 8));
    }

    private function successfulLoansCount(Customer $customer): int
    {
        return Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['closed', 'completed', 'active', 'disbursed'])
            ->count();
    }

    private function consecutiveOnTimeRepayments(Customer $customer): int
    {
        $paid = RepaymentSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->limit(24)
            ->get();

        $streak = 0;
        foreach ($paid as $schedule) {
            if ($schedule->due_date && Carbon::parse($schedule->paid_at)->lte(Carbon::parse($schedule->due_date)->endOfDay())) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }
}
