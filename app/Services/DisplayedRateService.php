<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;
use App\Models\Setting;
use App\Support\RatePercent;

class DisplayedRateService
{
    public const BOT_MAX_MONTHLY_RATE = 0.035;

    public function tierRateAt(LoanProduct $product, float $amount): ?float
    {
        $tier = $this->findTier($product, $amount);

        return $tier ? (float) $tier->monthly_rate : null;
    }

    /** Total monthly rate shown to borrowers (tier band total, or legacy interest_rate). */
    public function displayedMonthlyRate(LoanProduct $product, ?float $principal = null): float
    {
        $amount = $principal ?? (float) $product->min_amount;
        $tierRate = $this->tierRateAt($product, $amount);

        if ($tierRate !== null) {
            return $tierRate;
        }

        return $this->productLevelMonthlyRate($product);
    }

    public function productLevelMonthlyRate(LoanProduct $product): float
    {
        return $this->componentStackTotal($product);
    }

    /** Sum of BOT (capped) + processing + risk + insurance fee components. */
    public function componentStackTotal(LoanProduct $product): float
    {
        return $this->feeStackBreakdown($product)['displayed_monthly_rate'];
    }

    /**
     * @return array{
     *     bot_regulated_rate: float,
     *     processing_fee_rate: float,
     *     service_fee_rate: float,
     *     insurance_fee_rate: float,
     *     internal_fee_rate: float,
     *     component_total: float
     * }
     */
    public function rateComponents(LoanProduct $product): array
    {
        $stack = $this->feeStackBreakdown($product);

        return [
            'bot_regulated_rate'   => $stack['bot_regulated_rate'],
            'processing_fee_rate'  => $stack['processing_fee_rate'],
            'service_fee_rate'     => $stack['service_fee_rate'],
            'insurance_fee_rate'   => $stack['administration_fee_rate'],
            'internal_fee_rate'    => $stack['internal_fee_rate'],
            'component_total'      => $stack['displayed_monthly_rate'],
        ];
    }

    public function lowestBorrowerRateLabel(iterable $products): string
    {
        $mins = collect($products)
            ->map(fn (LoanProduct $p) => $this->borrowerRateRange($p)['min'])
            ->filter(fn (float $r) => $r > 0);

        if ($mins->isEmpty()) {
            return '—';
        }

        return RatePercent::formatOne($mins->min());
    }

    /**
     * @return array{min: float, max: float}
     */
    public function borrowerRateRange(LoanProduct $product): array
    {
        $tiers = $this->loadTiers($product);

        if ($tiers->isNotEmpty()) {
            $rates = $tiers->pluck('monthly_rate')->map(fn ($r) => (float) $r);

            return ['min' => $rates->min(), 'max' => $rates->max()];
        }

        $rate = max(0, $this->productLevelMonthlyRate($product));

        return ['min' => $rate, 'max' => $rate];
    }

    public function formatBorrowerRateRange(LoanProduct $product): string
    {
        $range = $this->borrowerRateRange($product);

        return RatePercent::formatRange($range['min'], $range['max']);
    }

    /**
     * Admin-only: BOT cap + internal fee components (does not use tier bands as BOT base).
     *
     * @return array{
     *     bot_regulated_rate: float,
     *     processing_fee_rate: float,
     *     service_fee_rate: float,
     *     administration_fee_rate: float,
     *     internal_fee_rate: float,
     *     displayed_monthly_rate: float,
     *     uses_tiers: bool,
     *     tier_borrower_rate_at_min: float|null,
     *     tier_borrower_range: string|null
     * }
     */
    public function breakdown(LoanProduct $product, ?float $principal = null): array
    {
        $stack = $this->feeStackBreakdown($product);
        $amount = $principal ?? (float) $product->min_amount;
        $tiers = $this->loadTiers($product);

        if ($tiers->isEmpty()) {
            return array_merge($stack, [
                'uses_tiers'              => false,
                'tier_borrower_rate_at_min' => null,
                'tier_borrower_range'     => null,
            ]);
        }

        $range = $this->borrowerRateRange($product);

        return array_merge($stack, [
            'uses_tiers'                => true,
            'tier_borrower_rate_at_min' => $this->displayedMonthlyRate($product, $amount),
            'tier_borrower_range'       => RatePercent::formatRange($range['min'], $range['max']),
            'displayed_monthly_rate'    => $this->displayedMonthlyRate($product, $amount),
        ]);
    }

    public function formatPercent(float $rate): string
    {
        return RatePercent::formatOne($rate);
    }

    /** @return list<string> */
    public function disclosureLines(LoanProduct $product, ?float $principal = null): array
    {
        $parts = $this->breakdown($product, $principal);

        if ($parts['uses_tiers']) {
            return [
                'Tiered monthly rate to borrower: '.$parts['tier_borrower_range'].' per month (by approved amount)',
                'At minimum amount: '.$this->formatPercent($parts['tier_borrower_rate_at_min'] ?? $parts['displayed_monthly_rate']).' per month',
            ];
        }

        return [
            'BOT regulated interest: '.$this->formatPercent($parts['bot_regulated_rate']).' per month (max '.$this->formatPercent(self::BOT_MAX_MONTHLY_RATE).')',
            'Internal fees: '.$this->formatPercent($parts['internal_fee_rate']).' per month (processing + risk + administration)',
            'Monthly rate to borrower: '.$this->formatPercent($parts['displayed_monthly_rate']).' per month',
        ];
    }

    /**
     * @return array{
     *     bot_regulated_rate: float,
     *     processing_fee_rate: float,
     *     service_fee_rate: float,
     *     administration_fee_rate: float,
     *     internal_fee_rate: float,
     *     displayed_monthly_rate: float
     * }
     */
    private function feeStackBreakdown(LoanProduct $product): array
    {
        $botCap = (float) (Setting::group('loan')['bot_max_monthly_rate'] ?? self::BOT_MAX_MONTHLY_RATE);
        $botCap = min(max($botCap, 0), self::BOT_MAX_MONTHLY_RATE);

        $bot = (float) ($product->bot_regulated_rate ?? $product->interest_rate ?? 0);
        $bot = min(max($bot, 0), $botCap);

        $processing = max(0, (float) ($product->processing_fee_rate ?? 0));
        $service = max(0, (float) ($product->service_fee_rate ?? 0));
        $administration = max(0, (float) ($product->administration_fee_rate ?? 0));
        $internal = round($processing + $service + $administration, 4);

        return [
            'bot_regulated_rate'      => $bot,
            'processing_fee_rate'     => $processing,
            'service_fee_rate'        => $service,
            'administration_fee_rate' => $administration,
            'internal_fee_rate'       => $internal,
            'displayed_monthly_rate'  => round($bot + $internal, 4),
        ];
    }

    private function findTier(LoanProduct $product, float $amount): ?LoanProductRateTier
    {
        if (! $product->id) {
            return null;
        }

        return LoanProductRateTier::query()
            ->where('loan_product_id', $product->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('sort_order')
            ->first();
    }

    /** @return \Illuminate\Support\Collection<int, LoanProductRateTier> */
    private function loadTiers(LoanProduct $product): \Illuminate\Support\Collection
    {
        if ($product->relationLoaded('rateTiers')) {
            return $product->rateTiers->sortBy('sort_order')->values();
        }

        if (! $product->id) {
            return collect();
        }

        return $product->rateTiers()->orderBy('sort_order')->get();
    }
}
