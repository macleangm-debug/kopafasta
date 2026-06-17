<?php

use App\Services\DisplayLabelService;

if (! function_exists('display_label')) {
    function display_label(?string $value, ?string $group = null): string
    {
        return app(DisplayLabelService::class)->label($value, $group);
    }
}

if (! function_exists('display_options')) {
    /** @param  list<string>|array<string, string>  $values */
    function display_options(array $values, ?string $group = null): array
    {
        return app(DisplayLabelService::class)->options($values, $group);
    }
}

if (! function_exists('format_display_value')) {
    /**
     * Format values for read-only admin/detail views (auto comma-separates plain numbers).
     */
    function format_display_value(mixed $value, bool $asMoney = false, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value)) {
            return $asMoney
                ? format_money($value, true, $decimals)
                : format_number($value, $decimals);
        }

        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', str_replace(',', '', $value))) {
            return $asMoney
                ? format_money($value, true, $decimals)
                : format_number($value, $decimals);
        }

        return (string) $value;
    }
}

if (! function_exists('marketplace_category_emoji')) {
    function marketplace_category_emoji(?string $category): string
    {
        return match ($category) {
            'vehicle', 'vehicles'       => '🚗',
            'motorcycle', 'motorcycles' => '🏍️',
            'truck', 'trucks'           => '🚚',
            'equipment'                 => '🧰',
            'electronics'               => '📱',
            'furniture'                 => '🪑',
            'property'                  => '🏠',
            default                     => '🏭',
        };
    }
}
