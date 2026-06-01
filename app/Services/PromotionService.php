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
            ];
        }

        return [
            'promotion_discount' => $promotionDiscount,
            'after_discount'     => max(0, round($amount - $promotionDiscount, 2)),
        ];
    }
}
