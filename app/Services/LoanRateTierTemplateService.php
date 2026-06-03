<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;

class LoanRateTierTemplateService
{
    /** @return list<array<string, mixed>> */
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

            $normalized = $this->normalizeTierRow($row);

            $rows[] = [
                'min_amount'              => $min,
                'max_amount'              => $max,
                'bot_regulated_rate'      => $normalized['bot_regulated_rate'],
                'processing_fee_rate'     => $normalized['processing_fee_rate'],
                'service_fee_rate'        => $normalized['service_fee_rate'],
                'administration_fee_rate' => $normalized['administration_fee_rate'],
                'monthly_rate'            => $normalized['monthly_rate'],
                'sort_order'              => ++$order,
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

    /** @return list<array<string, mixed>> */
    public function previewRows(?string $code = null): array
    {
        $template = $code
            ? (config('loan_product_rate_tiers.templates.'.strtoupper($code)) ?? config('loan_product_rate_tiers.default_template'))
            : config('loan_product_rate_tiers.default_template', []);

        return collect($template)
            ->map(function (array $row) {
                $normalized = $this->normalizeTierRow($row);

                return [
                    'min_amount'              => (float) $row['min_amount'],
                    'max_amount'              => $row['max_amount'] === null ? 50_000_000 : (float) $row['max_amount'],
                    'bot_regulated_rate'      => $normalized['bot_regulated_rate'],
                    'processing_fee_rate'     => $normalized['processing_fee_rate'],
                    'service_fee_rate'        => $normalized['service_fee_rate'],
                    'administration_fee_rate' => $normalized['administration_fee_rate'],
                    'monthly_rate'            => $normalized['monthly_rate'],
                ];
            })
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $row */
    public function normalizeTierRow(array $row): array
    {
        if (isset($row['bot_regulated_rate']) || isset($row['processing_fee_rate'])) {
            $monthly = LoanProductRateTier::totalFromComponents(
                $row['bot_regulated_rate'] ?? 0,
                $row['processing_fee_rate'] ?? 0,
                $row['service_fee_rate'] ?? 0,
                $row['administration_fee_rate'] ?? 0,
            );

            return [
                'bot_regulated_rate'      => min((float) ($row['bot_regulated_rate'] ?? 0), LoanProductRateTier::BOT_MAX),
                'processing_fee_rate'     => (float) ($row['processing_fee_rate'] ?? 0),
                'service_fee_rate'        => (float) ($row['service_fee_rate'] ?? 0),
                'administration_fee_rate' => (float) ($row['administration_fee_rate'] ?? 0),
                'monthly_rate'            => $monthly,
            ];
        }

        return self::splitTotalIntoComponents((float) ($row['monthly_rate'] ?? 0));
    }

    /** @return array{bot_regulated_rate: float, processing_fee_rate: float, service_fee_rate: float, administration_fee_rate: float, monthly_rate: float} */
    public static function splitTotalIntoComponents(float $total): array
    {
        $bot = min(LoanProductRateTier::BOT_MAX, max(0, $total));
        $remaining = max(0, $total - $bot);
        $processing = min(0.05, round($remaining * 0.42, 4));
        $remaining = max(0, $remaining - $processing);
        $risk = min(0.035, round($remaining * 0.85, 4));
        $insurance = max(0, round($total - $bot - $processing - $risk, 4));

        return [
            'bot_regulated_rate'      => $bot,
            'processing_fee_rate'     => $processing,
            'service_fee_rate'        => $risk,
            'administration_fee_rate' => $insurance,
            'monthly_rate'            => round($bot + $processing + $risk + $insurance, 4),
        ];
    }
}
