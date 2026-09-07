<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionService
{
    /** Fee types promotions may discount — never interest or penalties. */
    public const FEE_APPLIES_TO = [
        'registration_fee',
        'application_fee',
        'post_approval_fee',
        'valuation_fee',
        'membership_fee',
        'all',
    ];

    public static function isAllowedAppliesTo(?string $appliesTo): bool
    {
        if ($appliesTo === null || $appliesTo === '') {
            return true;
        }

        return in_array($appliesTo, self::FEE_APPLIES_TO, true);
    }

    /** @return Collection<int, Promotion> */
    public function active(?string $type = null, ?string $appliesTo = null): Collection
    {
        return Promotion::query()
            ->where('status', 'active')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($appliesTo, function ($q) use ($appliesTo) {
                $q->where(function ($inner) use ($appliesTo) {
                    $inner->where('applies_to', $appliesTo)
                        ->orWhere('applies_to', 'all')
                        ->orWhereNull('applies_to');
                });
            })
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->isActive())
            ->values();
    }

    public function birthdayMessage(Customer $customer): ?string
    {
        $promotion = $this->active('birthday')->first();
        if (! $promotion) {
            return null;
        }

        $template = $promotion->message_template
            ?: 'Happy birthday, :name! From all of us at KopaFasta — enjoy a special offer on your next fee.';

        return str_replace(
            [':name', ':first_name'],
            [$customer->first_name ?? 'member', $customer->first_name ?? 'member'],
            $template
        );
    }

    public function discountForFee(string $feeType, float $baseAmount): float
    {
        if (! self::isAllowedAppliesTo($feeType)) {
            return 0.0;
        }

        $promotion = $this->active(type: 'fee_discount', appliesTo: $feeType)->first();
        if (! $promotion) {
            return 0.0;
        }

        if ($promotion->discount_amount) {
            return min($baseAmount, (float) $promotion->discount_amount);
        }

        if ($promotion->discount_percent) {
            return round($baseAmount * ((float) $promotion->discount_percent / 100), 2);
        }

        return 0.0;
    }

    public function applyAfter(string $feeType, float $amount): array
    {
        $promotionDiscount = $this->discountForFee($feeType, $amount);
        if ($promotionDiscount <= 0) {
            return [
                'promotion_discount' => 0.0,
                'after_discount'     => round($amount, 2),
                'promotion'          => null,
            ];
        }

        return [
            'promotion_discount' => $promotionDiscount,
            'after_discount'     => max(0, round($amount - $promotionDiscount, 2)),
            'promotion'          => $this->active(type: 'fee_discount', appliesTo: $feeType)->first(),
        ];
    }

    /** @return array{valid: bool, promotion_discount: float, after_discount: float, promotion: Promotion|null, reason: string|null} */
    public function applyPromoCode(string $code, string $feeType, float $amount): array
    {
        $normalized = strtoupper(trim($code));
        $promotion = Promotion::query()
            ->where('code', $normalized)
            ->first();

        if (! $promotion) {
            return $this->invalidPromoResult($amount, 'not_found');
        }

        $today = now()->toDateString();
        if ($promotion->ends_at && $promotion->ends_at->toDateString() < $today) {
            return $this->invalidPromoResult($amount, 'expired', $promotion);
        }

        if ($promotion->starts_at && $promotion->starts_at->toDateString() > $today) {
            return $this->invalidPromoResult($amount, 'inactive', $promotion);
        }

        if ($promotion->status !== 'active' || ! $promotion->isActive()) {
            return $this->invalidPromoResult($amount, 'inactive', $promotion);
        }

        if (! self::isAllowedAppliesTo($promotion->applies_to)) {
            return $this->invalidPromoResult($amount, 'wrong_fee', $promotion);
        }

        if ($promotion->applies_to && $promotion->applies_to !== $feeType && $promotion->applies_to !== 'all') {
            return $this->invalidPromoResult($amount, 'wrong_fee', $promotion);
        }

        $uses = (int) data_get($promotion->metadata, 'uses', 0);
        $maxUses = data_get($promotion->metadata, 'max_uses');
        if ($maxUses !== null && (int) $maxUses > 0 && $uses >= (int) $maxUses) {
            return $this->invalidPromoResult($amount, 'exhausted', $promotion);
        }

        $discount = 0.0;
        if ($promotion->discount_amount) {
            $discount = min($amount, (float) $promotion->discount_amount);
        } elseif ($promotion->discount_percent) {
            $discount = round($amount * ((float) $promotion->discount_percent / 100), 2);
        }

        if ($discount <= 0) {
            return $this->invalidPromoResult($amount, 'wrong_fee', $promotion);
        }

        return [
            'valid' => true,
            'promotion_discount' => $discount,
            'after_discount' => max(0, round($amount - $discount, 2)),
            'promotion' => $promotion,
            'reason' => null,
        ];
    }

    /**
     * @return array{valid: bool, promotion_discount: float, after_discount: float, promotion: Promotion|null, reason: string}
     */
    private function invalidPromoResult(float $amount, string $reason, ?Promotion $promotion = null): array
    {
        return [
            'valid' => false,
            'promotion_discount' => 0.0,
            'after_discount' => round($amount, 2),
            'promotion' => $promotion,
            'reason' => $reason,
        ];
    }
}
