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
        $actions = $this->settings->group('loyalty_points')['actions']
            ?? config('gamification.loyalty_points.actions', []);

        return (string) ($actions[$actionKey]['label'] ?? ucfirst(str_replace('_', ' ', $actionKey)));
    }
}
