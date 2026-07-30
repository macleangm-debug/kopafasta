<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyRedemption;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoyaltyRedemptionService
{
    public function __construct(
        private readonly LoyaltyPointsService $points,
        private readonly GamificationSettingsService $settings,
    ) {}

    /** @return list<array<string, mixed>> */
    public function catalog(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $options = $this->redemptionOptions();

        return collect($options)->map(function (array $option) use ($locale) {
            $label = $locale === 'sw' && filled($option['label_sw'] ?? null)
                ? $option['label_sw']
                : ($option['label'] ?? $option['key']);

            return [
                'key'          => (string) ($option['key'] ?? ''),
                'label'        => (string) $label,
                'points'       => (int) ($option['points'] ?? 0),
                'benefit_type' => (string) ($option['benefit_type'] ?? 'percent_discount'),
                'benefit_value'=> (float) ($option['benefit_value'] ?? 0),
                'fee_type'     => $option['fee_type'] ?? null,
                'description'  => $locale === 'sw' && filled($option['description_sw'] ?? null)
                    ? $option['description_sw']
                    : ($option['description'] ?? null),
            ];
        })->values()->all();
    }

    public function redeem(Customer $customer, string $optionKey): LoyaltyRedemption
    {
        $option = collect($this->redemptionOptions())->firstWhere('key', $optionKey);
        if (! $option) {
            throw new InvalidArgumentException('Unknown redemption option.');
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

            return LoyaltyRedemption::create([
                'customer_id'   => $customer->id,
                'option_key'    => $optionKey,
                'label'         => (string) ($option['label'] ?? $optionKey),
                'benefit_type'  => $benefitType,
                'benefit_value' => (float) ($option['benefit_value'] ?? 0),
                'fee_type'      => $option['fee_type'] ?? null,
                'points_spent'  => $pointsCost,
                'status'        => 'active',
                'expires_at'    => now()->addDays(max(1, $expiresDays)),
            ]);
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

        $discount = match ($redemption->benefit_type) {
            'percent_discount' => round($baseAmount * ((float) $redemption->benefit_value / 100), 2),
            'fixed_discount'   => min($baseAmount, (float) $redemption->benefit_value),
            default            => 0.0,
        };

        return [
            'discount'   => max(0.0, $discount),
            'redemption' => $redemption,
            'label'      => $redemption->label,
        ];
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

    /**
     * Best application-fee redemption the customer can claim inline at checkout.
     * Hidden unless they have enough points and no overlapping active fee reward.
     *
     * @return array{key: string, label: string, points: int, benefit_type: string, benefit_value: float, can_redeem: bool, save_estimate: float}|null
     */
    public function availableApplicationFeeOption(Customer $customer, float $feeBase = 0): ?array
    {
        if ($this->activeForFee($customer, 'application_fee')) {
            return null;
        }

        $option = collect($this->redemptionOptions())
            ->filter(fn (array $row) => ($row['fee_type'] ?? null) === 'application_fee')
            ->filter(fn (array $row) => in_array(($row['benefit_type'] ?? ''), ['percent_discount', 'fixed_discount'], true))
            ->sortByDesc(fn (array $row) => (float) ($row['benefit_value'] ?? 0))
            ->first();

        if (! $option) {
            return null;
        }

        $pointsCost = (int) ($option['points'] ?? 0);
        $balance = $this->points->balance($customer);
        if ($pointsCost <= 0 || $balance < $pointsCost) {
            return null;
        }

        $benefitType = (string) ($option['benefit_type'] ?? 'percent_discount');
        $benefitValue = (float) ($option['benefit_value'] ?? 0);
        $saveEstimate = match ($benefitType) {
            'fixed_discount' => min(max(0, $feeBase), $benefitValue),
            default => round(max(0, $feeBase) * ($benefitValue / 100), 0),
        };

        $locale = app()->getLocale();
        $label = $locale === 'sw' && filled($option['label_sw'] ?? null)
            ? $option['label_sw']
            : ($option['label'] ?? $option['key']);

        return [
            'key'           => (string) ($option['key'] ?? ''),
            'label'         => (string) $label,
            'points'        => $pointsCost,
            'benefit_type'  => $benefitType,
            'benefit_value' => $benefitValue,
            'can_redeem'    => true,
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
        $redemption->update([
            'status'         => 'used',
            'used_at'        => now(),
            'reference_type' => $refType,
            'reference_id'   => $refId,
        ]);
    }

    private function activeForFee(Customer $customer, string $feeType): ?LoyaltyRedemption
    {
        return LoyaltyRedemption::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereIn('benefit_type', ['percent_discount', 'fixed_discount'])
            ->where(function ($q) use ($feeType) {
                $q->whereNull('fee_type')->orWhere('fee_type', $feeType);
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('benefit_value')
            ->first();
    }

    private function hasActiveOption(Customer $customer, string $optionKey, string $benefitType): bool
    {
        if ($benefitType === 'support_flag') {
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
