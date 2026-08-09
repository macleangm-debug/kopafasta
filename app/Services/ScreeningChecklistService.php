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

        foreach (collect($groupReview['members'] ?? []) as $member) {
            $mId = (int) ($member['id'] ?? 0);
            if ($mId < 1) {
                continue;
            }
            $vm = $this->viewModel($application, $actor, 'member', null, $mId);
            $subjects[] = [
                'key' => 'member:'.$mId,
                'person' => 'member',
                'g' => null,
                'm' => $mId,
                'label' => ucfirst((string) ($member['role'] ?? 'Member')),
                'sublabel' => $member['name'] ?? null,
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

        $groups = [];
        $decided = 0;
        $passed = 0;
        $failed = 0;
        $total = 0;

        foreach ($this->catalog($subject) as $groupKey => $group) {
            $items = [];
            foreach ((array) ($group['items'] ?? []) as $itemKey => $meta) {
                $meta = $this->normalizeItemMeta($meta);
                $fullKey = $groupKey.'.'.$itemKey;
                $row = (array) ($checkedMap[$fullKey] ?? []);
                $verdict = $this->normalizeVerdict($row);
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
                    'evidence_type' => $meta['evidence'],
                    'evidence' => $this->buildEvidence($meta['evidence'], $context),
                    'fail_reasons' => $meta['fail_reasons'],
                    'verdict' => $verdict,
                    'checked' => $verdict === 'pass' || $verdict === 'na',
                    'fail_reason_code' => $row['fail_reason_code'] ?? null,
                    'fail_reason_custom' => $row['fail_reason_custom'] ?? null,
                    'fail_reason_label' => $this->failReasonLabel($meta['fail_reasons'], $row),
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
                    $items[$key] = [
                        'verdict' => $verdict,
                        'checked' => $verdict === 'pass' || $verdict === 'na',
                        'fail_reason_code' => null,
                        'fail_reason_custom' => null,
                        'at' => now()->toIso8601String(),
                        'by' => $actor->id,
                    ];
                }
            }

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
            ];
        }

        return [
            'label' => (string) ($meta['label'] ?? 'Check'),
            'evidence' => (string) ($meta['evidence'] ?? 'generic'),
            'fail_reasons' => (array) ($meta['fail_reasons'] ?? ['custom' => 'Other (write reason)']),
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
        ];

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

        return [
            'customer' => $customer,
            'crb' => $crb,
            'face_photos' => $facePhotos,
            'nida_photo_path' => $nidaPhoto,
            'affordability' => $afford,
            'documents' => $docs,
            'guarantor_suggestion' => $gSug,
            'collateral_secure' => $collateral,
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
                    $photos[] = [
                        'label' => is_string($angle) ? ucfirst(str_replace('_', ' ', $angle)) : 'Face',
                        'url' => asset('storage/'.$path),
                    ];
                }
                $nidaPath = $ctx['nida_photo_path'] ?? null;
                if (is_object($nidaPath) && isset($nidaPath->file_path)) {
                    $nidaPath = $nidaPath->file_path;
                } elseif (is_array($nidaPath)) {
                    $nidaPath = $nidaPath['file_path'] ?? $nidaPath['path'] ?? null;
                }
                if (is_string($nidaPath) && filled($nidaPath)) {
                    $photos[] = [
                        'label' => 'NIDA photo',
                        'url' => asset('storage/'.$nidaPath),
                    ];
                }
                $rows = [
                    ['label' => 'Face status', 'value' => display_label($customer?->face_verification_status, 'face_verification_status') ?: '—'],
                ];
                $hint = 'Open the photos and compare likeness.';
                break;

            case 'phone':
                $rows = [
                    ['label' => 'Mobile number', 'value' => (string) ($customer?->phone ?: '—')],
                ];
                $hint = 'Confirm ownership with the customer if needed.';
                break;

            case 'crb_loans':
                $history = collect($crb['loan_history'] ?? [])->take(5);
                $rows = [
                    ['label' => 'Loans at other institutions', 'value' => (string) ($crb['existing_loans'] ?? $history->count() ?: '0')],
                    ['label' => 'Outstanding', 'value' => isset($crb['outstanding_balance']) ? format_money((float) $crb['outstanding_balance']) : '—'],
                    ['label' => 'Delinquencies', 'value' => (string) ($crb['delinquencies'] ?? '—')],
                    ['label' => 'CRB recommendation', 'value' => strtoupper((string) ($crb['recommendation'] ?? '—'))],
                ];
                foreach ($history as $loan) {
                    $rows[] = [
                        'label' => (string) ($loan['institution'] ?? $loan['lender'] ?? 'Loan'),
                        'value' => trim(($loan['status'] ?? '').' · '.($loan['balance'] ?? $loan['amount'] ?? '')),
                    ];
                }
                $hint = 'Check exposure at other microfinances / lenders.';
                break;

            case 'residence':
                $rows = [
                    ['label' => 'Region', 'value' => (string) ($customer?->region ?: '—')],
                    ['label' => 'District', 'value' => (string) ($customer?->district ?: '—')],
                    ['label' => 'Ward', 'value' => (string) ($customer?->ward ?: '—')],
                    ['label' => 'Street / address', 'value' => (string) ($customer?->street_address ?? $customer?->address ?? '—')],
                ];
                break;

            case 'activity':
                $rows = [
                    ['label' => 'Occupation / activity', 'value' => (string) ($customer?->occupation ?? $customer?->business_type ?? '—')],
                    ['label' => 'Employer / business', 'value' => (string) ($customer?->employer_name ?? $customer?->business_name ?? '—')],
                ];
                break;

            case 'affordability':
                $aff = (array) ($ctx['affordability'] ?? []);
                $rows = [
                    ['label' => 'Verdict', 'value' => strtoupper((string) ($aff['verdict'] ?? ($aff['pass'] ?? false ? 'pass' : '—')))],
                    ['label' => 'Capacity', 'value' => isset($aff['available_capacity']) ? format_money((float) $aff['available_capacity']) : '—'],
                    ['label' => 'Proposed EMI', 'value' => isset($aff['proposed_installment']) ? format_money((float) $aff['proposed_installment']) : '—'],
                ];
                break;

            case 'documents':
                $docs = (array) ($ctx['documents'] ?? []);
                $rows = [
                    ['label' => 'Required verified', 'value' => ($docs['satisfied'] ?? 0).' / '.($docs['required'] ?? 0)],
                    ['label' => 'Uploaded', 'value' => (string) ($docs['uploaded'] ?? 0)],
                    ['label' => 'Progress', 'value' => ($docs['progress'] ?? 0).'%'],
                ];
                $hint = 'Open the Documents tab for full preview if needed.';
                break;

            case 'doc_requests':
                $rows = [
                    ['label' => 'Tip', 'value' => 'Check open document requests on the Documents tab'],
                ];
                break;

            case 'guarantor_contact':
            case 'guarantor_capacity':
                $g = (array) ($ctx['guarantor_suggestion'] ?? []);
                $rows = [
                    ['label' => 'Guarantor', 'value' => (string) ($g['name'] ?? '—')],
                    ['label' => 'Signal', 'value' => strtoupper((string) ($g['recommendation'] ?? '—'))],
                    ['label' => 'Summary', 'value' => (string) ($g['summary'] ?? '—')],
                ];
                break;

            case 'nok_contact':
                $rows = [
                    ['label' => 'Next of kin', 'value' => (string) ($customer?->next_of_kin_name ?? $customer?->kin_name ?? '—')],
                    ['label' => 'Phone', 'value' => (string) ($customer?->next_of_kin_phone ?? $customer?->kin_phone ?? '—')],
                ];
                break;

            case 'insurance':
                $cs = (array) ($ctx['collateral_secure'] ?? []);
                $rows = [
                    ['label' => 'Status', 'value' => (string) ($cs['status'] ?? '—')],
                    ['label' => 'Source', 'value' => (string) ($cs['source'] ?? '—')],
                    ['label' => 'Insurance type', 'value' => (string) (data_get($cs, 'insurance.insurance_type') ?: '—')],
                    ['label' => 'Expiry', 'value' => (string) (data_get($cs, 'insurance.expiry') ?: '—')],
                ];
                break;

            default:
                $rows = [
                    ['label' => 'Tip', 'value' => 'Use the credit file tabs for detail, then record Pass or Fail here.'],
                ];
        }

        return [
            'type' => $type,
            'rows' => $rows,
            'compare' => $compare,
            'photos' => $photos,
            'hint' => $hint,
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
