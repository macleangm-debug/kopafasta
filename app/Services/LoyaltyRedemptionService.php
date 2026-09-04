<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyRedemption;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Services\Plus\PlusService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LoyaltyRedemptionService
{
    public function __construct(
        private readonly LoyaltyPointsService $points,
        private readonly GamificationSettingsService $settings,
    ) {}

    /** @return list<array<string, mixed>> */
    public function catalog(?string $locale = null, ?Customer $customer = null): array
    {
        $locale ??= app()->getLocale();
        $plusActive = $customer ? app(PlusService::class)->isActive($customer) : false;

        return collect($this->redemptionOptions())->map(function (array $option) use ($locale, $plusActive, $customer) {
            $label = $locale === 'sw' && filled($option['label_sw'] ?? null)
                ? $option['label_sw']
                : ($option['label'] ?? $option['key']);
            $audience = (string) ($option['audience'] ?? 'everyone');
            $plusOnly = $audience === 'plus_only';
            $eligible = ! $plusOnly || $plusActive;
            $cost = (int) ($option['points'] ?? 0);
            $balance = $customer ? $this->points->balance($customer) : 0;

            return [
                'key' => (string) ($option['key'] ?? ''),
                'label' => (string) $label,
                'points' => $cost,
                'benefit_type' => (string) ($option['benefit_type'] ?? 'percent_discount'),
                'benefit_value' => (float) ($option['benefit_value'] ?? 0),
                'fee_type' => $option['fee_type'] ?? null,
                'audience' => $audience,
                'plus_only' => $plusOnly,
                'eligible' => $eligible,
                'unlocked' => $eligible && $customer && $balance >= $cost,
                'shortfall' => $customer ? max(0, $cost - $balance) : $cost,
                'max_saving' => $option['max_saving'] ?? null,
                'stackable' => (bool) ($option['stackable'] ?? false),
                'description' => $locale === 'sw' && filled($option['description_sw'] ?? null)
                    ? $option['description_sw']
                    : ($option['description'] ?? null),
            ];
        })->values()->all();
    }

    /**
     * Onboarding copy from Rewards Settings. Never invent a fake balance or a hard-coded fee %.
     */
    public function onboardingHint(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $feeOptions = collect($this->redemptionOptions())
            ->filter(function (array $option) {
                $type = (string) ($option['benefit_type'] ?? '');
                $fee = (string) ($option['fee_type'] ?? '');

                return in_array($type, ['percent_discount', 'fixed_discount', 'fee_waiver'], true)
                    && ($fee === '' || $fee === 'application_fee');
            });

        if ($feeOptions->isEmpty()) {
            return '';
        }

        $percents = $feeOptions
            ->filter(fn (array $option) => ($option['benefit_type'] ?? '') === 'percent_discount')
            ->map(fn (array $option) => (int) ($option['benefit_value'] ?? 0))
            ->filter()
            ->unique()
            ->values();

        if ($percents->count() === 1) {
            return __('account_welcome.borrower.points_fee_percent', ['percent' => $percents->first()], $locale);
        }

        return __('account_welcome.borrower.points_fee_generic', [], $locale);
    }

    public function publicCatalog(?string $locale = null): array
    {
        return collect($this->catalog($locale))
            ->filter(fn (array $row) => ($row['audience'] ?? 'everyone') !== 'plus_only')
            ->values()
            ->all();
    }

    /**
     * Member Rewards mini-dashboard.
     *
     * @return array<string, mixed>
     */
    public function dashboard(Customer $customer): array
    {
        $balance = $this->points->balance($customer);
        $catalog = $this->catalog(null, $customer);
        $active = $this->activeRewards($customer);
        $activeKeys = $active->pluck('option_key')->all();

        $claimable = collect($catalog)
            ->filter(fn (array $row) => ($row['unlocked'] ?? false) && ! in_array($row['key'], $activeKeys, true))
            ->values()
            ->all();

        $locked = collect($catalog)
            ->filter(fn (array $row) => ! ($row['unlocked'] ?? false))
            ->sortBy('shortfall')
            ->values()
            ->all();

        $next = collect($catalog)
            ->filter(fn (array $row) => ($row['eligible'] ?? true) && ($row['shortfall'] ?? 0) > 0)
            ->sortBy('shortfall')
            ->first();

        $nextCost = (int) ($next['points'] ?? 0);
        $progress = $nextCost > 0 ? min(100, (int) round(($balance / $nextCost) * 100)) : 100;

        return [
            'balance' => $balance,
            'next' => $next,
            'to_next' => (int) ($next['shortfall'] ?? 0),
            'progress' => $progress,
            'claimable' => $claimable,
            'locked' => $locked,
            'all' => $catalog,
            'active' => $active,
            'history' => $this->history($customer, 20),
            'ledger' => $this->points->recentTransactions($customer, 20),
        ];
    }

    public function redeem(Customer $customer, string $optionKey): LoyaltyRedemption
    {
        if (app(GrowthPointsService::class)->isNonEarnable($customer)) {
            throw new InvalidArgumentException(__('borrower.rewards.demo_blocked'));
        }

        $option = collect($this->redemptionOptions())->firstWhere('key', $optionKey);
        if (! $option) {
            throw new InvalidArgumentException('Unknown redemption option.');
        }

        $audience = (string) ($option['audience'] ?? 'everyone');
        if ($audience === 'plus_only' && ! app(PlusService::class)->isActive($customer)) {
            throw new InvalidArgumentException(__('borrower.rewards.plus_only'));
        }

        $pointsCost = (int) ($option['points'] ?? 0);
        if ($pointsCost <= 0) {
            throw new InvalidArgumentException('This reward is not available.');
        }

        if ($this->points->balance($customer) < $pointsCost) {
            throw new InvalidArgumentException(__('borrower.rewards.insufficient_points'));
        }

        $benefitType = (string) ($option['benefit_type'] ?? 'percent_discount');
        if ($this->hasActiveOption($customer, $optionKey, $benefitType)) {
            throw new InvalidArgumentException(__('borrower.rewards.already_active'));
        }

        return DB::transaction(function () use ($customer, $option, $optionKey, $pointsCost, $benefitType): LoyaltyRedemption {
            if (! $this->points->redeem($customer, $pointsCost, 'Redeemed: '.($option['label'] ?? $optionKey), 'loyalty_redemption', null)) {
                throw new InvalidArgumentException(__('borrower.rewards.insufficient_points'));
            }

            $expiresDays = (int) ($option['expires_days'] ?? 90);

            $redemption = LoyaltyRedemption::create([
                'customer_id' => $customer->id,
                'option_key' => $optionKey,
                'label' => (string) ($option['label'] ?? $optionKey),
                'benefit_type' => $benefitType,
                'benefit_value' => (float) ($option['benefit_value'] ?? 0),
                'fee_type' => $option['fee_type'] ?? null,
                'points_spent' => $pointsCost,
                'status' => 'active',
                'expires_at' => now()->addDays(max(1, $expiresDays)),
            ]);

            $this->applyImmediateEffect($customer, $redemption, $option);

            return $redemption;
        });
    }

    /** @return array{discount: float, redemption: LoyaltyRedemption|null, label: string|null} */
    public function discountForFee(Customer $customer, string $feeType, float $baseAmount): array
    {
        if ($baseAmount <= 0) {
            return ['discount' => 0.0, 'redemption' => null, 'label' => null];
        }

        $redemption = $this->activeForFee($customer, $feeType);
        if (! $redemption) {
            return ['discount' => 0.0, 'redemption' => null, 'label' => null];
        }

        $discount = $this->discountAmount($redemption, $baseAmount);

        return [
            'discount' => max(0.0, $discount),
            'redemption' => $redemption,
            'label' => $redemption->label,
        ];
    }

    public function discountAmount(LoyaltyRedemption $redemption, float $baseAmount): float
    {
        $raw = match ($redemption->benefit_type) {
            'percent_discount' => round($baseAmount * ((float) $redemption->benefit_value / 100), 2),
            'fixed_discount' => min($baseAmount, (float) $redemption->benefit_value),
            'fee_waiver' => $baseAmount,
            default => 0.0,
        };

        $option = collect($this->redemptionOptions())->firstWhere('key', $redemption->option_key);
        $max = isset($option['max_saving']) && $option['max_saving'] !== null && $option['max_saving'] !== ''
            ? (float) $option['max_saving']
            : null;

        if ($max !== null && $max > 0) {
            $raw = min($raw, $max);
        }

        return max(0.0, min($baseAmount, $raw));
    }

    public function additionalRateDiscount(Customer $customer): float
    {
        $redemption = LoyaltyRedemption::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where('benefit_type', 'rate_discount')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();

        return $redemption ? (float) $redemption->benefit_value : 0.0;
    }

    public function activePrioritySupport(Customer $customer): ?LoyaltyRedemption
    {
        return LoyaltyRedemption::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where('benefit_type', 'support_flag')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();
    }

    /**
     * Best already-unlocked fee reward for this checkout — not a points claim.
     *
     * @return array{id: int, key: string, label: string, discount: float, benefit_type: string}|null
     */
    public function walletRewardForFee(Customer $customer, string $feeType, float $feeBase = 0): ?array
    {
        $redemption = $this->activeForFee($customer, $feeType);
        if (! $redemption) {
            return null;
        }

        return [
            'id' => (int) $redemption->id,
            'key' => (string) $redemption->option_key,
            'label' => (string) $redemption->label,
            'discount' => $this->discountAmount($redemption, $feeBase),
            'benefit_type' => (string) $redemption->benefit_type,
        ];
    }

    /**
     * Best application-fee redemption the customer can claim inline at checkout.
     *
     * @return array{key: string, label: string, points: int, benefit_type: string, benefit_value: float, can_redeem: bool, save_estimate: float}|null
     */
    public function availableApplicationFeeOption(Customer $customer, float $feeBase = 0): ?array
    {
        if ($this->activeForFee($customer, 'application_fee')) {
            return null;
        }

        $option = collect($this->catalog(null, $customer))
            ->filter(fn (array $row) => ($row['fee_type'] ?? null) === 'application_fee')
            ->filter(fn (array $row) => in_array(($row['benefit_type'] ?? ''), ['percent_discount', 'fixed_discount', 'fee_waiver'], true))
            ->filter(fn (array $row) => $row['unlocked'] ?? false)
            ->sortByDesc(fn (array $row) => (float) ($row['benefit_value'] ?? 0))
            ->first();

        if (! $option) {
            return null;
        }

        $benefitType = (string) ($option['benefit_type'] ?? 'percent_discount');
        $benefitValue = (float) ($option['benefit_value'] ?? 0);
        $saveEstimate = match ($benefitType) {
            'fixed_discount' => min(max(0, $feeBase), $benefitValue),
            'fee_waiver' => max(0, $feeBase),
            default => round(max(0, $feeBase) * ($benefitValue / 100), 0),
        };

        return [
            'key' => (string) ($option['key'] ?? ''),
            'label' => (string) ($option['label'] ?? ''),
            'points' => (int) ($option['points'] ?? 0),
            'benefit_type' => $benefitType,
            'benefit_value' => $benefitValue,
            'can_redeem' => true,
            'save_estimate' => (float) $saveEstimate,
        ];
    }

    /** @return Collection<int, LoyaltyRedemption> */
    public function activeRewards(Customer $customer): Collection
    {
        return LoyaltyRedemption::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get();
    }

    /** @return Collection<int, LoyaltyRedemption> */
    public function history(Customer $customer, int $limit = 20): Collection
    {
        return LoyaltyRedemption::query()
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function markUsed(LoyaltyRedemption $redemption, ?string $refType = null, ?int $refId = null): void
    {
        if ($redemption->status === 'used') {
            return;
        }

        $redemption->update([
            'status' => 'used',
            'used_at' => $redemption->used_at ?? now(),
            'reference_type' => $refType ?? $redemption->reference_type,
            'reference_id' => $refId ?? $redemption->reference_id,
        ]);
    }

    public function activeForFee(Customer $customer, string $feeType): ?LoyaltyRedemption
    {
        return LoyaltyRedemption::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereIn('benefit_type', ['percent_discount', 'fixed_discount', 'fee_waiver'])
            ->where(function ($q) use ($feeType) {
                $q->whereNull('fee_type')->orWhere('fee_type', $feeType);
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('benefit_value')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $option
     */
    private function applyImmediateEffect(Customer $customer, LoyaltyRedemption $redemption, array $option): void
    {
        $type = (string) ($option['benefit_type'] ?? $redemption->benefit_type);

        if ($type === 'fulfilment_task' || $type === 'partner_benefit') {
            SupportTicket::create([
                'ticket_number' => 'RWD-'.now()->format('ymd').'-'.Str::upper(Str::random(4)),
                'customer_id' => $customer->id,
                'subject' => 'Reward fulfilment: '.$redemption->label,
                'description' => 'Customer unlocked "'.$redemption->label.'". Fulfil this reward. Redemption #'.$redemption->id.'.',
                'priority' => 'high',
                'status' => 'open',
                'category' => 'reward_fulfilment',
            ]);
        }
    }

    private function hasActiveOption(Customer $customer, string $optionKey, string $benefitType): bool
    {
        if (in_array($benefitType, ['support_flag', 'fulfilment_task', 'partner_benefit'], true)) {
            return false;
        }

        return LoyaltyRedemption::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($optionKey, $benefitType) {
                $q->where('option_key', $optionKey)
                    ->orWhere('benefit_type', $benefitType);
            })
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    private function redemptionOptions(): array
    {
        $stored = Setting::get('gamification.loyalty_points.redemption_options');

        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        return config('gamification.loyalty_points.redemption_options', []);
    }
}
