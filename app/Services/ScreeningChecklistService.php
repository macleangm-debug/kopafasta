<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScreeningChecklistService
{
    /** @return array<string, array{label: string, items: array<string, string>}> */
    public function catalog(): array
    {
        $catalog = config('screening_checklist', []);

        return is_array($catalog) ? $catalog : [];
    }

    /**
     * @return array{
     *   groups: list<array{key: string, label: string, items: list<array{key: string, label: string, checked: bool, at: ?string, by: ?int, by_name: ?string}>}>,
     *   checked: int,
     *   total: int,
     *   percent: int,
     *   can_edit: bool,
     *   updated_at: ?string,
     *   updated_by: ?int
     * }
     */
    public function viewModel(LoanApplication $application, ?User $actor = null): array
    {
        $state = $this->state($application);
        $checkedMap = (array) ($state['items'] ?? []);
        $userIds = collect($checkedMap)
            ->pluck('by')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $names = $userIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        $groups = [];
        $checked = 0;
        $total = 0;

        foreach ($this->catalog() as $groupKey => $group) {
            $items = [];
            foreach ((array) ($group['items'] ?? []) as $itemKey => $label) {
                $fullKey = $groupKey.'.'.$itemKey;
                $row = (array) ($checkedMap[$fullKey] ?? []);
                $isChecked = (bool) ($row['checked'] ?? false);
                $total++;
                if ($isChecked) {
                    $checked++;
                }
                $by = isset($row['by']) ? (int) $row['by'] : null;
                $items[] = [
                    'key' => $fullKey,
                    'label' => (string) $label,
                    'checked' => $isChecked,
                    'at' => $row['at'] ?? null,
                    'by' => $by,
                    'by_name' => $by ? ($names[$by] ?? null) : null,
                ];
            }
            if ($items === []) {
                continue;
            }
            $groups[] = [
                'key' => (string) $groupKey,
                'label' => (string) ($group['label'] ?? ucfirst(str_replace('_', ' ', (string) $groupKey))),
                'items' => $items,
            ];
        }

        $actor = $actor ?? auth()->user();

        return [
            'groups' => $groups,
            'checked' => $checked,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($checked / $total) * 100) : 0,
            'can_edit' => $actor?->hasPermission('applications.review') ?? false,
            'updated_at' => $state['updated_at'] ?? null,
            'updated_by' => isset($state['updated_by']) ? (int) $state['updated_by'] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function state(LoanApplication $application): array
    {
        $payload = (array) ($application->screening_payload ?? []);
        $state = $payload['screening_checklist'] ?? null;

        return is_array($state) ? $state : ['items' => []];
    }

    /**
     * @param  array<string, mixed>  $checks  nested group=>item=>checked or flat "group.item"=>checked
     */
    public function save(LoanApplication $application, User $actor, array $checks): array
    {
        if (! $actor->hasPermission('applications.review')) {
            abort(403);
        }

        $checks = $this->flattenChecks($checks);

        $validKeys = [];
        foreach ($this->catalog() as $groupKey => $group) {
            foreach (array_keys((array) ($group['items'] ?? [])) as $itemKey) {
                $validKeys[] = $groupKey.'.'.$itemKey;
            }
        }

        return DB::transaction(function () use ($application, $actor, $checks, $validKeys) {
            $application->refresh();
            $payload = (array) ($application->screening_payload ?? []);
            $existing = (array) (($payload['screening_checklist']['items'] ?? []) ?: []);
            $items = $existing;

            foreach ($validKeys as $key) {
                $raw = $checks[$key] ?? false;
                $wantChecked = filter_var($raw, FILTER_VALIDATE_BOOLEAN)
                    || $raw === 1
                    || $raw === '1'
                    || $raw === 'on';

                $prev = (array) ($existing[$key] ?? []);
                $wasChecked = (bool) ($prev['checked'] ?? false);

                if ($wantChecked && ! $wasChecked) {
                    $items[$key] = [
                        'checked' => true,
                        'at' => now()->toIso8601String(),
                        'by' => $actor->id,
                    ];
                } elseif (! $wantChecked && $wasChecked) {
                    unset($items[$key]);
                } elseif ($wantChecked && $wasChecked) {
                    $items[$key] = $prev + ['checked' => true];
                } else {
                    unset($items[$key]);
                }
            }

            $payload['screening_checklist'] = [
                'items' => $items,
                'updated_at' => now()->toIso8601String(),
                'updated_by' => $actor->id,
            ];

            $application->update(['screening_payload' => $payload]);

            return $this->viewModel($application->fresh(), $actor);
        });
    }

    /**
     * Accept nested form items[group][item]=1 or flat group.item / group_item keys.
     *
     * @param  array<string, mixed>  $checks
     * @return array<string, mixed>
     */
    private function flattenChecks(array $checks): array
    {
        $flat = [];

        foreach ($checks as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $itemKey => $itemValue) {
                    $flat[$key.'.'.$itemKey] = $itemValue;
                }
                continue;
            }

            $key = (string) $key;
            if (str_contains($key, '.')) {
                $flat[$key] = $value;
                continue;
            }

            // PHP converts dots in form names to underscores — map back to catalog keys.
            foreach ($this->catalog() as $groupKey => $group) {
                foreach (array_keys((array) ($group['items'] ?? [])) as $itemKey) {
                    $full = $groupKey.'.'.$itemKey;
                    if ($key === $groupKey.'_'.$itemKey || $key === str_replace('.', '_', $full)) {
                        $flat[$full] = $value;
                    }
                }
            }
        }

        return $flat;
    }
}
