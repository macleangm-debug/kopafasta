<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;

class LoanRateTierService
{
    /** Resolve monthly interest rate for a principal amount. Falls back to product default rate. */
    public function resolveRate(LoanProduct $product, float $amount): float
    {
        $tier = LoanProductRateTier::query()
            ->where('loan_product_id', $product->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('sort_order')
            ->first();

        if ($tier) {
            return (float) $tier->monthly_rate;
        }

        return (float) $product->interest_rate;
    }

    /** @return list<array{min: float, max: float, rate: float}> */
    public function tiersForProduct(LoanProduct $product): array
    {
        return LoanProductRateTier::query()
            ->where('loan_product_id', $product->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (LoanProductRateTier $t) => [
                'min'  => (float) $t->min_amount,
                'max'  => (float) $t->max_amount,
                'rate' => (float) $t->monthly_rate,
            ])
            ->all();
    }
}
