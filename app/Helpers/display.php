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
