<?php

use App\Support\MoneyFormat;

if (! function_exists('country_config')) {
    /** @return array{name: string, currency: string, currency_symbol: string, locale: string} */
    function country_config(): array
    {
        return config('country', []);
    }
}

if (! function_exists('currency_code')) {
    function currency_code(): string
    {
        return (string) (country_config()['currency'] ?? 'TZS');
    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol(): string
    {
        return (string) (country_config()['currency_symbol'] ?? 'TSh');
    }
}

if (! function_exists('format_number')) {
    /**
     * Format a numeric value with thousands separators (e.g. 1,500,000.50).
     */
    function format_number(float|int|string|null $amount, int $decimals = 0): string
    {
        return MoneyFormat::format(MoneyFormat::toNumber($amount), $decimals);
    }
}

if (! function_exists('format_money')) {
    /**
     * Format currency for display (loan amounts, fees, balances, repayments, reports).
     */
    function format_money(float|int|string|null $amount, bool $withCode = true, int $decimals = 0): string
    {
        $formatted = format_number($amount, $decimals);

        return $withCode
            ? currency_code().' '.$formatted
            : currency_symbol().' '.$formatted;
    }
}

if (! function_exists('format_income_range')) {
    function format_income_range(?string $key): string
    {
        if (! $key) {
            return '—';
        }

        return income_range_label($key) ?? $key;
    }
}

if (! function_exists('income_range_options')) {
    /** @return array<string, string> */
    function income_range_options(): array
    {
        return collect(config('income_ranges', []))
            ->mapWithKeys(fn (array $range, string $key) => [$key => income_range_label($key) ?? $key])
            ->all();
    }
}

if (! function_exists('loan_product_theme')) {
    /** @return array{icon: string, theme: string, label?: string} */
    function loan_product_theme(?string $code): array
    {
        $themes = config('loan_product_themes', []);
        $code = strtoupper((string) $code);

        return $themes[$code] ?? $themes['default'] ?? ['icon' => '💼', 'theme' => 'slate'];
    }
}
