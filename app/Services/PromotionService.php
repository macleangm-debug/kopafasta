<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionService
{
    /** @return Collection<int, Promotion> */
    public function active(?string $type = null, ?string $appliesTo = null): Collection
    {
        return Promotion::query()
            ->where('status', 'active')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($appliesTo, fn ($q) => $q->where('applies_to', $appliesTo))
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

    /** @return array{valid: bool, promotion_discount: float, after_discount: float, promotion: Promotion|null} */
    public function applyPromoCode(string $code, string $feeType, float $amount): array
    {
        $promotion = Promotion::query()
            ->where('code', strtoupper(trim($code)))
            ->where('status', 'active')
            ->first();

        if (! $promotion || ! $promotion->isActive()) {
            return [
                'valid'              => false,
                'promotion_discount' => 0.0,
                'after_discount'     => round($amount, 2),
                'promotion'          => null,
            ];
        }

        if ($promotion->applies_to && $promotion->applies_to !== $feeType && $promotion->applies_to !== 'all') {
            return [
                'valid'              => false,
                'promotion_discount' => 0.0,
                'after_discount'     => round($amount, 2),
                'promotion'          => null,
            ];
        }

        $discount = 0.0;
        if ($promotion->discount_amount) {
            $discount = min($amount, (float) $promotion->discount_amount);
        } elseif ($promotion->discount_percent) {
            $discount = round($amount * ((float) $promotion->discount_percent / 100), 2);
        }

        return [
            'valid'              => $discount > 0,
            'promotion_discount' => $discount,
            'after_discount'     => max(0, round($amount - $discount, 2)),
            'promotion'          => $promotion,
        ];
    }
}
