<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScreeningChecklistService
{
    /** @return array<string, array{label: string, items: array<string, mixed>, subjects?: list<string>}> */
    public function catalog(?string $subjectKind = null): array
    {
        $catalog = config('screening_checklist', []);
        if (! is_array($catalog)) {
            return [];
        }

        if ($subjectKind === null) {
            return $catalog;
        }

        $kind = $this->kindFromSubject($subjectKind);

        return collect($catalog)
            ->filter(function (array $group) use ($kind) {
                $subjects = $group['subjects'] ?? ['borrower', 'guarantor', 'member'];

                return in_array($kind, $subjects, true);
            })
            ->all();
    }

    public function subjectKey(string $person = 'borrower', ?int $guarantorLinkId = null, ?int $memberId = null): string
    {
        if ($person === 'guarantor' && $guarantorLinkId) {
            return 'guarantor:'.$guarantorLinkId;
        }
        if ($person === 'member' && $memberId) {
            return 'member:'.$memberId;
        }

        return 'borrower';
    }

    public function kindFromSubject(string $subject): string
    {
        if (str_starts_with($subject, 'guarantor:')) {
            return 'guarantor';
        }
        if (str_starts_with($subject, 'member:')) {
            return 'member';
        }

        return 'borrower';
    }

    /**
     * Subject chips for the Review Desk (borrower, guarantors, group members).
     *
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>|null  $groupReview
     * @return list<array{key: string, person: string, g: ?int, m: ?int, label: string, sublabel: ?string, percent: int, done: int, total: int, complete: bool}>
     */
    public function deskSubjects(LoanApplication $application, array $review, ?array $groupReview = null, ?User $actor = null): array
    {
        $subjects = [];
        $isGroup = is_array($groupReview) && ! empty($groupReview['members']);

        if ($isGroup) {
            $members = collect($groupReview['members'] ?? []);
            $leader = $members->first(fn (array $m) => ($m['role'] ?? '') === 'leader');
            $leaderMemberId = (int) ($leader['id'] ?? 0);

            // Leader is the applicant — one chip using the full borrower checklist (not duplicated).
            $leaderVm = $this->viewModel($application, $actor, 'borrower');
            $subjects[] = [
                'key' => 'borrower',
                'person' => 'borrower',
                'g' => null,
                'm' => null,
                'label' => 'Leader',
                'sublabel' => $leader['name'] ?? ($review['customer']->full_name ?? null),
                'percent' => $leaderVm['percent'],
                'done' => $leaderVm['decided'],
                'total' => $leaderVm['total'],
                'complete' => $leaderVm['percent'] >= 100,
                'failed' => $leaderVm['failed'],
            ];

            foreach ($members as $member) {
                $mId = (int) ($member['id'] ?? 0);
                if ($mId < 1) {
                    continue;
                }
                // Skip the leader row — already covered above.
                if ($mId === $leaderMemberId || ($member['role'] ?? '') === 'leader') {
                    continue;
                }

                $vm = $this->viewModel($application, $actor, 'member', null, $mId);
                $subjects[] = [
                    'key' => 'member:'.$mId,
                    'person' => 'member',
                    'g' => null,
                    'm' => $mId,
                    'label' => 'Member',
                    'sublabel' => $member['name'] ?? null,
                    'percent' => $vm['percent'],
                    'done' => $vm['decided'],
                    'total' => $vm['total'],
                    'complete' => $vm['percent'] >= 100,
                    'failed' => $vm['failed'],
                ];
            }
        } else {
            $borrowerVm = $this->viewModel($application, $actor, 'borrower');
            $subjects[] = [
                'key' => 'borrower',
                'person' => 'borrower',
                'g' => null,
                'm' => null,
                'label' => 'Borrower',
                'sublabel' => $review['customer']->full_name ?? null,
                'percent' => $borrowerVm['percent'],
                'done' => $borrowerVm['decided'],
                'total' => $borrowerVm['total'],
                'complete' => $borrowerVm['percent'] >= 100,
                'failed' => $borrowerVm['failed'],
            ];
        }

        foreach (collect($review['guarantors'] ?? []) as $row) {
            if (($row['status'] ?? '') === 'rejected' && ! ($row['profile_complete'] ?? false)) {
                continue;
            }
            $gId = (int) ($row['link_id'] ?? 0);
            if ($gId < 1) {
                continue;
            }
            $vm = $this->viewModel($application, $actor, 'guarantor', $gId);
            $subjects[] = [
                'key' => 'guarantor:'.$gId,
                'person' => 'guarantor',
                'g' => $gId,
                'm' => null,
                'label' => 'Guarantor',
                'sublabel' => $row['name'] ?? null,
                'percent' => $vm['percent'],
                'done' => $vm['decided'],
                'total' => $vm['total'],
                'complete' => $vm['percent'] >= 100,
                'failed' => $vm['failed'],
            ];
        }

        return $subjects;
    }

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>|null  $groupReview
     * @return array{
     *   subject: string,
     *   person: string,
     *   g: ?int,
     *   m: ?int,
     *   groups: list<array<string, mixed>>,
     *   decided: int,
     *   passed: int,
     *   failed: int,
     *   total: int,
     *   percent: int,
     *   can_edit: bool,
     *   subjects: list<array<string, mixed>>,
     *   updated_at: ?string,
     *   updated_by: ?int
     * }
     */
    public function deskViewModel(
        LoanApplication $application,
        array $review,
        ?array $groupReview = null,
        ?User $actor = null,
        string $person = 'borrower',
        ?int $guarantorLinkId = null,
        ?int $memberId = null,
    ): array {
        $actor = $actor ?? auth()->user();
        $subjects = $this->deskSubjects($application, $review, $groupReview, $actor);
        $vm = $this->viewModel($application, $actor, $person, $guarantorLinkId, $memberId, $review, $groupReview);
        $vm['subjects'] = $subjects;
        $vm['person'] = $person;
        $vm['g'] = $guarantorLinkId;
        $vm['m'] = $memberId;

        return $vm;
    }

    /**
     * @param  array<string, mixed>|null  $review
     * @param  array<string, mixed>|null  $groupReview
     * @return array<string, mixed>
     */
    public function viewModel(
        LoanApplication $application,
        ?User $actor = null,
        string $person = 'borrower',
        ?int $guarantorLinkId = null,
        ?int $memberId = null,
        ?array $review = null,
        ?array $groupReview = null,
    ): array {
        $subject = $this->subjectKey($person, $guarantorLinkId, $memberId);
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

        $context = $this->evidenceContext($application, $person, $guarantorLinkId, $memberId, $review, $groupReview);
        $kind = $this->kindFromSubject($subject);
        $systemSuggestions = app(ScreeningChecklistAutoVerdictService::class)
            ->suggest($application, $kind, $context);

        $groups = [];
        $decided = 0;
        $passed = 0;
        $failed = 0;
        $total = 0;
        $collateralApplies = $this->collateralReviewApplies($application);

        foreach ($this->catalog($subject) as $groupKey => $group) {
            $items = [];
            foreach ((array) ($group['items'] ?? []) as $itemKey => $meta) {
                $meta = $this->normalizeItemMeta($meta);
                $fullKey = $groupKey.'.'.$itemKey;
                $row = (array) ($checkedMap[$fullKey] ?? []);
                $suggestion = $systemSuggestions[$fullKey] ?? null;
                $row = $this->applySystemSuggestion($row, $suggestion);
                [$verdict, $autoNa] = $this->resolveItemVerdict((string) $groupKey, $row, $collateralApplies);
                $isSystem = in_array(($row['source'] ?? null), ['system', 'auto_na'], true) && $verdict !== null;
                $total++;
                if ($verdict !== null) {
                    $decided++;
                    if ($verdict === 'pass' || $verdict === 'na') {
                        $passed++;
                    }
                    if ($verdict === 'fail') {
                        $failed++;
                    }
                }
                $by = isset($row['by']) ? (int) $row['by'] : null;
                $items[] = [
                    'key' => $fullKey,
                    'item_key' => $itemKey,
                    'group_key' => $groupKey,
                    'label' => $meta['label'],
                    'risk' => $meta['risk'] ?? 'normal',
                    'evidence_type' => $meta['evidence'],
                    'evidence' => $autoNa
                        ? [
                            'hint' => 'Auto N/A — this loan is not on an asset / collateral path. Items reopen if screening moves it to an asset.',
                            'rows' => [],
                            'photos' => [],
                            'compare' => [],
                            'layout' => null,
                        ]
                        : $this->buildEvidence($meta['evidence'], $context),
                    'fail_reasons' => $meta['fail_reasons'],
                    'verdict' => $verdict,
                    'auto_na' => $autoNa,
                    'system_checked' => $isSystem,
                    'checked' => $verdict === 'pass' || $verdict === 'na',
                    'fail_reason_code' => $autoNa ? null : ($row['fail_reason_code'] ?? null),
                    'fail_reason_custom' => $autoNa ? null : ($row['fail_reason_custom'] ?? null),
                    'fail_reason_label' => $autoNa ? null : $this->failReasonLabel($meta['fail_reasons'], $row),
                    'at' => $autoNa ? null : ($row['at'] ?? null),
                    'by' => $autoNa ? null : $by,
                    'by_name' => $isSystem ? 'System' : ($autoNa ? null : ($by ? ($names[$by] ?? null) : null)),
                ];
            }
            if ($items === []) {
                continue;
            }
            $groupDecided = collect($items)->whereNotNull('verdict')->count();
            $groupFailed = collect($items)->where('verdict', 'fail')->count();
            $groups[] = [
                'key' => (string) $groupKey,
                'label' => (string) ($group['label'] ?? ucfirst(str_replace('_', ' ', (string) $groupKey))),
                'phase' => (string) ($group['phase'] ?? 'general'),
                'phase_label' => (string) ($group['phase_label'] ?? ''),
                'items' => $items,
                'decided' => $groupDecided,
                'total' => count($items),
                'failed' => $groupFailed,
                'complete' => $groupDecided === count($items) && count($items) > 0,
            ];
        }

        $actor = $actor ?? auth()->user();

        return [
            'subject' => $subject,
            'groups' => $groups,
            'decided' => $decided,
            'passed' => $passed,
            'failed' => $failed,
            'checked' => $passed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($decided / $total) * 100) : 0,
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
     * @param  array<string, mixed>  $checks  nested group => item => ['verdict'=>..., 'fail_reason_code'=>..., 'fail_reason_custom'=>...]
     */
    public function save(
        LoanApplication $application,
        User $actor,
        array $checks,
        string $person = 'borrower',
        ?int $guarantorLinkId = null,
        ?int $memberId = null,
    ): array {
        if (! $actor->hasPermission('applications.review')) {
            abort(403);
        }

        $subject = $this->subjectKey($person, $guarantorLinkId, $memberId);
        $checks = $this->flattenVerdictChecks($checks, $subject);

        $validKeys = [];
        $failReasonMaps = [];
        foreach ($this->catalog($subject) as $groupKey => $group) {
            foreach ((array) ($group['items'] ?? []) as $itemKey => $meta) {
                $meta = $this->normalizeItemMeta($meta);
                $full = $groupKey.'.'.$itemKey;
                $validKeys[] = $full;
                $failReasonMaps[$full] = $meta['fail_reasons'];
            }
        }

        return DB::transaction(function () use ($application, $actor, $checks, $validKeys, $failReasonMaps, $subject, $person, $guarantorLinkId, $memberId) {
            $application->refresh();
            $payload = (array) ($application->screening_payload ?? []);
            $root = (array) ($payload['screening_checklist'] ?? []);
            $bySubject = (array) ($root['by_subject'] ?? []);

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
                $incoming = (array) ($checks[$key] ?? []);
                $verdict = $this->normalizeVerdict($incoming + ['checked' => $incoming['checked'] ?? null]);

                if ($verdict === null && array_key_exists($key, $checks) && ($incoming['verdict'] ?? '') === '') {
                    unset($items[$key]);
                    continue;
                }

                if ($verdict === null) {
                    // Not submitted — leave existing
                    continue;
                }

                if ($verdict === 'fail') {
                    $code = trim((string) ($incoming['fail_reason_code'] ?? ''));
                    $custom = trim((string) ($incoming['fail_reason_custom'] ?? ''));
                    $allowed = $failReasonMaps[$key] ?? [];
                    if ($code === '' || ! array_key_exists($code, $allowed)) {
                        throw new \InvalidArgumentException("Select a fail reason for {$key}.");
                    }
                    if ($code === 'custom' && $custom === '') {
                        throw new \InvalidArgumentException("Write a custom fail reason for {$key}.");
                    }
                    $items[$key] = [
                        'verdict' => 'fail',
                        'checked' => false,
                        'fail_reason_code' => $code,
                        'fail_reason_custom' => $code === 'custom' ? $custom : null,
                        'at' => now()->toIso8601String(),
                        'by' => $actor->id,
                    ];
                } else {
                    $entry = [
                        'verdict' => $verdict,
                        'checked' => $verdict === 'pass' || $verdict === 'na',
                        'fail_reason_code' => null,
                        'fail_reason_custom' => null,
                        'at' => now()->toIso8601String(),
                        'by' => $actor->id,
                    ];
                    // Tag auto N/A on collateral when the loan is not on an asset path.
                    if (str_starts_with($key, 'collateral.') && $verdict === 'na' && ! $this->collateralReviewApplies($application)) {
                        $entry['source'] = 'auto_na';
                    }
                    $items[$key] = $entry;
                }
            }

            $this->syncCollateralAutoNa($application, $items, $validKeys, $actor);
            $this->syncSystemVerdicts($application, $items, $validKeys, $person, $guarantorLinkId, $memberId);

            // Full replace mode: if form posted all keys with verdicts (including empty clear)
            if (($checks['__replace_all'] ?? false) === true) {
                foreach ($validKeys as $key) {
                    if (! array_key_exists($key, $checks)) {
                        unset($items[$key]);
                    }
                }
            }

            $bySubject[$subject] = [
                'items' => $items,
                'updated_at' => now()->toIso8601String(),
                'updated_by' => $actor->id,
            ];

            $payload['screening_checklist'] = [
                'by_subject' => $bySubject,
                'items' => (array) ($bySubject['borrower']['items'] ?? []),
                'updated_at' => $bySubject['borrower']['updated_at'] ?? now()->toIso8601String(),
                'updated_by' => $bySubject['borrower']['updated_by'] ?? $actor->id,
            ];

            $application->update(['screening_payload' => $payload]);

            return $this->viewModel($application->fresh(), $actor, $person, $guarantorLinkId, $memberId);
        });
    }

    /** @param  array<string, mixed>|string  $meta */
    private function normalizeItemMeta(array|string $meta): array
    {
        if (is_string($meta)) {
            return [
                'label' => $meta,
                'evidence' => 'generic',
                'fail_reasons' => ['custom' => 'Other (write reason)'],
                'risk' => 'normal',
            ];
        }

        $risk = (string) ($meta['risk'] ?? 'normal');
        if (! in_array($risk, ['critical', 'elevated', 'normal'], true)) {
            $risk = 'normal';
        }

        return [
            'label' => (string) ($meta['label'] ?? 'Check'),
            'evidence' => (string) ($meta['evidence'] ?? 'generic'),
            'fail_reasons' => (array) ($meta['fail_reasons'] ?? ['custom' => 'Other (write reason)']),
            'risk' => $risk,
        ];
    }

    /**
     * Apply system suggestion when the item has no human verdict yet (or was previously system-set).
     *
     * @param  array<string, mixed>  $row
     * @param  array{verdict?: string, fail_reason_code?: string|null, source?: string}|null  $suggestion
     * @return array<string, mixed>
     */
    private function applySystemSuggestion(array $row, ?array $suggestion): array
    {
        if ($suggestion === null) {
            return $row;
        }
        $source = (string) ($suggestion['source'] ?? '');
        $existing = $this->normalizeVerdict($row);
        $existingSource = (string) ($row['source'] ?? '');

        // system_skip = human must decide. If a prior system auto-verdict is stale (e.g. photos
        // arrived after a photos_missing Fail), clear it so the item reopens for review.
        if ($source === 'system_skip' || ($suggestion['verdict'] ?? '') === '') {
            if ($existing !== null && in_array($existingSource, ['system', 'auto_na'], true)) {
                return [
                    'verdict' => null,
                    'checked' => false,
                    'source' => null,
                    'fail_reason_code' => null,
                    'fail_reason_custom' => null,
                    'at' => null,
                    'by' => null,
                ];
            }

            return $row;
        }

        $humanLocked = $existing !== null && ! in_array($existingSource, ['system', 'auto_na', ''], true);
        if ($humanLocked) {
            return $row;
        }

        return [
            'verdict' => $suggestion['verdict'],
            'checked' => in_array($suggestion['verdict'], ['pass', 'na'], true),
            'source' => $source === 'auto_na' ? 'auto_na' : 'system',
            'fail_reason_code' => $suggestion['fail_reason_code'] ?? null,
            'fail_reason_custom' => null,
            'at' => $row['at'] ?? now()->toIso8601String(),
            'by' => $row['by'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function normalizeVerdict(array $row): ?string
    {
        $verdict = strtolower(trim((string) ($row['verdict'] ?? '')));
        if (in_array($verdict, ['pass', 'fail', 'na'], true)) {
            return $verdict;
        }
        // Legacy checkbox
        if (array_key_exists('checked', $row) && $row['checked']) {
            return 'pass';
        }

        return null;
    }

    /**
     * Collateral checklist applies when the product is asset-backed, collateral is already
     * attached, or screening has moved the file onto the secure-with-asset path.
     */
    public function collateralReviewApplies(LoanApplication $application): bool
    {
        $application->loadMissing(['product', 'collateralAssets']);

        $product = $application->product;
        if ($product) {
            if ((bool) $product->requires_collateral) {
                return true;
            }

            $category = strtolower((string) ($product->category ?? ''));
            if (in_array($category, ['asset_finance', 'asset_lending'], true)) {
                return true;
            }

            $assetCode = strtoupper((string) config('asset_marketplace.asset_loan_product_code', 'AL'));
            if (strtoupper((string) ($product->code ?? '')) === $assetCode) {
                return true;
            }
        }

        if ($application->collateralAssets->isNotEmpty()) {
            return true;
        }

        $secure = app(CollateralSecureService::class)->state($application);
        if (! is_array($secure)) {
            return false;
        }

        // Rejected / expired secure attempts fall back to non-asset (auto N/A again).
        return ! in_array((string) ($secure['status'] ?? ''), [
            CollateralSecureService::STATUS_REJECTED,
            CollateralSecureService::STATUS_EXPIRED,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: ?string, 1: bool} [verdict, auto_na]
     */
    private function resolveItemVerdict(string $groupKey, array $row, bool $collateralApplies): array
    {
        $stored = $this->normalizeVerdict($row);
        $isAutoNa = ($row['source'] ?? null) === 'auto_na';

        if ($groupKey !== 'collateral') {
            return [$stored, false];
        }

        if (! $collateralApplies) {
            // Non-asset loans: auto N/A unless a reviewer already recorded pass/fail.
            if (in_array($stored, ['pass', 'fail'], true)) {
                return [$stored, false];
            }

            return ['na', true];
        }

        // Asset path: clear stale auto-N/A so collateral checks reopen for review.
        if ($isAutoNa && $stored === 'na') {
            return [null, false];
        }

        return [$stored, false];
    }

    /**
     * Persist system Pass/Fail for undecided items (does not override human verdicts).
     *
     * @param  array<string, mixed>  $items
     * @param  list<string>  $validKeys
     */
    private function syncSystemVerdicts(
        LoanApplication $application,
        array &$items,
        array $validKeys,
        string $person,
        ?int $guarantorLinkId,
        ?int $memberId,
    ): void {
        $context = $this->evidenceContext($application, $person, $guarantorLinkId, $memberId, null, null);
        $kind = $this->kindFromSubject($this->subjectKey($person, $guarantorLinkId, $memberId));
        $suggestions = app(ScreeningChecklistAutoVerdictService::class)->suggest($application, $kind, $context);

        foreach ($validKeys as $key) {
            $suggestion = $suggestions[$key] ?? null;
            if ($suggestion === null || ($suggestion['source'] ?? '') === 'system_skip' || ($suggestion['verdict'] ?? '') === '') {
                continue;
            }
            $current = (array) ($items[$key] ?? []);
            $existing = $this->normalizeVerdict($current);
            $existingSource = (string) ($current['source'] ?? '');
            if ($existing !== null && ! in_array($existingSource, ['system', 'auto_na', ''], true)) {
                continue;
            }
            $items[$key] = [
                'verdict' => $suggestion['verdict'],
                'checked' => in_array($suggestion['verdict'], ['pass', 'na'], true),
                'source' => ($suggestion['source'] ?? '') === 'auto_na' ? 'auto_na' : 'system',
                'fail_reason_code' => $suggestion['fail_reason_code'] ?? null,
                'fail_reason_custom' => null,
                'at' => now()->toIso8601String(),
                'by' => null,
            ];
        }
    }

    /**
     * Keep collateral auto-N/A in sync with whether the loan is on an asset path.
     *
     * @param  array<string, mixed>  $items
     * @param  list<string>  $validKeys
     */
    private function syncCollateralAutoNa(LoanApplication $application, array &$items, array $validKeys, User $actor): void
    {
        $applies = $this->collateralReviewApplies($application);

        foreach ($validKeys as $key) {
            if (! str_starts_with($key, 'collateral.')) {
                continue;
            }

            if (! $applies) {
                $current = (array) ($items[$key] ?? []);
                $verdict = $this->normalizeVerdict($current);
                if (in_array($verdict, ['pass', 'fail'], true)) {
                    continue;
                }
                $items[$key] = [
                    'verdict' => 'na',
                    'checked' => true,
                    'source' => 'auto_na',
                    'fail_reason_code' => null,
                    'fail_reason_custom' => null,
                    'at' => now()->toIso8601String(),
                    'by' => $actor->id,
                ];
                continue;
            }

            if (($items[$key]['source'] ?? null) === 'auto_na') {
                unset($items[$key]);
            }
        }
    }

    /**
     * @param  array<string, string>  $reasons
     * @param  array<string, mixed>  $row
     */
    private function failReasonLabel(array $reasons, array $row): ?string
    {
        $code = $row['fail_reason_code'] ?? null;
        if (! $code) {
            return null;
        }
        if ($code === 'custom') {
            return $row['fail_reason_custom'] ?? 'Other';
        }

        return $reasons[$code] ?? (string) $code;
    }

    /**
     * @param  array<string, mixed>  $checks
     * @return array<string, mixed>
     */
    private function flattenVerdictChecks(array $checks, string $subject): array
    {
        $flat = [];
        $replaceAll = (bool) ($checks['_replace_all'] ?? $checks['__replace_all'] ?? false);

        foreach ($checks as $groupKey => $value) {
            if (in_array($groupKey, ['_replace_all', '__replace_all'], true)) {
                continue;
            }
            if (! is_array($value)) {
                continue;
            }
            foreach ($value as $itemKey => $itemValue) {
                if (is_array($itemValue)) {
                    $flat[$groupKey.'.'.$itemKey] = $itemValue;
                } elseif ($itemValue === '1' || $itemValue === 1 || $itemValue === true || $itemValue === 'on') {
                    $flat[$groupKey.'.'.$itemKey] = ['verdict' => 'pass'];
                }
            }
        }

        if ($replaceAll) {
            $flat['__replace_all'] = true;
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>|null  $review
     * @param  array<string, mixed>|null  $groupReview
     * @return array<string, mixed>
     */
    private function evidenceContext(
        LoanApplication $application,
        string $person,
        ?int $guarantorLinkId,
        ?int $memberId,
        ?array $review,
        ?array $groupReview,
    ): array {
        $review = $review ?? [];
        $customer = $review['customer'] ?? $application->customer;
        $crb = $review['crb'] ?? [];
        $facePhotos = $review['face_photos'] ?? [];
        $nidaPhoto = $review['nida_photo_path'] ?? null;
        $afford = $review['affordability'] ?? [];
        $docs = [
            'required' => (int) ($review['required_docs'] ?? 0),
            'satisfied' => (int) ($review['satisfied_docs'] ?? 0),
            'uploaded' => (int) ($review['uploaded_docs'] ?? 0),
            'progress' => (int) ($review['document_progress'] ?? 0),
            'missing' => array_values(array_filter((array) ($review['missing_documents'] ?? []))),
            'files' => [],
            'id_files' => [],
        ];

        // Product requirement uploads for in-checklist preview.
        foreach (collect($review['uploads'] ?? []) as $upload) {
            if (! is_object($upload) && ! is_array($upload)) {
                continue;
            }
            $path = is_object($upload) ? ($upload->file_path ?? null) : ($upload['file_path'] ?? null);
            $label = is_object($upload)
                ? ($upload->documentType->name ?? $upload->original_name ?? 'Document')
                : ($upload['label'] ?? 'Document');
            $status = is_object($upload) ? ($upload->status ?? null) : ($upload['status'] ?? null);
            if (! is_string($path) || ! filled($path)) {
                continue;
            }
            $docs['files'][] = [
                'label' => (string) $label,
                'url' => asset('storage/'.$path),
                'status' => (string) ($status ?: 'uploaded'),
            ];
        }

        foreach (collect($review['id_documents'] ?? []) as $code => $doc) {
            if (! is_object($doc) && ! is_array($doc)) {
                continue;
            }
            $path = is_object($doc) ? ($doc->file_path ?? null) : ($doc['file_path'] ?? null);
            $label = is_object($doc)
                ? ($doc->documentType->name ?? (is_string($code) ? ucfirst(str_replace('_', ' ', $code)) : 'ID'))
                : ($doc['label'] ?? 'ID');
            if (! is_string($path) || ! filled($path)) {
                continue;
            }
            $docs['id_files'][] = [
                'label' => (string) $label,
                'url' => asset('storage/'.$path),
            ];
        }

        if ($person === 'guarantor' && $guarantorLinkId) {
            $row = collect($review['guarantors'] ?? [])->first(
                fn ($g) => (int) ($g['link_id'] ?? 0) === $guarantorLinkId
            );
            if ($row) {
                $file = $row['file'] ?? [];
                $customer = $row['customer'] ?? ($file['customer'] ?? $customer);
                $crb = $row['crb'] ?? ($file['crb'] ?? $crb);
                $facePhotos = $file['face_photos'] ?? $facePhotos;
                $nidaPhoto = $file['nida_photo_path'] ?? $nidaPhoto;
                $afford = $file['affordability'] ?? ($row['affordability'] ?? $afford);
            }
        }

        if ($person === 'member' && $memberId && $groupReview) {
            $member = collect($groupReview['members'] ?? [])->first(
                fn ($m) => (int) ($m['id'] ?? 0) === $memberId
            );
            if ($member) {
                $customer = Customer::query()->find($member['customer_id'] ?? 0) ?? $customer;
                $crb = [
                    'score' => $member['crb_score'] ?? null,
                    'recommendation' => $member['crb_status'] ?? null,
                    'existing_loans' => $member['existing_loans'] ?? null,
                    'loan_history' => $member['loan_history'] ?? [],
                ];
            }
        }

        $gSug = $review['guarantor_suggestion'] ?? [];
        $collateral = data_get($application->screening_payload, 'collateral_secure', []);
        $anomalies = $review['anomalies'] ?? null;
        if (! is_array($anomalies)) {
            $anomalies = app(UnderwritingAnomalyService::class)->forApplication($application, $review);
        }

        return [
            'customer' => $customer,
            'crb' => $crb,
            'face_photos' => $facePhotos,
            'nida_photo_path' => $nidaPhoto,
            'affordability' => $afford,
            'documents' => $docs,
            'guarantor_suggestion' => $gSug,
            'collateral_secure' => $collateral,
            'anomalies' => $anomalies,
            'application' => $application,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{type: string, rows: list<array{label: string, value: string}>, photos: list<array{label: string, url: string}>, hint: ?string}
     */
    private function buildEvidence(string $type, array $ctx): array
    {
        /** @var Customer|null $customer */
        $customer = $ctx['customer'] ?? null;
        $crb = (array) ($ctx['crb'] ?? []);
        $photos = [];
        $rows = [];
        $compare = [];
        $hint = null;
        $layout = null;

        switch ($type) {
            case 'nida_dob':
                $rows = [
                    ['label' => 'NIDA / National ID', 'value' => (string) ($customer?->national_id ?: '—')],
                    ['label' => 'Date of birth', 'value' => optional($customer?->date_of_birth)->format('d M Y') ?: '—'],
                    ['label' => 'NIDA status', 'value' => display_label($customer?->nida_verification_status, 'nida_verification_status') ?: '—'],
                ];
                $hint = 'Do the ID digits and year of birth line up?';
                break;

            case 'name_crb':
                $personal = (array) ($crb['personal'] ?? []);
                $crbName = (string) ($personal['full_name'] ?? data_get($crb, 'identity.full_name') ?: '—');
                $profileName = (string) ($customer?->full_name ?: '—');
                $compare = [
                    $this->compareRow('Full name', $profileName, $crbName),
                ];
                $rows = [
                    ['label' => 'CRB recommendation', 'value' => strtoupper((string) ($crb['recommendation'] ?? '—'))],
                    ['label' => 'CRB score', 'value' => (string) ($crb['score'] ?? '—')],
                ];
                $hint = 'Expand to compare profile name with the CRB file.';
                break;

            case 'marital_crb':
                $personal = (array) ($crb['personal'] ?? []);
                $profileMarital = $customer?->marital_status
                    ? (__('borrower.profile.marital_options.'.$customer->marital_status) ?: $customer->marital_status)
                    : '—';
                $crbMarital = (string) ($personal['marital_status'] ?? '—');
                $spouseProfile = trim(implode(' ', array_filter([
                    $customer?->spouse_first_name,
                    $customer?->spouse_middle_name,
                    $customer?->spouse_last_name,
                ])));
                $spouseCrb = collect($personal['spouses'] ?? [])->pluck('name')->filter()->implode(' ');
                if ($spouseCrb === '') {
                    $spouseCrb = collect($personal['related_persons'] ?? [])
                        ->filter(fn ($r) => str_contains(strtolower((string) ($r['relation'] ?? '')), 'spouse'))
                        ->pluck('name')
                        ->filter()
                        ->implode(' ');
                }
                $childrenProfile = $customer?->number_of_children !== null ? (string) $customer->number_of_children : '—';
                $childrenCrb = array_key_exists('number_of_children', $personal) && $personal['number_of_children'] !== null
                    ? (string) $personal['number_of_children']
                    : '—';

                $compare = [
                    $this->compareRow('Marital status', $profileMarital, $crbMarital !== '' ? $crbMarital : '—'),
                    $this->compareRow('Spouse name', $spouseProfile !== '' ? $spouseProfile : '—', $spouseCrb !== '' ? $spouseCrb : '—'),
                    $this->compareRow('Number of children', $childrenProfile, $childrenCrb),
                ];
                $rows = [];
                $hint = 'Expand to compare what the borrower entered with CRB personal data.';
                break;

            case 'face_nida':
                $facePhotos = $ctx['face_photos'] ?? [];
                if ($facePhotos instanceof \Illuminate\Support\Collection) {
                    $facePhotos = $facePhotos->all();
                }
                $frontPhoto = null;
                $supportFaces = [];
                foreach ((array) $facePhotos as $angle => $entry) {
                    $path = $entry;
                    if (is_object($entry) && isset($entry->file_path)) {
                        $path = $entry->file_path;
                    } elseif (is_array($entry)) {
                        $path = $entry['file_path'] ?? $entry['path'] ?? null;
                    }
                    if (! is_string($path) || ! filled($path)) {
                        continue;
                    }
                    $pack = [
                        'label' => is_string($angle) ? ucfirst(str_replace('_', ' ', $angle)) : 'Face',
                        'url' => asset('storage/'.$path),
                        'role' => 'face_support',
                    ];
                    if (is_string($angle) && strtolower($angle) === 'front') {
                        $pack['label'] = 'Front face capture';
                        $pack['role'] = 'face';
                        $frontPhoto = $pack;
                    } else {
                        $supportFaces[] = $pack;
                    }
                }
                if ($frontPhoto === null && $supportFaces !== []) {
                    $frontPhoto = $supportFaces[0];
                    $frontPhoto['label'] = 'Face capture';
                    $frontPhoto['role'] = 'face';
                    array_shift($supportFaces);
                }
                if ($frontPhoto !== null) {
                    $photos[] = $frontPhoto;
                }

                // Prefer uploaded ID card, then bureau NIDA photo (CRB never returns a portrait).
                $idFiles = (array) data_get($ctx, 'documents.id_files', []);
                $idPhoto = null;
                foreach ($idFiles as $file) {
                    if (empty($file['url'])) {
                        continue;
                    }
                    $label = strtolower((string) ($file['label'] ?? ''));
                    $candidate = [
                        'label' => (string) ($file['label'] ?? 'Identification card'),
                        'url' => (string) $file['url'],
                        'role' => 'id',
                    ];
                    if (str_contains($label, 'national') || str_contains($label, 'nida') || str_contains($label, 'front')) {
                        $idPhoto = $candidate;
                        break;
                    }
                    $idPhoto ??= $candidate;
                }
                if ($idPhoto === null) {
                    $nidaPath = $ctx['nida_photo_path'] ?? null;
                    if (is_object($nidaPath) && isset($nidaPath->file_path)) {
                        $nidaPath = $nidaPath->file_path;
                    } elseif (is_array($nidaPath)) {
                        $nidaPath = $nidaPath['file_path'] ?? $nidaPath['path'] ?? null;
                    }
                    if (is_string($nidaPath) && filled($nidaPath)) {
                        $idPhoto = [
                            'label' => 'NIDA bureau photo (backup)',
                            'url' => asset('storage/'.$nidaPath),
                            'role' => 'id',
                        ];
                    }
                }
                if ($idPhoto !== null) {
                    $photos[] = $idPhoto;
                }
                foreach ($supportFaces as $extra) {
                    $photos[] = $extra;
                }

                $rows = [
                    ['label' => 'Face status', 'value' => display_label($customer?->face_verification_status, 'face_verification_status') ?: '—'],
                    ['label' => 'Compare', 'value' => 'Front face vs uploaded ID — CRB has no portrait'],
                ];
                $hint = ($frontPhoto && $idPhoto)
                    ? 'Side-by-side: confirm the person in the face capture is the same person on the ID. Other angles are supporting only.'
                    : 'Need both a face capture and an uploaded ID (or NIDA bureau backup) before you can Pass.';
                $layout = 'face_id_compare';
                break;

            case 'phone':
                $rows = [
                    ['label' => 'Mobile number', 'value' => (string) ($customer?->phone ?: '—')],
                ];
                $hint = 'Confirm ownership with the customer if needed.';
                break;

            case 'crb_loans':
                $history = collect($crb['loan_history'] ?? [])->take(5);
                $aff = (array) ($ctx['affordability'] ?? []);
                $proposed = (float) ($aff['proposed_installment'] ?? $aff['new_emi'] ?? 0);
                $capacity = (float) ($aff['available_capacity'] ?? 0);
                $existingOblig = (float) ($aff['existing_obligations'] ?? 0);
                $crbOut = (float) ($crb['outstanding_balance'] ?? 0);
                $affVerdict = strtolower((string) ($aff['verdict'] ?? (($aff['pass'] ?? null) === true ? 'pass' : (($aff['pass'] ?? null) === false ? 'fail' : ''))));
                $rows = [
                    ['label' => 'Loans at other institutions', 'value' => (string) ($crb['existing_loans'] ?? $history->count() ?: '0')],
                    ['label' => 'CRB outstanding', 'value' => $crbOut > 0 ? format_money($crbOut) : '—'],
                    ['label' => 'Profile existing obligations (EMI)', 'value' => $existingOblig > 0 ? format_money($existingOblig) : '—'],
                    ['label' => 'Proposed KopaFasta instalment', 'value' => $proposed > 0 ? format_money($proposed) : '—'],
                    ['label' => 'Available capacity', 'value' => $capacity > 0 || array_key_exists('available_capacity', $aff) ? format_money($capacity) : '—'],
                    ['label' => 'Affordability verdict', 'value' => $affVerdict !== '' ? strtoupper($affVerdict) : '—'],
                    ['label' => 'Delinquencies', 'value' => (string) ($crb['delinquencies'] ?? '—')],
                    ['label' => 'CRB recommendation', 'value' => strtoupper((string) ($crb['recommendation'] ?? '—'))],
                    ['label' => 'Bank / M-Pesa statements', 'value' => 'Capacity → Documents (filter Missing / To verify) · also Personal → Activity income check'],
                ];
                foreach ($history as $loan) {
                    $rows[] = [
                        'label' => (string) ($loan['institution'] ?? $loan['lender'] ?? 'Loan'),
                        'value' => trim(($loan['status'] ?? '').' · '.($loan['balance'] ?? $loan['amount'] ?? '')),
                    ];
                }
                $hint = 'High risk if capacity cannot carry this loan on top of other-institution debt. Affordability already folds in declared obligations — confirm CRB exposure matches, then Pass/Fail.';
                break;

            case 'anomalies':
                $flags = collect($ctx['anomalies'] ?? [])->values();
                $critical = $flags->where('severity', 'critical')->count();
                $warning = $flags->where('severity', 'warning')->count();
                $rows = [
                    ['label' => 'Critical flags', 'value' => (string) $critical],
                    ['label' => 'Warning flags', 'value' => (string) $warning],
                    ['label' => 'Where listed', 'value' => 'Review flags strip at the top of the checklist workspace'],
                ];
                foreach ($flags->take(8) as $flag) {
                    $rows[] = [
                        'label' => strtoupper((string) ($flag['severity'] ?? 'info')).' · '.((string) ($flag['title'] ?? 'Flag')),
                        'value' => (string) ($flag['detail'] ?? '—'),
                    ];
                }
                $hint = $critical + $warning > 0
                    ? 'Read each flag above (and the Review flags strip). Pass only after you addressed them in notes / decision rationale — or Fail if they kill the file.'
                    : 'No critical/warning flags right now. Pass if you are comfortable; re-check if new CRB/docs arrive.';
                break;

            case 'recommendation_gate':
                $rows = [
                    ['label' => 'What this means', 'value' => 'Your attestation that this subject is ready for Decision / committee'],
                    ['label' => 'Does not auto-submit', 'value' => 'Pass here does not record the recommendation — open Decision to submit Approve / Reject / Counter'],
                    ['label' => 'When to Pass', 'value' => 'Other checklist items on this subject are decided and high-risk fails are handled'],
                ];
                $hint = 'This is a final “ready” checkbox for the screener — not the committee decision itself.';
                break;

            case 'residence':
                $rows = [
                    ['label' => 'Region', 'value' => (string) ($customer?->region ?: '—')],
                    ['label' => 'District', 'value' => (string) ($customer?->district ?: '—')],
                    ['label' => 'Ward', 'value' => (string) ($customer?->ward ?: '—')],
                    ['label' => 'Street / address', 'value' => (string) ($customer?->street_address ?? $customer?->address ?? '—')],
                    ['label' => 'LGO / letter signatory', 'value' => (string) ($customer?->lga_officer_name ?: '—')],
                    ['label' => 'Signatory position', 'value' => (string) ($customer?->lga_officer_position ?: '—')],
                    ['label' => 'Signatory phone', 'value' => (string) ($customer?->lga_officer_phone ?: '—')],
                ];
                $hint = filled($customer?->lga_officer_phone)
                    ? 'System only checks that address + LGO name/phone are filled — not that they match CRB or a letter. Call the LGO yourself to confirm.'
                    : 'Residence incomplete — borrower must add address and LGO officer name + phone before you can call them.';
                break;

            case 'residence_proof':
                $docs = (array) ($ctx['documents'] ?? []);
                $files = collect($docs['files'] ?? [])->filter(function ($file) {
                    $label = strtolower((string) ($file['label'] ?? $file['code'] ?? ''));
                    $code = strtolower((string) ($file['code'] ?? ''));

                    return str_contains($label, 'resid')
                        || str_contains($label, 'utility')
                        || str_contains($label, 'letter')
                        || str_contains($code, 'resid')
                        || str_contains($code, 'utility')
                        || str_contains($code, 'lga')
                        || str_contains($code, 'lgo');
                })->values();
                $rows = [
                    ['label' => 'Residence / utility files', 'value' => (string) $files->count()],
                    ['label' => 'How confirmed', 'value' => 'You review the letter / utility image — system only counts uploads'],
                ];
                foreach ($files->take(8) as $file) {
                    if (! empty($file['url'])) {
                        $photos[] = [
                            'label' => trim(($file['label'] ?? 'Residence proof').(isset($file['status']) ? ' · '.$file['status'] : '')),
                            'url' => (string) $file['url'],
                        ];
                    }
                }
                $hint = $photos === []
                    ? 'No residence / utility proof uploaded yet — request a re-upload if needed.'
                    : 'Open the images and confirm they match the stated address. System does not auto-verify the letter.';
                break;

            case 'activity':
                $rows = [
                    ['label' => 'Occupation / activity', 'value' => (string) ($customer?->occupation ?? $customer?->business_type ?? '—')],
                    ['label' => 'Employer / business', 'value' => (string) ($customer?->employer_name ?? $customer?->business_name ?? '—')],
                ];
                $hint = 'Screening judgment — system shows profile fields only.';
                break;

            case 'affordability':
                $aff = (array) ($ctx['affordability'] ?? []);
                $rows = [
                    ['label' => 'Verdict', 'value' => strtoupper((string) ($aff['verdict'] ?? ($aff['pass'] ?? false ? 'pass' : '—')))],
                    ['label' => 'Capacity', 'value' => isset($aff['available_capacity']) ? format_money((float) $aff['available_capacity']) : '—'],
                    ['label' => 'Proposed EMI', 'value' => isset($aff['proposed_installment']) ? format_money((float) $aff['proposed_installment']) : '—'],
                    ['label' => 'How confirmed', 'value' => 'System runs affordability math; you judge income evidence quality'],
                ];
                $hint = 'System calculates capacity vs proposed installment. Confirm the underlying income evidence yourself.';
                break;

            case 'id_docs':
                $idFiles = (array) data_get($ctx, 'documents.id_files', []);
                foreach ($idFiles as $file) {
                    if (! empty($file['url'])) {
                        $photos[] = [
                            'label' => (string) ($file['label'] ?? 'ID'),
                            'url' => (string) $file['url'],
                        ];
                    }
                }
                $rows = [
                    ['label' => 'ID documents on file', 'value' => (string) count($photos)],
                    ['label' => 'NIDA / National ID', 'value' => (string) ($customer?->national_id ?: '—')],
                ];
                $hint = $photos === []
                    ? 'No ID images on file yet — request a re-upload if needed.'
                    : 'Compare ID image quality here. Request a re-upload if unclear.';
                break;

            case 'documents':
                $docs = (array) ($ctx['documents'] ?? []);
                $missing = collect($docs['missing'] ?? [])->filter()->values();
                $rows = [
                    ['label' => 'Product docs verified', 'value' => ($docs['satisfied'] ?? 0).' / '.($docs['required'] ?? 0)],
                    ['label' => 'Uploaded', 'value' => (string) ($docs['uploaded'] ?? 0)],
                    ['label' => 'Progress', 'value' => ($docs['progress'] ?? 0).'%'],
                    ['label' => 'How confirmed', 'value' => 'System counts required product docs marked verified'],
                ];
                if ($missing->isNotEmpty()) {
                    $rows[] = [
                        'label' => 'Missing / unverified',
                        'value' => $missing->take(8)->implode(', ').($missing->count() > 8 ? '…' : ''),
                    ];
                }
                foreach (array_slice((array) ($docs['files'] ?? []), 0, 12) as $file) {
                    if (! empty($file['url'])) {
                        $photos[] = [
                            'label' => trim(($file['label'] ?? 'Document').(isset($file['status']) ? ' · '.$file['status'] : '')),
                            'url' => (string) $file['url'],
                        ];
                    }
                }
                $hint = $photos === []
                    ? 'No product documents uploaded yet. “0 / N” means none of the required types are verified yet.'
                    : 'System Pass when every required product document is verified. Follow-up requests are a separate check.';
                break;

            case 'doc_requests':
                $rows = [
                    ['label' => 'How confirmed', 'value' => 'System Pass when no open follow-up document requests remain'],
                    ['label' => 'Tip', 'value' => 'Use Pass/Fail after reviewing follow-up uploads. Request another re-upload from Profiles → Documents if needed.'],
                ];
                $hint = 'System checks whether screening follow-up document requests are still open.';
                break;

            case 'guarantor_contact':
            case 'guarantor_capacity':
                $g = (array) ($ctx['guarantor_suggestion'] ?? []);
                $rows = [
                    ['label' => 'Guarantor', 'value' => (string) ($g['name'] ?? '—')],
                    ['label' => 'Signal', 'value' => strtoupper((string) ($g['recommendation'] ?? '—'))],
                    ['label' => 'Summary', 'value' => (string) ($g['summary'] ?? '—')],
                ];
                $hint = 'You confirm by calling the guarantor — system only shows contact / CRB signals.';
                break;

            case 'nok_contact':
                $rows = [
                    ['label' => 'Next of kin', 'value' => (string) ($customer?->next_of_kin_name ?? $customer?->kin_name ?? '—')],
                    ['label' => 'Phone', 'value' => (string) ($customer?->next_of_kin_phone ?? $customer?->kin_phone ?? '—')],
                ];
                $hint = 'You confirm by calling next of kin — system cannot place the call.';
                break;

            case 'insurance':
                $cs = (array) ($ctx['collateral_secure'] ?? []);
                $rows = [
                    ['label' => 'Status', 'value' => (string) ($cs['status'] ?? '—')],
                    ['label' => 'Source', 'value' => (string) ($cs['source'] ?? '—')],
                    ['label' => 'Insurance type', 'value' => (string) (data_get($cs, 'insurance.insurance_type') ?: '—')],
                    ['label' => 'Expiry', 'value' => (string) (data_get($cs, 'insurance.expiry') ?: '—')],
                    ['label' => 'How confirmed', 'value' => 'System shows recorded cover; you confirm the policy matches the asset'],
                ];
                $hint = 'System surfaces insurance on file. Confirm type and expiry against the vehicle / asset yourself.';
                break;

            case 'generic':
                $rows = [
                    ['label' => 'How confirmed', 'value' => 'Screening judgment — system does not auto-confirm this item'],
                    ['label' => 'Tip', 'value' => 'Use Pass after you personally completed the check (call, file review, or notes).'],
                ];
                $hint = 'No automatic check — you confirm by completing the action (call, review notes, or committee readiness).';
                break;

            default:
                $rows = [
                    ['label' => 'How confirmed', 'value' => 'Screening judgment — review evidence, then record Pass or Fail'],
                    ['label' => 'Tip', 'value' => 'Review the evidence above, then record Pass or Fail.'],
                ];
                $hint = 'System only shows supporting fields — confirmation is your Pass / Fail.';
        }

        return [
            'type' => $type,
            'rows' => $rows,
            'compare' => $compare,
            'photos' => $photos,
            'hint' => $hint,
            'layout' => $layout,
        ];
    }

    /**
     * @return array{label: string, profile: string, crb: string, status: string}
     */
    private function compareRow(string $label, string $profile, string $crb): array
    {
        $profileNorm = strtolower(trim(preg_replace('/\s+/', ' ', $profile) ?? ''));
        $crbNorm = strtolower(trim(preg_replace('/\s+/', ' ', $crb) ?? ''));
        $status = match (true) {
            $profileNorm === '' || $profileNorm === '—' || $crbNorm === '' || $crbNorm === '—' => 'missing',
            $profileNorm === $crbNorm => 'match',
            default => 'mismatch',
        };

        return [
            'label' => $label,
            'profile' => $profile !== '' ? $profile : '—',
            'crb' => $crb !== '' ? $crb : '—',
            'status' => $status,
        ];
    }
}
