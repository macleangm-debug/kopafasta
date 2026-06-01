<?php

namespace App\Services;

class DisplayLabelService
{
    public function label(?string $value, ?string $group = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($group === 'role') {
            return app(RoleService::class)->label($value);
        }

        if ($group === 'application_stage' || $group === 'stage') {
            return app(LoanApplicationWorkflowService::class)->stageLabel($value);
        }

        if ($group !== null) {
            $mapped = config("display_labels.groups.{$group}.{$value}");

            if (is_string($mapped) && $mapped !== '') {
                return $mapped;
            }
        }

        foreach (config('display_labels.groups', []) as $labels) {
            if (is_array($labels) && isset($labels[$value]) && $labels[$value] !== '') {
                return (string) $labels[$value];
            }
        }

        return ucwords(str_replace('_', ' ', $value));
    }

    /** @param  list<string>|array<string, string>  $values */
    public function options(array $values, ?string $group = null): array
    {
        $options = [];

        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $options[$value] = $this->label((string) $value, $group);
            } else {
                $options[$key] = is_string($value) && ! str_contains($value, '_')
                    ? $value
                    : $this->label((string) $key, $group);
            }
        }

        return $options;
    }
}
