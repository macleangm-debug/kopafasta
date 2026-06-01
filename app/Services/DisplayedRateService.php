<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\Setting;

class DisplayedRateService
{
    public const BOT_MAX_MONTHLY_RATE = 0.035;

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
    public function breakdown(LoanProduct $product, ?float $principal = null): array
    {
        $botCap = (float) (Setting::group('loan')['bot_max_monthly_rate'] ?? self::BOT_MAX_MONTHLY_RATE);
        $botCap = min(max($botCap, 0), self::BOT_MAX_MONTHLY_RATE);

        $baseRate = app(LoanRateTierService::class)->resolveRate(
            $product,
            $principal ?? (float) $product->min_amount
        );

        $bot = (float) ($product->bot_regulated_rate ?? $baseRate);
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
            'displayed_monthly_rate'    => round($bot + $internal, 4),
        ];
    }

    public function displayedMonthlyRate(LoanProduct $product, ?float $principal = null): float
    {
        return $this->breakdown($product, $principal)['displayed_monthly_rate'];
    }

    public function formatPercent(float $rate): string
    {
        return number_format($rate * 100, 2).'%';
    }

    /** @return list<string> */
    public function disclosureLines(LoanProduct $product, ?float $principal = null): array
    {
        $parts = $this->breakdown($product, $principal);

        return [
            'BOT regulated interest: '.$this->formatPercent($parts['bot_regulated_rate']).' per month (max '. $this->formatPercent(self::BOT_MAX_MONTHLY_RATE).')',
            'Internal fees: '.$this->formatPercent($parts['internal_fee_rate']).' per month (processing + service + administration)',
            'Displayed rate to borrower: '.$this->formatPercent($parts['displayed_monthly_rate']).' per month',
        ];
    }
}
