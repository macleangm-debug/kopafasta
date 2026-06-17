<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;
use App\Support\RatePercent;

class LoanRateTierTemplateService
{
    public function tiersForProduct(LoanProduct $product): array
    {
        $minAmount = (float) $product->min_amount;
        $maxAmount = (float) $product->max_amount;

        if ($minAmount <= 0 || $maxAmount <= 0 || $maxAmount < $minAmount) {
            return [];
        }

        $code = strtoupper((string) $product->code);
        $overrides = config("loan_product_rate_tiers.products.{$code}", []);
        $tierCount = (int) ($overrides['tier_count'] ?? app(UnderwritingSettingsService::class)->defaultRateTierCount());
        $tierCount = $this->resolveTierCount($minAmount, $maxAmount, $tierCount);

        $maxRate = RatePercent::toDecimal((float) ($product->interest_rate ?? 0));
        if ($maxRate <= 0) {
            $maxRate = 0.12;
        }

        $discount = (float) ($overrides['rate_discount_fraction']
            ?? app(UnderwritingSettingsService::class)->defaultRateDiscountFraction());
        $discount = max(0, min(0.85, $discount));

        $bands = $this->buildAmountBands($minAmount, $maxAmount, $tierCount);
        $rates = $this->buildTierRates($maxRate, count($bands), $discount);

        $rows = [];
        $order = 0;

        foreach ($bands as $i => $band) {
            $normalized = $this->normalizeTierRow(['monthly_rate' => $rates[$i] ?? $maxRate]);

            $rows[] = [
                'min_amount'              => $band['min_amount'],
                'max_amount'              => $band['max_amount'],
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

    /**
     * Preview tiers for admin forms (create / validation errors).
     *
     * @return list<array<string, mixed>>
     */
    public function previewRows(
        ?string $code = null,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?float $interestRate = null,
    ): array {
        $product = new LoanProduct([
            'code'          => $code ?? 'PREVIEW',
            'min_amount'    => $minAmount ?? 100_000,
            'max_amount'    => $maxAmount ?? 5_000_000,
            'interest_rate' => $interestRate ?? 0.17,
        ]);

        return $this->tiersForProduct($product);
    }

    /** @return list<array{min_amount: int, max_amount: int}> */
    public function buildAmountBands(float $minAmount, float $maxAmount, int $tierCount): array
    {
        $minAmount = (int) round($minAmount);
        $maxAmount = (int) round($maxAmount);
        $tierCount = max(1, $tierCount);

        if ($maxAmount <= $minAmount) {
            return [['min_amount' => $minAmount, 'max_amount' => $maxAmount]];
        }

        if ($tierCount === 1) {
            return [['min_amount' => $minAmount, 'max_amount' => $maxAmount]];
        }

        $bands = [];
        $span = $maxAmount - $minAmount;

        for ($i = 0; $i < $tierCount; $i++) {
            $bandMin = $i === 0
                ? $minAmount
                : (int) floor($minAmount + ($span * $i / $tierCount)) + 1;

            $bandMax = $i === $tierCount - 1
                ? $maxAmount
                : (int) floor($minAmount + ($span * ($i + 1) / $tierCount));

            if ($bandMin > $bandMax) {
                continue;
            }

            $bands[] = ['min_amount' => $bandMin, 'max_amount' => $bandMax];
        }

        return $bands;
    }

    /** @return list<float> Monthly rates (decimals), highest first. */
    public function buildTierRates(float $maxRate, int $tierCount, float $discountFraction): array
    {
        $tierCount = max(1, $tierCount);
        $maxRate = max(0.01, round($maxRate, 4));
        $minRate = max(0.01, round($maxRate * (1 - $discountFraction), 4));

        if ($tierCount === 1) {
            return [$maxRate];
        }

        $rates = [];
        for ($i = 0; $i < $tierCount; $i++) {
            $rates[] = round($maxRate - ($maxRate - $minRate) * ($i / ($tierCount - 1)), 4);
        }

        return $rates;
    }

    protected function resolveTierCount(float $minAmount, float $maxAmount, int $configured): int
    {
        $configured = max(1, min(6, $configured));

        if ($maxAmount <= $minAmount) {
            return 1;
        }

        $ratio = $maxAmount / max($minAmount, 1);

        if ($ratio < 3) {
            return 1;
        }

        if ($ratio < 12) {
            return min($configured, 2);
        }

        if ($ratio < 50) {
            return min($configured, 3);
        }

        return $configured;
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
