<?php

namespace App\Services;

use App\Models\Setting;

class PartnerAutoAssignPolicy
{
    /** @return array<string, mixed> */
    public function forRecoveryType(string $type): array
    {
        return $this->resolve('recovery', $type);
    }

    /** @return array<string, mixed> */
    public function forServiceCategory(string $category): array
    {
        return $this->resolve('service', $category);
    }

    public function enabledForRecovery(string $type): bool
    {
        if ($type === 'call_center' && ! $this->legacyCallCenterEnabled()) {
            return false;
        }

        return (bool) $this->forRecoveryType($type)['enabled'];
    }

    public function enabledForService(string $category): bool
    {
        return (bool) $this->forServiceCategory($category)['enabled'];
    }

    public function slaDaysForService(string $category): int
    {
        $days = (int) ($this->forServiceCategory($category)['sla_days'] ?? 5);

        return max(1, min(90, $days));
    }

    /** @return list<string> */
    public function strategies(): array
    {
        return array_keys(config('partner_auto_assign.strategies', []));
    }

    /**
     * @param  array<string, mixed>  $input  Flat request fields like auto_assign_enabled_call_center
     */
    public function saveFromRequest(array $input, bool $autoAssignCallCenterLegacy): void
    {
        $settings = [];

        foreach (array_keys(config('partner_auto_assign.recovery', [])) as $type) {
            $row = $this->normalizeRow($input, 'recovery', $type);
            if ($type === 'call_center') {
                $row['enabled'] = $autoAssignCallCenterLegacy && $row['enabled'];
            }
            foreach ($row as $key => $value) {
                $settings["partner_auto_assign.recovery.{$type}.{$key}"] = $value;
            }
        }

        foreach (array_keys(config('partner_auto_assign.service', [])) as $category) {
            $row = $this->normalizeRow($input, 'service', $category);
            foreach ($row as $key => $value) {
                $settings["partner_auto_assign.service.{$category}.{$key}"] = $value;
            }
        }

        Setting::setMany($settings);
    }

    /** @return array<string, array<string, mixed>> */
    public function allRecoverySettings(): array
    {
        $out = [];
        foreach (array_keys(config('partner_auto_assign.recovery', [])) as $type) {
            $out[$type] = $this->forRecoveryType($type);
        }

        return $out;
    }

    /** @return array<string, array<string, mixed>> */
    public function allServiceSettings(): array
    {
        $out = [];
        foreach (array_keys(config('partner_auto_assign.service', [])) as $category) {
            $out[$category] = $this->forServiceCategory($category);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function resolve(string $group, string $key): array
    {
        $defaults = config("partner_auto_assign.{$group}.{$key}", []);
        $stored = Setting::group('partner_auto_assign') ?? [];

        $prefix = "{$group}.{$key}.";
        $row = $defaults;
        foreach ($defaults as $field => $default) {
            $path = $prefix.$field;
            if (array_key_exists($path, $stored)) {
                $row[$field] = $this->castField($field, $stored[$path]);
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeRow(array $input, string $group, string $key): array
    {
        $defaults = config("partner_auto_assign.{$group}.{$key}", []);
        $suffix = $group === 'recovery' ? $key : 'svc_'.$key;

        $enabled = filter_var($input["auto_assign_enabled_{$suffix}"] ?? $defaults['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $strategy = (string) ($input["auto_assign_strategy_{$suffix}"] ?? $defaults['strategy'] ?? 'least_load');
        if (! in_array($strategy, $this->strategies(), true)) {
            $strategy = 'least_load';
        }

        $maxOpenRaw = $input["auto_assign_max_open_{$suffix}"] ?? null;
        $maxOpen = filled($maxOpenRaw) ? max(1, (int) $maxOpenRaw) : null;

        $weightLoad = max(0, min(100, (int) ($input["auto_assign_weight_load_{$suffix}"] ?? $defaults['weight_load'] ?? 50)));
        $weightEfficiency = max(0, min(100, (int) ($input["auto_assign_weight_efficiency_{$suffix}"] ?? $defaults['weight_efficiency'] ?? 40)));
        $weightFairness = max(0, min(100, (int) ($input["auto_assign_weight_fairness_{$suffix}"] ?? $defaults['weight_fairness'] ?? 10)));
        $sum = max(1, $weightLoad + $weightEfficiency + $weightFairness);

        $row = [
            'enabled' => $enabled,
            'strategy' => $strategy,
            'max_open' => $maxOpen,
            'require_region' => filter_var($input["auto_assign_require_region_{$suffix}"] ?? $defaults['require_region'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'reassign_on_sla' => filter_var($input["auto_assign_reassign_on_sla_{$suffix}"] ?? $defaults['reassign_on_sla'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'weight_load' => (int) round(($weightLoad / $sum) * 100),
            'weight_efficiency' => (int) round(($weightEfficiency / $sum) * 100),
            'weight_fairness' => max(0, 100 - (int) round(($weightLoad / $sum) * 100) - (int) round(($weightEfficiency / $sum) * 100)),
            'cold_start_rate' => max(0, min(100, (float) ($input["auto_assign_cold_start_{$suffix}"] ?? $defaults['cold_start_rate'] ?? 50))),
            'sla_days' => max(1, min(90, (int) ($input["auto_assign_sla_days_{$suffix}"] ?? $defaults['sla_days'] ?? 5))),
        ];

        if ($group === 'recovery') {
            $row['sla_days'] = null;
        }

        return $row;
    }

    private function castField(string $field, mixed $value): mixed
    {
        return match ($field) {
            'enabled', 'require_region', 'reassign_on_sla' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'max_open', 'weight_load', 'weight_efficiency', 'weight_fairness', 'sla_days' => $value === null || $value === ''
                ? null
                : (int) $value,
            'cold_start_rate' => (float) $value,
            default => $value,
        };
    }

    private function legacyCallCenterEnabled(): bool
    {
        return filter_var(
            Setting::get('recovery.auto_assign_call_center', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
