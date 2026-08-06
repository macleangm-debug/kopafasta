<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScreeningChecklistService
{
    /** @return array<string, array{label: string, items: array<string, string>, subjects?: list<string>}> */
    public function catalog(?string $subjectKind = null): array
    {
        $catalog = config('screening_checklist', []);
        if (! is_array($catalog)) {
            return [];
        }

        if ($subjectKind === null) {
            return $catalog;
        }

        $kind = str_starts_with($subjectKind, 'guarantor:') ? 'guarantor' : 'borrower';

        return collect($catalog)
            ->filter(function (array $group) use ($kind) {
                $subjects = $group['subjects'] ?? ['borrower', 'guarantor'];

                return in_array($kind, $subjects, true);
            })
            ->all();
    }

    public function subjectKey(string $person = 'borrower', ?int $guarantorLinkId = null): string
    {
        if ($person === 'guarantor' && $guarantorLinkId) {
            return 'guarantor:'.$guarantorLinkId;
        }

        return 'borrower';
    }

    /**
     * @return array{
     *   subject: string,
     *   groups: list<array{key: string, label: string, items: list<array{key: string, label: string, checked: bool, at: ?string, by: ?int, by_name: ?string}>}>,
     *   checked: int,
     *   total: int,
     *   percent: int,
     *   can_edit: bool,
     *   updated_at: ?string,
     *   updated_by: ?int
     * }
     */
    public function viewModel(
        LoanApplication $application,
        ?User $actor = null,
        string $person = 'borrower',
        ?int $guarantorLinkId = null,
    ): array {
        $subject = $this->subjectKey($person, $guarantorLinkId);
        $state = $this->state($application, $subject);
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

        foreach ($this->catalog($subject) as $groupKey => $group) {
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
            'subject' => $subject,
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
    public function state(LoanApplication $application, string $subject = 'borrower'): array
    {
        $payload = (array) ($application->screening_payload ?? []);
        $root = $payload['screening_checklist'] ?? null;
        if (! is_array($root)) {
            return ['items' => []];
        }

        $bySubject = $root['by_subject'] ?? null;
        if (is_array($bySubject) && isset($bySubject[$subject]) && is_array($bySubject[$subject])) {
            return $bySubject[$subject];
        }

        // Legacy single checklist → borrower only
        if ($subject === 'borrower' && isset($root['items']) && is_array($root['items'])) {
            return [
                'items' => $root['items'],
                'updated_at' => $root['updated_at'] ?? null,
                'updated_by' => $root['updated_by'] ?? null,
            ];
        }

        return ['items' => []];
    }

    /**
     * @param  array<string, mixed>  $checks
     */
    public function save(
        LoanApplication $application,
        User $actor,
        array $checks,
        string $person = 'borrower',
        ?int $guarantorLinkId = null,
    ): array {
        if (! $actor->hasPermission('applications.review')) {
            abort(403);
        }

        $subject = $this->subjectKey($person, $guarantorLinkId);
        $checks = $this->flattenChecks($checks, $subject);

        $validKeys = [];
        foreach ($this->catalog($subject) as $groupKey => $group) {
            foreach (array_keys((array) ($group['items'] ?? [])) as $itemKey) {
                $validKeys[] = $groupKey.'.'.$itemKey;
            }
        }

        return DB::transaction(function () use ($application, $actor, $checks, $validKeys, $subject, $person, $guarantorLinkId) {
            $application->refresh();
            $payload = (array) ($application->screening_payload ?? []);
            $root = (array) ($payload['screening_checklist'] ?? []);
            $bySubject = (array) ($root['by_subject'] ?? []);

            // Preserve legacy borrower items into by_subject on first multi-subject save
            if (! isset($bySubject['borrower']) && isset($root['items']) && is_array($root['items'])) {
                $bySubject['borrower'] = [
                    'items' => $root['items'],
                    'updated_at' => $root['updated_at'] ?? null,
                    'updated_by' => $root['updated_by'] ?? null,
                ];
            }

            $existing = (array) (($bySubject[$subject]['items'] ?? []) ?: []);
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
                } elseif (! $wantChecked) {
                    unset($items[$key]);
                } elseif ($wantChecked && $wasChecked) {
                    $items[$key] = $prev + ['checked' => true];
                }
            }

            $bySubject[$subject] = [
                'items' => $items,
                'updated_at' => now()->toIso8601String(),
                'updated_by' => $actor->id,
            ];

            $payload['screening_checklist'] = [
                'by_subject' => $bySubject,
                // Keep borrower mirror for older readers / committee progress
                'items' => (array) ($bySubject['borrower']['items'] ?? []),
                'updated_at' => $bySubject['borrower']['updated_at'] ?? now()->toIso8601String(),
                'updated_by' => $bySubject['borrower']['updated_by'] ?? $actor->id,
            ];

            $application->update(['screening_payload' => $payload]);

            return $this->viewModel($application->fresh(), $actor, $person, $guarantorLinkId);
        });
    }

    /**
     * @param  array<string, mixed>  $checks
     * @return array<string, mixed>
     */
    private function flattenChecks(array $checks, string $subject): array
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

            foreach ($this->catalog($subject) as $groupKey => $group) {
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
