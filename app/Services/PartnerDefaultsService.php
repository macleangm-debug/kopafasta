<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Setting;

/**
 * Global default rates/prices for insurance, GPS, valuers (Recovery settings),
 * with optional per-partner overrides from Add Partner.
 */
class PartnerDefaultsService
{
    /** @return array<string, array<string, mixed>> */
    public function categories(): array
    {
        return config('partner_defaults.categories', []);
    }

    /** @return array<string, mixed> */
    public function defaultsFor(string $category): array
    {
        $meta = $this->categories()[$category] ?? null;
        if ($meta === null) {
            return [];
        }

        $prefix = "partner_defaults.{$category}";
        $hasMarkup = (bool) Setting::get(
            "{$prefix}.has_markup",
            $meta['default_has_markup'] ?? false
        );
        $markup = max(0, (float) Setting::get(
            "{$prefix}.markup_percent",
            $meta['default_markup_percent'] ?? 0
        ));

        $row = [
            'category' => $category,
            'label' => $meta['label'] ?? $category,
            'pricing_mode' => $meta['pricing_mode'] ?? 'fixed',
            'add_category' => $meta['add_category'] ?? $category,
            'help' => $meta['help'] ?? null,
            'charge_unit' => $meta['charge_unit'] ?? null,
            'has_markup' => $hasMarkup,
            'markup_percent' => $hasMarkup ? $markup : 0.0,
            'stored_markup_percent' => $markup,
        ];

        if (($meta['pricing_mode'] ?? '') === 'percent_of_value') {
            $row['rate_percent'] = max(0, (float) Setting::get(
                "{$prefix}.rate_percent",
                // Prefer partner_defaults; fall back to legacy underwriting keys.
                Setting::get(
                    'underwriting.collateral_insurance_rate_percent',
                    $meta['default_rate_percent'] ?? 3.5
                )
            ));
        } else {
            $row['base_cost'] = max(0, (float) Setting::get(
                "{$prefix}.base_cost",
                $meta['default_base_cost'] ?? 0
            ));
        }

        if (($meta['pricing_mode'] ?? '') === 'fixed_plus_recurring') {
            $row['monitoring_monthly'] = max(0, (float) Setting::get(
                "{$prefix}.monitoring_monthly",
                $meta['default_monitoring_monthly']
                    ?? config('gps_pricing.monitoring_monthly', 10_000)
            ));
        }

        return $row;
    }

    /** @return array<string, array<string, mixed>> */
    public function allDefaults(): array
    {
        $out = [];
        foreach (array_keys($this->categories()) as $category) {
            $out[$category] = $this->defaultsFor($category);
        }

        return $out;
    }

    public function insuranceRatePercent(?Partner $partner = null): float
    {
        $defaults = $this->defaultsFor('insurance');
        $rate = (float) ($defaults['rate_percent'] ?? 3.5);

        if ($partner) {
            $override = data_get($partner->metadata, 'service_rate_percent');
            if ($override !== null && $override !== '') {
                $rate = (float) $override;
            }
        }

        return max(0, $rate);
    }

    public function insuranceMarkupPercent(?Partner $partner = null): float
    {
        $defaults = $this->defaultsFor('insurance');

        if ($partner) {
            if ($partner->markup_percent !== null && $partner->markup_percent !== '') {
                return max(0, (float) $partner->markup_percent);
            }
            $metaHas = data_get($partner->metadata, 'has_markup');
            if ($metaHas === false || $metaHas === 0 || $metaHas === '0') {
                return 0.0;
            }
        }

        return max(0, (float) ($defaults['markup_percent'] ?? 0));
    }

    public function gpsBaseCost(?Partner $partner = null): float
    {
        $defaults = $this->defaultsFor('gps_installer');
        if ($partner && $partner->partner_cost !== null && $partner->partner_cost !== '') {
            return max(0, (float) $partner->partner_cost);
        }

        return max(0, (float) ($defaults['base_cost'] ?? config('gps_pricing.device_cost', 50_000)));
    }

    public function gpsMonitoringMonthly(): float
    {
        $defaults = $this->defaultsFor('gps_installer');

        return max(0, (float) ($defaults['monitoring_monthly'] ?? config('gps_pricing.monitoring_monthly', 20_000)));
    }

    public function gpsMarkupPercent(?Partner $partner = null): float
    {
        $defaults = $this->defaultsFor('gps_installer');

        if ($partner && $partner->markup_percent !== null && $partner->markup_percent !== '') {
            return max(0, (float) $partner->markup_percent);
        }

        return max(0, (float) ($defaults['markup_percent'] ?? config('gps_pricing.markup_percent', 0)));
    }

    public function valuerBaseCost(?Partner $partner = null): float
    {
        $defaults = $this->defaultsFor('valuer');
        if ($partner && $partner->partner_cost !== null && $partner->partner_cost !== '') {
            return max(0, (float) $partner->partner_cost);
        }

        return max(0, (float) ($defaults['base_cost'] ?? 0));
    }

    public function valuerMarkupPercent(?Partner $partner = null): float
    {
        $defaults = $this->defaultsFor('valuer');

        if ($partner && $partner->markup_percent !== null && $partner->markup_percent !== '') {
            return max(0, (float) $partner->markup_percent);
        }

        return max(0, (float) ($defaults['markup_percent'] ?? 0));
    }

