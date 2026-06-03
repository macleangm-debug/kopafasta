<?php

namespace App\Services;

use App\Models\LoanProduct;

class LoanRateTierTemplateService
{
    /** @return list<array{min_amount: float, max_amount: float, monthly_rate: float, sort_order: int}> */
    public function tiersForProduct(LoanProduct $product): array
    {
        $code = strtoupper((string) $product->code);
        $template = config("loan_product_rate_tiers.templates.{$code}")
            ?? config('loan_product_rate_tiers.default_template', []);

        $minProduct = (float) $product->min_amount;
        $maxProduct = (float) $product->max_amount;
        $rows = [];
        $order = 0;

        foreach ($template as $row) {
            $min = max($minProduct, (float) $row['min_amount']);
            $max = $row['max_amount'] === null
                ? $maxProduct
                : min($maxProduct, (float) $row['max_amount']);

            if ($min > $max) {
                continue;
            }

            $rows[] = [
                'min_amount'   => $min,
                'max_amount'   => $max,
                'monthly_rate' => (float) $row['monthly_rate'],
                'sort_order'   => ++$order,
            ];
        }

        return $rows;
    }

    public function applyDefaults(LoanProduct $product, bool $replaceExisting = false): void
    {
        if ($replaceExisting) {
            $product->rateTiers()->delete();
        } elseif ($product->rateTiers()->exists()) {
            return;
        }

        foreach ($this->tiersForProduct($product) as $tier) {
            $product->rateTiers()->create($tier);
        }
    }

    /** @return list<array{min_amount: float, max_amount: float, monthly_rate: float}> */
    public function previewRows(?string $code = null): array
    {
        $template = $code
            ? (config('loan_product_rate_tiers.templates.'.strtoupper($code)) ?? config('loan_product_rate_tiers.default_template'))
            : config('loan_product_rate_tiers.default_template', []);

        return collect($template)
            ->map(fn (array $row) => [
                'min_amount'   => (float) $row['min_amount'],
                'max_amount'   => $row['max_amount'] === null ? 50_000_000 : (float) $row['max_amount'],
                'monthly_rate' => (float) $row['monthly_rate'],
            ])
            ->values()
            ->all();
    }
}
