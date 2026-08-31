<?php

use App\Services\DisplayLabelService;
use Carbon\Carbon;
use Carbon\CarbonInterface;

if (! function_exists('display_label')) {
    function display_label(?string $value, ?string $group = null): string
    {
        return app(DisplayLabelService::class)->label($value, $group);
    }
}

if (! function_exists('app_display_timezone')) {
    function app_display_timezone(): string
    {
        return (string) (config('app.display_timezone')
            ?: config('country.timezone')
            ?: 'Africa/Dar_es_Salaam');
    }
}

if (! function_exists('format_app_datetime')) {
    /**
     * Format a stored timestamp in the local display timezone (default EAT).
     */
    function format_app_datetime(\DateTimeInterface|string|null $value, string $format = 'd M Y H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $dt = $value instanceof CarbonInterface
            ? $value->copy()
            : Carbon::parse($value);

        return $dt->timezone(app_display_timezone())->format($format);
    }
}

if (! function_exists('guided_evidence_url')) {
    /**
     * Keep Guided Review (or Committee / Post-Approval) as the return context
     * when opening a full evidence page from a wizard step.
     */
    function guided_evidence_url(?string $href, string $from = 'guided'): string
    {
        if (! is_string($href) || $href === '') {
            return '';
        }
        $from = in_array($from, ['guided', 'committee', 'post_approval'], true) ? $from : 'guided';
        $hash = '';
        if (str_contains($href, '#')) {
            [$href, $hash] = explode('#', $href, 2);
            $hash = '#'.$hash;
        }
        $join = str_contains($href, '?') ? '&' : '?';

        return $href.$join.'from='.rawurlencode($from).$hash;
    }
}

if (! function_exists('format_app_date')) {
    function format_app_date(\DateTimeInterface|string|null $value, string $format = 'd M Y'): string
    {
        return format_app_datetime($value, $format);
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
