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
            : (function () use ($key) {
                $apply = __('borrower.apply.purposes.'.$key);
                if ($apply !== 'borrower.apply.purposes.'.$key) {
                    return $apply;
                }

                return config('loan_purposes.'.$key) ?? display_label($key, 'loan_purpose');
            })();
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

if (! function_exists('borrower_catalogue_products')) {
    /** @return \Illuminate\Support\Collection<int, \App\Models\LoanProduct> */
    function borrower_catalogue_products(): \Illuminate\Support\Collection
    {
        $order = config('loan_products.display_order', ['IL', 'GL', 'AL', 'FC', 'KB', 'BP', 'EL', 'EM', 'WL', 'AB']);

        return \App\Models\LoanProduct::with('rateTiers')->where('is_active', true)->get()
            ->sortBy(fn (\App\Models\LoanProduct $p) => ($i = array_search($p->code, $order, true)) === false ? 99 : $i)
            ->values();
    }
}

if (! function_exists('active_loan_product_count')) {
    function active_loan_product_count(): int
    {
        return (int) \App\Models\LoanProduct::where('is_active', true)->count();
    }
}

if (! function_exists('marketplace_only_loan_codes')) {
    /** @return list<string> */
    function marketplace_only_loan_codes(): array
    {
        return config('asset_marketplace.marketplace_only_codes', ['AL', 'AST']);
    }
}

if (! function_exists('is_marketplace_loan_product')) {
    function is_marketplace_loan_product(?string $code): bool
    {
        return in_array(strtoupper((string) $code), marketplace_only_loan_codes(), true);
    }
}

if (! function_exists('effective_marketplace_asset_max_tenure')) {
    /** Max loan tenure months for a marketplace asset (asset cap × platform loan cap). */
    function effective_marketplace_asset_max_tenure(\App\Models\MarketplaceAsset $asset): int
    {
        $assetTenure = (int) $asset->max_tenure_months;
        $loanCap = (int) (\App\Models\Setting::group('loan')['max_tenure_months'] ?? 6);

        if ($assetTenure <= 0) {
            return max(1, $loanCap);
        }

        if ($loanCap <= 0) {
            return $assetTenure;
        }

        return min($assetTenure, $loanCap);
    }
}
