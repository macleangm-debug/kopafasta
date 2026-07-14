<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyPointTransaction;
use Illuminate\Support\Facades\DB;

class LoyaltyPointsService
{
    public function __construct(
        private readonly GamificationSettingsService $settings,
    ) {}

    public function balance(Customer $customer): int
    {
        return (int) ($customer->loyalty_points ?? 0);
    }

    public function earn(Customer $customer, string $actionKey, ?string $description = null, ?string $refType = null, ?int $refId = null): int
    {
        $pointsMap = $this->settings->loyaltyActionPoints();
        $points = (int) ($pointsMap[$actionKey] ?? 0);

        if ($points <= 0) {
            return 0;
        }

        return $this->credit($customer, $points, $actionKey, $description, $refType, $refId);
    }

    public function earnCustom(
        Customer $customer,
        int $points,
        string $actionKey,
        ?string $description = null,
        ?string $refType = null,
        ?int $refId = null,
    ): int {
        if ($points <= 0) {
            return 0;
        }

        return $this->credit($customer, $points, $actionKey, $description, $refType, $refId);
    }

    private function credit(
        Customer $customer,
        int $points,
        string $actionKey,
        ?string $description,
        ?string $refType,
        ?int $refId,
    ): int {
        if ($this->alreadyEarned($customer, $actionKey, $refType, $refId)) {
            return 0;
        }

        return DB::transaction(function () use ($customer, $actionKey, $points, $description, $refType, $refId): int {
            $customer->increment('loyalty_points', $points);

            LoyaltyPointTransaction::create([
                'customer_id'    => $customer->id,
                'type'           => 'credit',
                'points'         => $points,
                'action_key'     => $actionKey,
                'description'    => $description ?? $this->actionLabel($actionKey),
                'reference_type' => $refType,
                'reference_id'   => $refId,
            ]);

            $customer->refresh();

            try {
                app(NotificationService::class)->notifyInApp(
                    $customer,
                    __('borrower.rewards.points_earned_body', ['points' => number_format($points)]),
                    'promotions',
                    'loyalty_points_earned',
                    __('borrower.rewards.points_earned_title'),
                    route('site.borrower.engagement', ['tab' => 'rewards']),
                    __('borrower.rewards.points_earned_cta'),
                    [
                        'title_key' => 'borrower.rewards.points_earned_title',
                        'body_key'  => 'borrower.rewards.points_earned_body',
                        'params'    => ['points' => number_format($points)],
                    ],
                );
            } catch (\Throwable) {
                // Notifications must not block points credit.
            }

            \App\Support\Celebration::flashOne('points_earned');

            return $points;
        });
    }

    public function redeem(Customer $customer, int $points, string $description, ?string $refType = null, ?int $refId = null): bool
    {
        if ($points <= 0 || $this->balance($customer) < $points) {
            return false;
        }

        DB::transaction(function () use ($customer, $points, $description, $refType, $refId): void {
            $customer->decrement('loyalty_points', $points);

            LoyaltyPointTransaction::create([
                'customer_id'    => $customer->id,
                'type'           => 'debit',
                'points'         => -$points,
                'description'    => $description,
                'reference_type' => $refType,
                'reference_id'   => $refId,
            ]);
        });

        return true;
    }

    /**
     * Deduct loyalty points for a penalty action (late repayment, late fee, etc.).
     * Never takes the balance below zero. Idempotent per action + reference.
     */
    public function deductPenalty(
        Customer $customer,
        string $penaltyKey,
        ?string $description = null,
        ?string $refType = null,
        ?int $refId = null,
    ): int {
        $penalty = $this->penaltyConfig($penaltyKey);
        if (! ($penalty['enabled'] ?? false)) {
            return 0;
        }

        $points = (int) ($penalty['points'] ?? 0);
        if ($points <= 0) {
            return 0;
        }

        if ($this->alreadyPenalized($customer, $penaltyKey, $refType, $refId)) {
            return 0;
        }

        $deduct = min($points, $this->balance($customer));
        if ($deduct <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($customer, $penaltyKey, $deduct, $description, $penalty, $refType, $refId): int {
            $customer->decrement('loyalty_points', $deduct);

            LoyaltyPointTransaction::create([
                'customer_id'    => $customer->id,
                'type'           => 'debit',
                'points'         => -$deduct,
                'action_key'     => $penaltyKey,
                'description'    => $description
                    ?? __('borrower.rewards.penalty_description', [
                        'label' => (string) ($penalty['label'] ?? ucfirst(str_replace('_', ' ', $penaltyKey))),
                    ]),
                'reference_type' => $refType,
                'reference_id'   => $refId,
            ]);

            $customer->refresh();

            try {
                app(NotificationService::class)->notifyInApp(
                    $customer,
                    __('borrower.rewards.points_deducted_body', [
                        'points' => number_format($deduct),
                        'reason' => (string) ($penalty['label'] ?? $penaltyKey),
                    ]),
                    'promotions',
                    'loyalty_points_deducted',
                    __('borrower.rewards.points_deducted_title'),
                    route('site.borrower.engagement', ['tab' => 'rewards']),
                    __('borrower.rewards.points_earned_cta'),
                );
            } catch (\Throwable) {
                // Notifications must not block penalty deductions.
            }

            return $deduct;
        });
    }

    /** @return array{label?: string, points?: int, enabled?: bool} */
    private function penaltyConfig(string $penaltyKey): array
    {
        $penalties = $this->settings->group('loyalty_points')['penalties']
            ?? config('gamification.loyalty_points.penalties', []);

        return is_array($penalties[$penaltyKey] ?? null) ? $penalties[$penaltyKey] : [];
    }

    private function alreadyPenalized(Customer $customer, string $penaltyKey, ?string $refType, ?int $refId): bool
    {
        if ($refType === null || $refId === null) {
            return false;
        }

        return LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'debit')
            ->where('action_key', $penaltyKey)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->exists();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, LoyaltyPointTransaction> */
    public function recentTransactions(Customer $customer, int $limit = 10)
    {
        return LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @return list<array{key: string, label: string, points: int}> */
    public function actionCatalog(): array
    {
        $actions = $this->settings->group('loyalty_points')['actions']
            ?? config('gamification.loyalty_points.actions', []);

        return collect($actions)->map(fn (array $action, string $key) => [
            'key'    => $key,
            'label'  => (string) ($action['label'] ?? ucfirst(str_replace('_', ' ', $key))),
            'points' => (int) ($action['points'] ?? 0),
        ])->values()->all();
    }

    /** @return list<string> */
    public function redemptionOptions(): array
    {
        $options = $this->settings->group('loyalty_points')['redemptions']
            ?? config('gamification.loyalty_points.redemptions', []);

        return array_values($options);
    }

    private function alreadyEarned(Customer $customer, string $actionKey, ?string $refType, ?int $refId): bool
    {
        if ($refType === null && ! in_array($actionKey, ['complete_profile', 'refer_friend'], true)) {
            return false;
        }

        $query = LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'credit')
            ->where('action_key', $actionKey);

        if ($refType !== null) {
            $query->where('reference_type', $refType)->where('reference_id', $refId);
        }

        return $query->exists();
    }

    private function actionLabel(string $actionKey): string
    {
        $translated = __('borrower.rewards.actions.'.$actionKey);
        if ($translated !== 'borrower.rewards.actions.'.$actionKey) {
            return $translated;
        }

        $actions = $this->settings->group('loyalty_points')['actions']
            ?? config('gamification.loyalty_points.actions', []);

        return (string) ($actions[$actionKey]['label'] ?? ucfirst(str_replace('_', ' ', $actionKey)));
    }
}
