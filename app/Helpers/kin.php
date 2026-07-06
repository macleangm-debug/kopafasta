<?php

use Illuminate\Support\Str;

if (! function_exists('kin_relationship_key')) {
    function kin_relationship_key(string $value): string
    {
        return Str::slug(strtolower(trim($value)), '_');
    }
}

if (! function_exists('kin_relationship_label')) {
    function kin_relationship_label(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $key = kin_relationship_key($value);
        $label = __("kin.relationships.{$key}");

        return $label !== "kin.relationships.{$key}" ? $label : $value;
    }
}

if (! function_exists('kin_relationship_options')) {
    /** @return array<string, string> */
    function kin_relationship_options(): array
    {
        return collect(config('kin.relationships', []))
            ->mapWithKeys(fn (string $value) => [$value => kin_relationship_label($value) ?? $value])
            ->all();
    }
}
