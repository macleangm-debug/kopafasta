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

if (! function_exists('format_money_compact')) {
    /**
     * Short KPI/card/chart amounts (TZS 1.25M). Not for receipts, tables or payment.show.
     */
    function format_money_compact(float|int|string|null $amount, bool $withCode = true): string
    {
        $compact = MoneyFormat::compact($amount);

        if (! $withCode) {
            return $compact;
        }

        $sign = str_starts_with($compact, '−') ? '−' : '';
        $body = $sign === '' ? $compact : substr($compact, strlen('−'));

        return $sign.currency_code().' '.$body;
    }
}

if (! function_exists('format_money_spoken')) {
    function format_money_spoken(float|int|string|null $amount, ?string $locale = null): string
    {
        return MoneyFormat::spoken($amount, $locale);
    }
}

if (! function_exists('normalize_income_range_key')) {
    /**
     * Map legacy income-range keys to canonical selectable config keys.
     */
    function normalize_income_range_key(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return $key;
        }

        $aliases = [
            '1m_plus' => '1m_5m',
            'above_1m' => '1m_5m',
            'below_100k' => '100k_300k',
        ];

        $normalized = $aliases[$key] ?? $key;

        return array_key_exists($normalized, config('income_ranges', []))
            ? $normalized
            : $key;
    }
}

if (! function_exists('format_income_range')) {
    function format_income_range(?string $key): string
    {
        if (! $key) {
            return '—';
        }

        $key = normalize_income_range_key($key) ?? $key;

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

if (! function_exists('income_range_select_options')) {
    /** @return array<string, string> */
    function income_range_select_options(): array
    {
        $legacy = ['below_100k', 'above_1m'];

        return collect(income_range_options())
            ->reject(fn (string $label, string $key) => in_array($key, $legacy, true))
            ->all();
    }
}

if (! function_exists('loan_product_theme')) {
    /** @return array{icon: string, theme: string, label?: string, label_sw?: string, illustration?: string} */
    function loan_product_theme(?string $code): array
    {
        $themes = config('loan_product_themes', []);
        $code = strtoupper((string) $code);

        return $themes[$code] ?? $themes['default'] ?? ['icon' => '💼', 'theme' => 'slate'];
    }
}

if (! function_exists('loan_product_card_description')) {
    function loan_product_card_description(object $product): string
    {
        if (function_exists('is_marketplace_loan_product') && is_marketplace_loan_product($product->code ?? null)) {
            return (string) __('borrower.marketplace.subtitle');
        }

        $max = 90;

        if (method_exists($product, 'localizedShortDescription')) {
            $short = $product->localizedShortDescription();
            if (filled($short)) {
                return \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', (string) $short) ?? ''), $max, '…');
            }
        }

        $theme = loan_product_theme($product->code ?? null);
        $locale = app()->getLocale();
        $themeLabel = $locale === 'sw'
            ? ($theme['label_sw'] ?? $theme['label'] ?? null)
            : ($theme['label'] ?? $theme['label_sw'] ?? null);

        $text = filled($themeLabel)
            ? (string) $themeLabel
            : (string) ($product->description ?? __('borrower.dashboard.browse_products'));

        return \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $text) ?? ''), $max, '…');
    }
}

if (! function_exists('format_phone')) {
    /** Display a phone with the country prefix, e.g. +255 784275297. */
    function format_phone(?string $phone): string
    {
        return \App\Support\PhoneNumber::format($phone) ?: '—';
    }
}
