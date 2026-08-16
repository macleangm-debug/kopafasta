<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Collection;
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
            $leaderVm = $this->viewModel($application, $actor, 'borrower', null, null, $review, $groupReview);
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

                // Pass groupReview so each member gets their own evidence / auto-verdicts (not identical chips).
                $vm = $this->viewModel($application, $actor, 'member', null, $mId, $review, $groupReview);
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
            $borrowerVm = $this->viewModel($application, $actor, 'borrower', null, null, $review, $groupReview);
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
            $vm = $this->viewModel($application, $actor, 'guarantor', $gId, null, $review, $groupReview);
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
                $rowSource = (string) ($row['source'] ?? '');
                $isSystem = in_array($rowSource, ['system', 'auto_na'], true) && $verdict !== null;
                $isDocuments = $rowSource === 'documents' && $verdict !== null;
                $documentLink = null;
                if (! $autoNa) {
                    $documentLink = app(ChecklistDocumentBridge::class)->statusForItem(
                        $application,
                        $context['customer'] ?? null,
                        $fullKey,
                    );
                }
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
                $item = [
                    'key' => $fullKey,
                    'item_key' => $itemKey,
                    'group_key' => $groupKey,
                    'label' => $meta['label'],
                    'risk' => $meta['risk'] ?? 'normal',
                    'gate' => $meta['gate'] ?? null,
                    'evidence_type' => $meta['evidence'],
                    'evidence' => $autoNa
                        ? [
                            'hint' => 'Auto N/A — this loan is not on an asset / collateral path. Items reopen if screening moves it to an asset.',
                            'rows' => [],
                            'photos' => [],
                            'documents' => [],
                            'compare' => [],
                            'layout' => null,
                        ]
                        : $this->buildEvidence($meta['evidence'], $context, $itemKey),
                    'fail_reasons' => $meta['fail_reasons'],
                    'verdict' => $verdict,
                    'auto_na' => $autoNa,
                    'system_checked' => $isSystem,
                    'documents_checked' => $isDocuments || (($documentLink['auto'] ?? false) && $verdict !== null && $rowSource === 'documents'),
                    'document_link' => $documentLink,
                    'checked' => $verdict === 'pass' || $verdict === 'na',
                    'fail_reason_code' => $autoNa ? null : ($row['fail_reason_code'] ?? null),
                    'fail_reason_custom' => $autoNa ? null : ($row['fail_reason_custom'] ?? null),
                    'fail_reason_label' => $autoNa ? null : $this->failReasonLabel($meta['fail_reasons'], $row),
                    'at' => $autoNa ? null : ($row['at'] ?? null),
                    'by' => $autoNa ? null : $by,
                    'by_name' => $isDocuments
                        ? 'Documents'
                        : ($isSystem ? 'System' : ($autoNa ? null : ($by ? ($names[$by] ?? null) : null))),
                    'captures_statement' => ($meta['gate'] ?? null) === 'statements_vs_declared',
                    'statement_deposits_total' => $autoNa ? null : ($row['statement_deposits_total'] ?? null),
                    'statement_months' => $autoNa ? null : ($row['statement_months'] ?? StatementCapacityService::DEFAULT_MONTHS),
                    'statement_monthly' => $autoNa ? null : ($row['statement_monthly'] ?? null),
                    'statement_weekly' => $autoNa ? null : ($row['statement_weekly'] ?? null),
                ];
                if (! $autoNa && ($meta['gate'] ?? null) === 'statements_vs_declared' && (float) ($row['statement_monthly'] ?? 0) > 0) {
                    $item['evidence']['rows'][] = [
                        'label' => 'Statement deposits',
                        'value' => format_money((float) ($row['statement_deposits_total'] ?? 0))
                            .' over '.(int) ($row['statement_months'] ?? 6).' months',
                    ];
                    $item['evidence']['rows'][] = [
                        'label' => 'Proven average',
                        'value' => format_money((float) $row['statement_monthly']).'/mo · ≈ '
                            .format_money((float) ($row['statement_weekly'] ?? 0)).'/week',
                    ];
                }
                $items[] = $item;
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
                $incoming = $this->applyGate2AutoVerdict(
                    $key,
                    (array) ($checks[$key] ?? []),
                    $application,
                    $person,
                    $guarantorLinkId,
                    $memberId,
                );
                $verdict = $this->normalizeVerdict($incoming + ['checked' => $incoming['checked'] ?? null]);

                if ($verdict === null && array_key_exists($key, $checks) && ($incoming['verdict'] ?? '') === '') {
                    // Gate 2 posts months even when deposits are blank — keep a prior system verdict.
                    if ($key === StatementCapacityService::CHECKLIST_KEY) {
                        continue;
                    }
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
                    $failEntry = [
                        'verdict' => 'fail',
                        'checked' => false,
                        'fail_reason_code' => $code,
                        'fail_reason_custom' => $code === 'custom' ? $custom : null,
                        'at' => now()->toIso8601String(),
                        'by' => $actor->id,
                    ];
                    if (($incoming['source'] ?? '') === 'system') {
                        $failEntry['source'] = 'system';
                    }
                    $items[$key] = $this->mergeStatementCapture($key, $failEntry, $incoming, (array) ($items[$key] ?? []), $verdict);
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
                    if (($incoming['source'] ?? '') === 'system') {
                        $entry['source'] = 'system';
                    }
                    $items[$key] = $this->mergeStatementCapture(
                        $key,
                        $entry,
                        $incoming,
                        (array) ($items[$key] ?? []),
                        $verdict,
                    );
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

            $fresh = $application->fresh();

            $subjectCustomer = $this->resolveSubjectCustomer($fresh, $person, $guarantorLinkId, $memberId);
            app(ChecklistDocumentBridge::class)->syncDocumentsAfterChecklistPass(
                $fresh,
                $actor,
                $subjectCustomer,
                $items,
                [
                    'subject_kind' => $person,
                    'subject_customer_id' => $subjectCustomer?->id,
                    'loan_group_member_id' => $memberId,
                ],
            );

            $suggestion = $this->suggestedRejection($fresh);
            if ($suggestion['prompt_reject'] && $suggestion['codes'] !== []) {
                $payload = (array) ($fresh->screening_payload ?? []);
                $payload['recommendation_meta'] = array_merge(
                    (array) ($payload['recommendation_meta'] ?? []),
                    [
                        'preferred_rejection_reason_code' => $suggestion['codes'][0],
                        'preferred_rejection_reason_codes' => $suggestion['codes'],
                        'preferred_rejection_notes' => $suggestion['summary'],
                        'from_checklist' => true,
                    ]
                );
                $fresh->update([
                    'screening_payload' => $payload,
                    'screening_rejection_reason_code' => $suggestion['codes'][0],
                ]);
            }

            return $this->viewModel($fresh->fresh(), $actor, $person, $guarantorLinkId, $memberId);
        });
    }

    /**
     * Map checklist Fail verdicts into borrower-facing rejection letter codes + screening summary.
     * Critical fails (incl. Gate 2 on any subject / group member) prompt reject.
     *
     * @return array{
     *   prompt_reject: bool,
     *   codes: list<string>,
     *   summary: string,
     *   fails: list<array{subject: string, item: string, label: string, fail_code: string, fail_label: string, risk: string, letter_code: string}>
     * }
     */
    public function suggestedRejection(LoanApplication $application): array
    {
        $application->loadMissing([
            'customer',
            'customerGuarantors.guarantor',
            'loanGroup.members.customer',
        ]);

        $bySubject = (array) data_get($application->screening_payload, 'screening_checklist.by_subject', []);
        if ($bySubject === [] && is_array(data_get($application->screening_payload, 'screening_checklist.items'))) {
            $bySubject = ['borrower' => ['items' => data_get($application->screening_payload, 'screening_checklist.items')]];
        }

        $letterMap = $this->checklistFailToLetterCodeMap();
        $fails = [];
        $prompt = false;

        foreach ($bySubject as $subjectKey => $bucket) {
            $items = (array) ($bucket['items'] ?? []);
            $subjectLabel = $this->subjectDisplayLabel($application, (string) $subjectKey);
            $kind = $this->kindFromSubject((string) $subjectKey);

            foreach ($this->catalog($kind) as $groupKey => $group) {
                foreach ((array) ($group['items'] ?? []) as $itemKey => $meta) {
                    $meta = $this->normalizeItemMeta($meta);
                    $full = $groupKey.'.'.$itemKey;
                    $entry = (array) ($items[$full] ?? []);
                    if (($entry['verdict'] ?? null) !== 'fail') {
                        continue;
                    }
                    $failCode = (string) ($entry['fail_reason_code'] ?? 'custom');
                    $failLabel = $this->failReasonLabel($meta['fail_reasons'], $entry) ?: $failCode;
                    $letter = $letterMap[$failCode]
                        ?? ($letterMap[$full] ?? null)
                        ?? 'internal_credit_policy_declined';
                    $risk = (string) ($meta['risk'] ?? 'normal');
                    $isGate = ($meta['gate'] ?? null) === 'statements_vs_declared'
                        || $full === 'activity_income.income_evidence'
                        || $full === 'activity_income.bank_or_mobile_money';
                    if ($risk === 'critical' || $isGate) {
                        $prompt = true;
                    }
                    $fails[] = [
                        'subject' => $subjectLabel,
                        'item' => $full,
                        'label' => (string) ($meta['label'] ?? $full),
                        'fail_code' => $failCode,
                        'fail_label' => $failLabel,
                        'risk' => $risk,
                        'letter_code' => $letter,
                    ];
                }
            }
        }

        $codes = collect($fails)
            ->sortBy(fn (array $fail) => match (true) {
                str_contains($fail['item'], 'income_evidence') => 0,
                $fail['risk'] === 'critical' => 1,
                default => 2,
            })
            ->pluck('letter_code')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $summaryLines = collect($fails)->map(function (array $fail) {
            $tag = ($fail['risk'] === 'critical') ? 'High risk' : 'Fail';

            return $tag.' · '.$fail['subject'].' · '.$fail['label'].' — '.$fail['fail_label'];
        })->all();

        return [
            'prompt_reject' => $prompt && $fails !== [],
            'codes' => $codes,
            'summary' => implode("\n", $summaryLines),
            'fails' => $fails,
        ];
    }

    /** @return array<string, string> checklist fail_reason_code (or item key) → letter code */
    private function checklistFailToLetterCodeMap(): array
    {
        return [
            'statements_missing' => 'required_documents_missing',
            'revenue_mismatch' => 'insufficient_income',
            'income_insufficient' => 'insufficient_income',
            'irregular_pattern' => 'unstable_income_pattern',
            'gambling_betting' => 'unstable_income_pattern',
            'round_tripping' => 'unstable_income_pattern',
            'third_party_dumping' => 'unstable_income_pattern',
            'salary_inconsistent' => 'unstable_income_pattern',
            'high_cash_out' => 'unstable_income_pattern',
            'overdraft_bounce' => 'unstable_income_pattern',
            'debt_stacking' => 'excessive_existing_debt',
            'dormant_spike' => 'unstable_income_pattern',
            'low_turnover' => 'insufficient_income',
            'implausible' => 'business_not_verified',
            'unverified' => 'employment_not_verified',
            'docs_missing' => 'required_documents_missing',
            'docs_rejected' => 'documents_not_verified',
            'falsified' => 'falsified_documentation',
            'inconsistent' => 'inconsistent_information',
            'insufficient_capacity' => 'guarantor_not_acceptable',
            'profile_incomplete' => 'guarantor_profile_incomplete',
            'identity_mismatch' => 'national_id_mismatch',
            'face_mismatch' => 'face_verification_failed',
            'crb_reject' => 'poor_crb_history',
            'high_debt' => 'excessive_existing_debt',
            'delinquent' => 'active_loan_delinquency',
            'low_score' => 'low_credit_score',
            'custom' => 'internal_credit_policy_declined',
        ];
    }

    private function subjectDisplayLabel(LoanApplication $application, string $subjectKey): string
    {
        if ($subjectKey === 'borrower') {
            $name = $application->customer?->full_name;

            return $name ? 'Borrower / leader ('.$name.')' : 'Borrower / leader';
        }
        if (str_starts_with($subjectKey, 'guarantor:')) {
            $linkId = (int) substr($subjectKey, strlen('guarantor:'));
            $link = $application->customerGuarantors->firstWhere('id', $linkId);
            $name = $link?->displayName();

            return ($name && $name !== '') ? 'Guarantor ('.$name.')' : 'Guarantor';
        }
        if (str_starts_with($subjectKey, 'member:')) {
            $memberId = (int) substr($subjectKey, strlen('member:'));
            $member = $application->loanGroup?->members?->firstWhere('id', $memberId);
            $name = $member?->customer?->full_name ?? null;

            return $name ? 'Group member ('.$name.')' : 'Group member';
        }

        return $subjectKey;
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
            'gate' => isset($meta['gate']) ? (string) $meta['gate'] : null,
            'document_bundle' => isset($meta['document_bundle']) ? (string) $meta['document_bundle'] : null,
        ];
    }

    /**
     * Apply system / Documents suggestion when the item has no human verdict yet
     * (or was previously system/documents-set).
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
        // Save-time Gate 2 totals are the source of truth — keep the recorded system verdict.
        if ((float) ($row['statement_monthly'] ?? 0) > 0 && $this->normalizeVerdict($row) !== null) {
            return $row;
        }
        $source = (string) ($suggestion['source'] ?? '');
        $existing = $this->normalizeVerdict($row);
        $existingSource = (string) ($row['source'] ?? '');
        $autoSources = ['system', 'auto_na', 'documents'];

        // system_skip = human must decide. If a prior system/documents auto-verdict is stale
        // (e.g. photos arrived after a photos_missing Fail), clear it so the item reopens.
        if ($source === 'system_skip' || ($suggestion['verdict'] ?? '') === '') {
            if ($existing !== null && in_array($existingSource, $autoSources, true)) {
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

        $humanLocked = $existing !== null && ! in_array($existingSource, $autoSources, true);
        if ($humanLocked) {
            return $row;
        }

        $resolvedSource = match ($source) {
            'auto_na' => 'auto_na',
            'documents' => 'documents',
            default => 'system',
        };

        return [
            'verdict' => $suggestion['verdict'],
            'checked' => in_array($suggestion['verdict'], ['pass', 'na'], true),
            'source' => $resolvedSource,
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
        $autoSources = ['system', 'auto_na', 'documents'];

        foreach ($validKeys as $key) {
            $current = (array) ($items[$key] ?? []);
            // Save-time Gate 2 totals are the source of truth — do not let pre-save chips overwrite.
            if ($key === StatementCapacityService::CHECKLIST_KEY
                && (float) ($current['statement_monthly'] ?? 0) > 0
                && $this->normalizeVerdict($current) !== null) {
                continue;
            }

            $suggestion = $suggestions[$key] ?? null;
            if ($suggestion === null) {
                continue;
            }

            $existing = $this->normalizeVerdict($current);
            $existingSource = (string) ($current['source'] ?? '');
            $suggestionSource = (string) ($suggestion['source'] ?? '');

            // Clear stale system/documents verdicts when the platform can no longer decide.
            if ($suggestionSource === 'system_skip' || ($suggestion['verdict'] ?? '') === '') {
                if ($existing !== null && in_array($existingSource, $autoSources, true)) {
                    unset($items[$key]);
                }

                continue;
            }

            if ($existing !== null && ! in_array($existingSource, $autoSources, true)) {
                continue;
            }
            $resolvedSource = match ($suggestionSource) {
                'auto_na' => 'auto_na',
                'documents' => 'documents',
                default => 'system',
            };
            $items[$key] = [
                'verdict' => $suggestion['verdict'],
                'checked' => in_array($suggestion['verdict'], ['pass', 'na'], true),
                'source' => $resolvedSource,
                'fail_reason_code' => $suggestion['fail_reason_code'] ?? null,
                'fail_reason_custom' => null,
                'at' => now()->toIso8601String(),
                'by' => null,
            ];
        }
    }

    /**
     * Persist Documents-driven checklist verdicts after a file is reviewed in Documents.
     *
     * @param  list<string>  $touchedKeys
     */
    public function refreshDocumentLinkedVerdicts(
        LoanApplication $application,
        User $actor,
        string $person = 'borrower',
        ?int $guarantorLinkId = null,
        ?int $memberId = null,
        array $touchedKeys = [],
    ): void {
        $subject = $this->subjectKey($person, $guarantorLinkId, $memberId);
        $validKeys = [];
        foreach ($this->catalog($subject) as $groupKey => $group) {
            foreach (array_keys((array) ($group['items'] ?? [])) as $itemKey) {
                $validKeys[] = $groupKey.'.'.$itemKey;
            }
        }

        if ($touchedKeys !== []) {
            $validKeys = array_values(array_intersect($validKeys, $touchedKeys));
        }
        if ($validKeys === []) {
            return;
        }

        DB::transaction(function () use ($application, $actor, $person, $guarantorLinkId, $memberId, $subject, $validKeys) {
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

            $items = (array) (($bySubject[$subject]['items'] ?? []) ?: []);
            $this->syncSystemVerdicts($application, $items, $validKeys, $person, $guarantorLinkId, $memberId);

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
        });
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
     * When screening keys deposits + months, the system sets Gate 2 pass/fail.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function applyGate2AutoVerdict(
        string $key,
        array $incoming,
        LoanApplication $application,
        string $person,
        ?int $guarantorLinkId,
        ?int $memberId,
    ): array {
        if ($key !== StatementCapacityService::CHECKLIST_KEY) {
            return $incoming;
        }

        $capacity = app(StatementCapacityService::class);
        $capture = $capacity->fromIncoming($incoming);
        if ($capture === null) {
            return $incoming;
        }

        $customer = $this->resolveSubjectCustomer($application, $person, $guarantorLinkId, $memberId);
        $auto = $capacity->verdictAgainstDeclared((float) $capture['statement_monthly'], $customer);

        return array_merge($incoming, $auto);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function mergeStatementCapture(
        string $key,
        array $entry,
        array $incoming,
        array $existing,
        string $verdict,
    ): array {
        if ($key !== StatementCapacityService::CHECKLIST_KEY) {
            return $entry;
        }

        $capture = app(StatementCapacityService::class)->fromIncoming($incoming);

        if ($capture === null) {
            foreach (['statement_deposits_total', 'statement_months', 'statement_monthly', 'statement_weekly'] as $field) {
                if (isset($existing[$field])) {
                    $entry[$field] = $existing[$field];
                }
            }
        } else {
            $entry = array_merge($entry, $capture);
        }

        if ($verdict === 'pass' && (float) ($entry['statement_monthly'] ?? 0) <= 0) {
            throw new \InvalidArgumentException(
                'Key the total deposits from the statement (and the months covered) before passing the revenue match. The system uses that average for capacity and any counter-offer.'
            );
        }

        return $entry;
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

    private function resolveSubjectCustomer(
        LoanApplication $application,
        string $person,
        ?int $guarantorLinkId,
        ?int $memberId,
    ): ?Customer {
        $context = $this->evidenceContext($application, $person, $guarantorLinkId, $memberId, null, null);
        $customer = $context['customer'] ?? null;

        return $customer instanceof Customer ? $customer : null;
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

        // Guarantor / member chips must never inherit the leader/borrower file — that made every
        // member show the same Pass/Fail progress (e.g. identical 6/20).
        if ($person === 'guarantor' && $guarantorLinkId) {
            $facePhotos = [];
            $nidaPhoto = null;
            $afford = [];
            $docs = [
                'required' => 0,
                'satisfied' => 0,
                'uploaded' => 0,
                'progress' => 0,
                'missing' => [],
                'files' => [],
                'id_files' => [],
            ];
            $crb = [];

            $row = collect($review['guarantors'] ?? [])->first(
                fn ($g) => (int) ($g['link_id'] ?? 0) === $guarantorLinkId
            );
            if ($row) {
                $file = is_array($row['file'] ?? null) ? $row['file'] : [];
                $customer = $row['customer'] ?? ($file['customer'] ?? $customer);
                $crb = $row['crb'] ?? ($file['crb'] ?? []);
                $facePhotos = $file['face_photos'] ?? [];
                $nidaPhoto = $file['nida_photo_path'] ?? null;
                $afford = $file['affordability'] ?? ($row['affordability'] ?? []);
                $docs['id_files'] = $this->idFilesFromSubjectFile($file);
            }
        }

        if ($person === 'member' && $memberId) {
            $facePhotos = [];
            $nidaPhoto = null;
            $afford = [];
            $docs = [
                'required' => 0,
                'satisfied' => 0,
                'uploaded' => 0,
                'progress' => 0,
                'missing' => [],
                'files' => [],
                'id_files' => [],
            ];
            $crb = [];

            if (! is_array($groupReview) || empty($groupReview['members'])) {
                $groupReview = app(GroupLoanReviewService::class)->dossier($application);
            }

            $member = collect($groupReview['members'] ?? [])->first(
                fn ($m) => (int) ($m['id'] ?? 0) === $memberId
            );
            if ($member) {
                $customer = Customer::query()->find($member['customer_id'] ?? 0) ?? $customer;
                $file = (array) ($member['file'] ?? []);
                if ($file === [] && $customer) {
                    $file = app(LoanApplicationReviewService::class)->subjectFileForCustomer($customer);
                }
                $facePhotos = $file['face_photos'] ?? [];
                $nidaPhoto = $file['nida_photo_path'] ?? null;
                $afford = $file['affordability'] ?? [];
                $docs['id_files'] = $this->idFilesFromSubjectFile($file);
                $crb = [
                    'score' => $member['crb_score'] ?? ($file['crb']['score'] ?? null),
                    'recommendation' => $member['crb_recommendation'] ?? $member['crb_status'] ?? ($file['crb']['recommendation'] ?? null),
                    'existing_loans' => $member['crb_existing_loans'] ?? $member['existing_loans'] ?? ($file['crb']['existing_loans'] ?? null),
                    'outstanding_balance' => $member['crb_outstanding'] ?? ($file['crb']['outstanding_balance'] ?? null),
                    'delinquencies' => $member['crb_delinquencies'] ?? ($file['crb']['delinquencies'] ?? null),
                    'loan_history' => $member['loan_history'] ?? ($file['crb']['loan_history'] ?? []),
                    'personal' => $file['crb']['personal'] ?? [],
                ];
            }
        }

        $gSug = $review['guarantor_suggestion'] ?? [];
        $collateral = data_get($application->screening_payload, 'collateral_secure', []);
        if (in_array($person, ['guarantor', 'member'], true)) {
            $anomalies = [];
            $gSug = [];
        } else {
            $anomalies = $review['anomalies'] ?? null;
            if (! is_array($anomalies)) {
                $anomalies = app(UnderwritingAnomalyService::class)->forApplication($application, $review);
            }
        }

        [$declaredIncome, $declaredIncomeLabel, $incomeStatements] = $this->incomeStatementEvidence($customer);

        return [
            'customer' => $customer,
            'crb' => $crb,
            'face_photos' => $facePhotos,
            'nida_photo_path' => $nidaPhoto,
            'affordability' => $afford,
            'documents' => $docs,
            'declared_monthly_income' => $declaredIncome,
            'declared_income_label' => $declaredIncomeLabel,
            'income_statements' => $incomeStatements,
            'guarantor_suggestion' => $gSug,
            'collateral_secure' => $collateral,
            'anomalies' => $anomalies,
            'application' => $application,
            'subject_person' => $person,
            'subject_member_id' => $memberId,
            'subject_guarantor_link_id' => $guarantorLinkId,
        ];
    }

    /**
     * Declared profile revenue + bank / mobile-money statement files for Gate 2.
     *
     * @return array{0: float, 1: ?string, 2: list<array{label: string, url: string, status: ?string}>}
     */
    private function incomeStatementEvidence(?Customer $customer): array
    {
        if (! $customer) {
            return [0.0, null, []];
        }

        $declared = (float) ($customer->monthly_income ?? 0);
        if ($declared <= 0 && filled($customer->income_range)) {
            $declared = (float) (config('income_ranges.'.$customer->income_range.'.midpoint') ?? 0);
        }
        $label = income_range_label($customer->income_range)
            ?? ($declared > 0 ? format_money($declared).'/mo' : null);

        $statements = [];
        $codes = ['bank_statement', 'mobile_money_statement', 'mpesa_statement', 'salary_slip'];
        $uploads = app(ProfileDocumentService::class)->latestByCodes($customer, $codes);
        foreach ($codes as $code) {
            $doc = $uploads->get($code);
            if (! $doc || ! filled($doc->file_path ?? null)) {
                continue;
            }
            $statements[] = [
                'label' => match ($code) {
                    'bank_statement' => 'Bank statement',
                    'mobile_money_statement', 'mpesa_statement' => 'Mobile money statement',
                    'salary_slip' => 'Salary slip',
                    default => (string) ($doc->documentType?->name ?? 'Income document'),
                },
                'url' => asset('storage/'.$doc->file_path),
                'status' => $doc->status ?? null,
                'file_path' => $doc->file_path,
            ];
        }

        return [$declared, $label, $statements];
    }

    /**
     * @param  array<string, mixed>  $file
     * @return list<array{label: string, url: string}>
     */
    private function idFilesFromSubjectFile(array $file): array
    {
        $idFiles = [];
        foreach (collect($file['id_documents'] ?? []) as $code => $doc) {
            $path = is_object($doc) ? ($doc->file_path ?? null) : (is_array($doc) ? ($doc['file_path'] ?? null) : null);
            if (! is_string($path) || ! filled($path)) {
                continue;
            }
            $label = is_object($doc)
                ? ($doc->documentType->name ?? (is_string($code) ? ucfirst(str_replace('_', ' ', $code)) : 'ID'))
                : ($doc['label'] ?? 'ID');
            $idFiles[] = [
                'label' => (string) $label,
                'url' => asset('storage/'.$path),
            ];
        }

        return $idFiles;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{
     *   type: string,
     *   rows: list<array{label: string, value: string}>,
     *   photos: list<array{label: string, url: string}>,
     *   documents: list<array{label: string, url: string, kind?: string, status?: ?string}>,
     *   hint: ?string,
     *   layout: ?string,
     *   documents_heading: ?string,
     *   documents_open_label: ?string,
     *   compare: list<array{label: string, profile: string, crb: string, status: string}>
     * }
     */
    private function buildEvidence(string $type, array $ctx, ?string $itemKey = null): array
    {
        /** @var Customer|null $customer */
        $customer = $ctx['customer'] ?? null;
        $crb = (array) ($ctx['crb'] ?? []);
        $photos = [];
        $documents = [];
        $rows = [];
        $compare = [];
        $hint = null;
        $layout = null;
        $documentsHeading = null;
        $documentsOpenLabel = null;

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
                if ($facePhotos instanceof Collection) {
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
                [$rows, $documents, $photos, $hint] = $this->activityEvidenceBundle($customer);
                $documentsHeading = 'Activity documents';
                $documentsOpenLabel = 'Open document';
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

            case 'income_statements':
                $declared = (float) ($ctx['declared_monthly_income'] ?? 0);
                $declaredLabel = (string) ($ctx['declared_income_label'] ?? '');
                $statements = collect($ctx['income_statements'] ?? []);
                $layout = 'documents';
                $rows = [
                    ['label' => 'Profile monthly revenue', 'value' => $declaredLabel !== ''
                        ? $declaredLabel.($declared > 0 ? ' · ~'.format_money($declared) : '')
                        : ($declared > 0 ? format_money($declared).'/mo' : '— not declared')],
                    ['label' => 'Statements on file', 'value' => (string) $statements->count()],
                ];
                $documents = [];
                foreach ($statements->take(8) as $file) {
                    if (empty($file['url'])) {
                        continue;
                    }
                    $path = strtolower((string) ($file['file_path'] ?? $file['url'] ?? ''));
                    $kind = str_ends_with($path, '.pdf') ? 'pdf' : 'image';
                    $documents[] = [
                        'label' => trim((string) ($file['label'] ?? 'Statement')),
                        'url' => (string) $file['url'],
                        'kind' => $kind,
                        'status' => $file['status'] ?? null,
                    ];
                }
                $hint = $documents === []
                    ? 'No bank or mobile-money statement on this profile yet — request one, or Fail as missing (that rejects the file).'
                    : ($itemKey === 'bank_or_mobile_money'
                        ? 'Open the statement(s) below. On Fail, pick a concerning pattern — that rejects the application and pre-fills the rejection letter.'
                        : 'Open the statement(s). Key the total deposits for the period (usually 6 months). The system averages monthly and weekly for capacity and any counter-offer.');
                $documentsHeading = 'Statements on file';
                $documentsOpenLabel = 'Open full statement';
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
            'documents' => $documents,
            'hint' => $hint,
            'layout' => $layout,
            'documents_heading' => $documentsHeading,
            'documents_open_label' => $documentsOpenLabel,
        ];
    }

    /**
     * Profile activity type + details + activity proof documents for screening.
     *
     * @return array{0: list<array{label: string, value: string}>, 1: list<array{label: string, url: string, kind?: string, status?: ?string}>, 2: list<array{label: string, url: string}>, 3: string}
     */
    private function activityEvidenceBundle(?Customer $customer): array
    {
        if (! $customer) {
            return [[
                ['label' => 'Activity', 'value' => '— no customer on file'],
            ], [], [], 'No profile linked — cannot review activity.'];
        }

        $type = (string) ($customer->activity_type ?? $customer->employment_type ?? '');
        $details = (array) ($customer->activity_details ?? []);
        $fieldDefs = collect(activity_fields_localized()[$type] ?? [])->keyBy('key');

        $rows = [
            [
                'label' => 'Activity type',
                'value' => display_label($type, 'activity_type')
                    ?: (activity_type_label($type) ?? ($type !== '' ? $type : '—')),
            ],
            [
                'label' => 'Income range',
                'value' => income_range_label($customer->income_range)
                    ?? ($customer->monthly_income ? format_money((float) $customer->monthly_income).'/mo' : '—'),
            ],
        ];

        if ($customer->monthly_income) {
            $rows[] = [
                'label' => 'Monthly income (midpoint)',
                'value' => format_money((float) $customer->monthly_income),
            ];
        }

        foreach ($fieldDefs as $key => $field) {
            if (($field['type'] ?? '') === 'document') {
                continue;
            }
            $raw = $details[$key] ?? null;
            if (! filled($raw) && in_array($key, ['business_name', 'employer_name'], true)) {
                $raw = $customer->business_name ?? null;
            }
            if (! filled($raw)) {
                continue;
            }
            $value = (string) $raw;
            $options = (array) ($field['options'] ?? []);
            if ($options !== [] && isset($options[$value])) {
                $value = (string) $options[$value];
            }
            $rows[] = [
                'label' => (string) ($field['label'] ?? $key),
                'value' => $value,
            ];
        }

        // Any other filled detail keys not covered by the activity profile schema.
        foreach ($details as $key => $raw) {
            if ($fieldDefs->has($key) || ! filled($raw) || is_array($raw)) {
                continue;
            }
            $rows[] = [
                'label' => ucwords(str_replace('_', ' ', (string) $key)),
                'value' => (string) $raw,
            ];
        }

        if (count($rows) <= 2) {
            $rows[] = [
                'label' => 'Activity details',
                'value' => '— no activity details on profile yet',
            ];
        }

        $docCodes = ['employment_contract', 'business_license', 'business_registration', 'business_photos', 'tin_certificate', 'salary_slip'];
        foreach ($fieldDefs as $field) {
            if (($field['type'] ?? '') === 'document' && filled($field['document_code'] ?? null)) {
                $docCodes[] = (string) $field['document_code'];
            }
        }
        $docCodes = array_values(array_unique($docCodes));

        $uploads = app(ProfileDocumentService::class)->latestByCodes($customer, $docCodes);
        $documents = [];
        $photos = [];
        foreach ($docCodes as $code) {
            $doc = $uploads->get($code);
            if (! $doc || ! filled($doc->file_path ?? null)) {
                continue;
            }
            $url = asset('storage/'.$doc->file_path);
            $label = match ($code) {
                'employment_contract' => 'Employment contract',
                'business_license' => 'Business licence',
                'business_registration' => 'Business registration',
                'business_photos' => 'Business photos',
                'tin_certificate' => 'TIN certificate',
                'salary_slip' => 'Salary slip',
                default => (string) ($doc->documentType?->name ?? ucwords(str_replace('_', ' ', $code))),
            };
            $path = strtolower((string) $doc->file_path);
            $kind = str_ends_with($path, '.pdf') ? 'pdf' : 'image';
            $documents[] = [
                'label' => $label,
                'url' => $url,
                'kind' => $kind,
                'status' => $doc->status ?? null,
            ];
            if ($kind === 'image') {
                $photos[] = [
                    'label' => $label,
                    'url' => $url,
                ];
            }
        }

        $hint = $documents === []
            ? 'Review the profile activity fields below. Request activity proof documents if needed, then Pass or Fail.'
            : 'Review the profile activity fields and open the activity documents below. Pass if the activity looks real for this loan.';

        return [$rows, $documents, $photos, $hint];
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
