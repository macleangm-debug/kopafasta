<?php

if (! function_exists('activity_type_label')) {
    function activity_type_label(?string $key): ?string
    {
        if (! filled($key)) {
            return null;
        }

        $translated = __('activity.types.'.$key);

        return $translated !== 'activity.types.'.$key
            ? $translated
            : (config('activity_profiles.types.'.$key) ?? $key);
    }
}

if (! function_exists('activity_type_options')) {
    /** @return array<string, string> */
    function activity_type_options(): array
    {
        return collect(config('activity_profiles.types', []))
            ->mapWithKeys(fn (string $label, string $key) => [$key => activity_type_label($key) ?? $label])
            ->all();
    }
}

if (! function_exists('translate_activity_field')) {
    /** @param  array<string, mixed>  $field */
    function translate_activity_field(array $field): array
    {
        $key = $field['key'] ?? '';
        $translated = __('activity.fields.'.$key);
        $field['label'] = $translated !== 'activity.fields.'.$key
            ? $translated
            : ($field['label'] ?? $key);

        if (! empty($field['options']) && is_array($field['options'])) {
            $field['options'] = collect($field['options'])
                ->mapWithKeys(function (string $label, string $optKey) use ($key) {
                    $optTranslated = __('activity.options.'.$key.'.'.$optKey);

                    return [$optKey => $optTranslated !== 'activity.options.'.$key.'.'.$optKey ? $optTranslated : $label];
                })
                ->all();
        }

        return $field;
    }
}

if (! function_exists('activity_fields_localized')) {
    /** @return array<string, list<array<string, mixed>>> */
    function activity_fields_localized(): array
    {
        return collect(config('activity_profiles.fields', []))
            ->map(fn (array $fields) => collect($fields)->map(fn (array $field) => translate_activity_field($field))->all())
            ->all();
    }
}

if (! function_exists('income_range_label')) {
    function income_range_label(?string $key): ?string
    {
        if (! filled($key)) {
            return null;
        }

        $translated = __('activity.income_ranges.'.$key);

        if ($translated !== 'activity.income_ranges.'.$key) {
            return currency_code().' '.$translated;
        }

        $label = config("income_ranges.{$key}.label");

        return $label ? currency_code().' '.$label : $key;
    }
}

if (! function_exists('loan_purpose_label')) {
    function loan_purpose_label(?string $key): ?string
    {
        if (! filled($key)) {
            return null;
        }

        $translated = __('activity.loan_purposes.'.$key);

        return $translated !== 'activity.loan_purposes.'.$key
            ? $translated
            : (config('loan_purposes.'.$key) ?? $key);
    }
}

if (! function_exists('loan_purpose_options')) {
    /** @return array<string, string> */
    function loan_purpose_options(): array
    {
        return collect(config('loan_purposes', []))
            ->mapWithKeys(fn (string $label, string $key) => [$key => loan_purpose_label($key) ?? $label])
            ->all();
    }
}
