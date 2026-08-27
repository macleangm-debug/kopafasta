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

    public function slaHoursForService(string $category): int
    {
        $hours = $this->forServiceCategory($category)['sla_hours'] ?? null;
        if (filled($hours) && (int) $hours > 0) {
            return max(1, min(90 * 24, (int) $hours));
        }

        return $this->slaDaysForService($category) * 24;
    }

    /** @return list<int> */
    public function remindHoursForService(string $category): array
    {
        $raw = $this->forServiceCategory($category)['remind_hours'] ?? '12,4';
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        return collect($parts)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function graceHoursForService(string $category): int
    {
        return max(0, min(72, (int) ($this->forServiceCategory($category)['grace_hours'] ?? 0)));
    }

    public function maxReassignmentsForService(string $category): int
    {
        return max(1, min(10, (int) ($this->forServiceCategory($category)['max_reassignments'] ?? 3)));
    }

    public function reassignModeForService(string $category): string
    {
        $mode = (string) ($this->forServiceCategory($category)['reassign_mode'] ?? 'auto');

        return in_array($mode, ['auto', 'manual'], true) ? $mode : 'auto';
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

    /**
     * Persist origination / service auto-assign only (valuer, GPS, insurance).
     * Recovery rows are left unchanged.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveOriginationFromRequest(array $input): void
    {
        $settings = [];

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
            'sla_hours' => filled($input["auto_assign_sla_hours_{$suffix}"] ?? null)
                ? max(1, min(90 * 24, (int) $input["auto_assign_sla_hours_{$suffix}"]))
                : ($defaults['sla_hours'] ?? null),
            'remind_hours' => (string) ($input["auto_assign_remind_hours_{$suffix}"] ?? $defaults['remind_hours'] ?? '12,4'),
            'grace_hours' => max(0, min(72, (int) ($input["auto_assign_grace_hours_{$suffix}"] ?? $defaults['grace_hours'] ?? 0))),
            'max_reassignments' => max(1, min(10, (int) ($input["auto_assign_max_reassignments_{$suffix}"] ?? $defaults['max_reassignments'] ?? 3))),
            'reassign_mode' => in_array(($input["auto_assign_reassign_mode_{$suffix}"] ?? $defaults['reassign_mode'] ?? 'auto'), ['auto', 'manual'], true)
                ? (string) ($input["auto_assign_reassign_mode_{$suffix}"] ?? $defaults['reassign_mode'] ?? 'auto')
                : 'auto',
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
            'max_open', 'weight_load', 'weight_efficiency', 'weight_fairness', 'sla_days', 'sla_hours', 'grace_hours', 'max_reassignments' => $value === null || $value === ''
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
