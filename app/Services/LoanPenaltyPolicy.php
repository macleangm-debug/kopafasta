<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Setting;

/**
 * Resolves grace period and penalty rules for a loan (snapshot → product → global settings).
 */
class LoanPenaltyPolicy
{
    public const BOT_MAX_PENALTY_CAP_PERCENT = 30.0;

    public const DEFAULT_PENALTY_RATE_PERCENT_PER_DAY = 1.0;

    public function __construct(
        public readonly int $graceDaysAfterDefault,
        public readonly float $penaltyRatePercent,
        public readonly string $penaltyBasis,
        public readonly float $penaltyCapPercent,
    ) {}

    public static function for(Loan $loan): self
    {
        $loan->loadMissing('product');
        $global = self::loanSettings();

        $grace = (int) (
            $loan->default_grace_days
            ?? $loan->product?->default_grace_days
            ?? $global['default_grace_days']
            ?? config('loan_product_defaults.default_grace_days', 7)
        );

        $rate = (float) (
            $loan->penalty_rate_percent
            ?? $loan->product?->penalty_rate_percent
            ?? $global['default_penalty_rate']
            ?? config('loan_product_defaults.penalty_rate_percent', self::DEFAULT_PENALTY_RATE_PERCENT_PER_DAY)
        );

        $basis = (string) (
            $loan->penalty_basis
            ?? $loan->product?->penalty_basis
            ?? $global['penalty_basis']
            ?? config('loan_product_defaults.penalty_basis', 'per_day')
        );

        $cap = (float) ($global['penalty_cap_percent'] ?? self::BOT_MAX_PENALTY_CAP_PERCENT);
        $cap = min(self::BOT_MAX_PENALTY_CAP_PERCENT, max(0, $cap));

        return new self(
            graceDaysAfterDefault: max(0, $grace),
            penaltyRatePercent: min(self::BOT_MAX_PENALTY_CAP_PERCENT, max(0, $rate)),
            penaltyBasis: in_array($basis, ['per_day', 'per_month', 'one_time'], true) ? $basis : 'per_day',
            penaltyCapPercent: $cap,
        );
    }

    /** Defaults for a loan product record (admin + seeding). */
    public static function defaultsForProduct(LoanProduct $product): array
    {
        $code = strtoupper((string) $product->code);
        $overrides = config("loan_product_defaults.products.{$code}", []);
        $global = self::loanSettings();

        return [
            'default_grace_days' => (int) (
                $overrides['default_grace_days']
                ?? $global['default_grace_days']
                ?? config('loan_product_defaults.default_grace_days', 7)
            ),
            'penalty_rate_percent' => (float) (
                $overrides['penalty_rate_percent']
                ?? $global['default_penalty_rate']
                ?? config('loan_product_defaults.penalty_rate_percent', self::DEFAULT_PENALTY_RATE_PERCENT_PER_DAY)
            ),
            'penalty_basis' => (string) (
                $overrides['penalty_basis']
                ?? $global['penalty_basis']
                ?? config('loan_product_defaults.penalty_basis', 'per_day')
            ),
        ];
    }

    public function perDayPenaltyAmount(float $overdueBalance): float
    {
        if ($overdueBalance <= 0) {
            return 0.0;
        }

        return match ($this->penaltyBasis) {
            'per_day' => round($overdueBalance * ($this->penaltyRatePercent / 100), 2),
            'per_month' => round($overdueBalance * ($this->penaltyRatePercent / 100) / 30, 2),
            'one_time' => round($overdueBalance * ($this->penaltyRatePercent / 100), 2),
            default => round($overdueBalance * ($this->penaltyRatePercent / 100), 2),
        };
    }

    public function maxPenaltyAmount(float $overdueBalance): float
    {
        return round($overdueBalance * ($this->penaltyCapPercent / 100), 2);
    }

    /** @return array<string, mixed> */
    protected static function loanSettings(): array
    {
        try {
            return Setting::group('loan');
        } catch (\Throwable) {
            return [];
        }
    }
}