    /**
     * Snapshot shown on Add Partner Rates step for a given category.
     *
     * @return array{label: string, lines: list<string>, pricing_mode: string, has_markup: bool, markup_percent: float, rate_percent?: float, base_cost?: float, monitoring_monthly?: float}
     */
    public function formSnapshot(string $category): array
    {
        $defaults = $this->defaultsFor($category);
        if ($defaults === []) {
            return [
                'label' => $category,
                'lines' => ['No platform defaults for this partner type.'],
                'pricing_mode' => 'fixed',
                'has_markup' => false,
                'markup_percent' => 0.0,
            ];
        }

        $lines = [];
        $mode = $defaults['pricing_mode'] ?? 'fixed';

        if ($mode === 'percent_of_value') {
            $lines[] = 'Default cover rate: '.rtrim(rtrim(number_format((float) $defaults['rate_percent'], 2), '0'), '.').'% of insured value';
        } else {
            $unit = (string) ($defaults['charge_unit'] ?? '');
            $lines[] = 'Default base price: TZS '.number_format((float) ($defaults['base_cost'] ?? 0), 0)
                .($unit !== '' ? ' '.$unit : '');
        }

        if ($mode === 'fixed_plus_recurring') {
            $lines[] = 'Default monitoring: TZS '.number_format((float) ($defaults['monitoring_monthly'] ?? 0), 0).'/month';
        }

        if (! empty($defaults['has_markup']) && (float) $defaults['markup_percent'] > 0) {
            $lines[] = $mode === 'percent_of_value'
                ? 'Platform markup: +'.rtrim(rtrim(number_format((float) $defaults['markup_percent'], 2), '0'), '.').'% of insured value (added to cover rate)'
                : 'Default markup: '.rtrim(rtrim(number_format((float) $defaults['markup_percent'], 2), '0'), '.').'% on partner cost';
        } else {
            $lines[] = 'Default markup: none';
        }

        return [
            'label' => (string) $defaults['label'],
            'lines' => $lines,
            'pricing_mode' => $mode,
            'has_markup' => (bool) $defaults['has_markup'],
            'markup_percent' => (float) $defaults['markup_percent'],
            'rate_percent' => isset($defaults['rate_percent']) ? (float) $defaults['rate_percent'] : null,
            'base_cost' => isset($defaults['base_cost']) ? (float) $defaults['base_cost'] : null,
            'monitoring_monthly' => isset($defaults['monitoring_monthly']) ? (float) $defaults['monitoring_monthly'] : null,
        ];
    }

    /**
     * Persist defaults from the Recovery settings form.
     * Only categories present in $input (by rate/base keys or has_markup) are updated.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>|null  $onlyCategories
     */
    public function saveFromRequest(array $input, ?array $onlyCategories = null): void
    {
        $settings = [];
        $categories = $onlyCategories ?? array_keys($this->categories());

        foreach ($categories as $category) {
            $meta = $this->categories()[$category] ?? null;
            if ($meta === null) {
                continue;
            }

            $prefix = "partner_defaults.{$category}";
            $hasMarkup = (bool) ($input["{$category}_has_markup"] ?? false);
            $markup = max(0, (float) ($input["{$category}_markup_percent"] ?? 0));

            $settings["{$prefix}.has_markup"] = $hasMarkup;
            $settings["{$prefix}.markup_percent"] = $markup;

            if (($meta['pricing_mode'] ?? '') === 'percent_of_value') {
                $rate = max(0, (float) ($input["{$category}_rate_percent"] ?? $meta['default_rate_percent'] ?? 0));
                $settings["{$prefix}.rate_percent"] = $rate;
                // Keep underwriting keys in sync for existing screens.
                $settings['underwriting.collateral_insurance_rate_percent'] = $rate;
                $settings['underwriting.collateral_insurance_markup_percent'] = $hasMarkup ? $markup : 0;
            } else {
                $settings["{$prefix}.base_cost"] = max(0, (float) ($input["{$category}_base_cost"] ?? $meta['default_base_cost'] ?? 0));
            }

            if (($meta['pricing_mode'] ?? '') === 'fixed_plus_recurring') {
                $settings["{$prefix}.monitoring_monthly"] = max(0, (float) (
                    $input["{$category}_monitoring_monthly"] ?? $meta['default_monitoring_monthly'] ?? 0
                ));
            }
        }

        Setting::setMany($settings);

        if (isset($settings['partner_defaults.valuer.base_cost'])
            || isset($settings['partner_defaults.valuer.markup_percent'])
            || isset($settings['partner_defaults.valuer.has_markup'])) {
            app(ValuationPricingService::class)->syncChargesFees();
        }
    }

    /** Merge optional rate override into partner metadata on create/update. */
    public function applyPartnerPricingMeta(array $data, ?Partner $existing = null): array
    {
        $rate = $data['service_rate_percent'] ?? null;
        unset($data['service_rate_percent']);

        $meta = (array) ($data['metadata'] ?? $existing?->metadata ?? []);
        if ($rate === null || $rate === '') {
            unset($meta['service_rate_percent']);
        } else {
            $meta['service_rate_percent'] = max(0, (float) $rate);
        }
        $data['metadata'] = $meta === [] ? null : $meta;

        return $data;
    }
}
