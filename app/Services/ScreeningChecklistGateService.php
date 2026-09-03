<?php

namespace App\Services;

use App\Models\LoanApplication;

/**
 * Presentation map for Screening — does not change credit rules.
 */
class ScreeningChecklistGateService
{
    public const GATES = [
        'income' => 'Gate 2 — Verified income & statements',
        'crb' => 'Gate 3 — CRB / Credit history',
        'collateral' => 'Gate 4 — Collateral & security',
        'identity' => 'Gate 5 — Identity, people & contacts',
        'final' => 'Gate 6 — Final review',
    ];

    public const SHORT = [
        'income' => '2 Income',
        'crb' => '3 CRB',
        'collateral' => '4 Collateral',
        'identity' => '5 Identity',
        'final' => '6 Final review',
    ];

    public const LOCK_REASONS = [
        'income' => 'Locked — complete initial affordability first',
        'crb' => 'Locked — complete verified income first',
        'collateral' => 'Locked — complete CRB first',
        'identity' => 'Locked — complete collateral first',
        'final' => 'Locked — complete identity, people & contacts first',
    ];

    /**
     * @param  array<string, mixed>  $item
     */
    public function isQuietAuto(array $item): bool
    {
        if (! empty($item['keep_visible'])) {
            return false;
        }
        $key = (string) ($item['key'] ?? '');
        if ($key === 'credit_file.risk_flags_addressed') {
            return false;
        }
        if (! empty($item['auto_na'])) {
            return true;
        }
        if (! empty($item['captures_statement'])) {
            return false;
        }
        $verdict = $item['verdict'] ?? null;
        $auto = ! empty($item['system_checked'])
            || ! empty($item['catalog_system'])
            || ! empty($item['documents_checked']);

        return $auto && in_array($verdict, ['pass', 'na'], true);
    }

    /**
     * System already has a result (pass, fail, N/A, or awaiting data).
     * These must not appear as Pass / Concern questions.
     *
     * @param  array<string, mixed>  $item
     */
    public function isSystemDetermined(array $item): bool
    {
        if (! empty($item['auto_na'])) {
            return true;
        }
        if (! empty($item['captures_statement'])) {
            return false;
        }
        $auto = ! empty($item['system_checked'])
            || ! empty($item['catalog_system'])
            || ! empty($item['documents_checked'])
            || $this->isSystemFail($item);
        if (! $auto) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isHumanWork(array $item): bool
    {
        return ! $this->isSystemDetermined($item);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isSystemFail(array $item): bool
    {
        if (($item['verdict'] ?? null) !== 'fail') {
            return false;
        }

        return ! empty($item['system_checked'])
            || ! empty($item['catalog_system'])
            || ! empty($item['documents_checked'])
            || in_array((string) ($item['by_name'] ?? ''), ['System', 'Documents'], true);
    }

    public function gateFor(string $groupKey, string $fullKey): string
    {
        return match ($fullKey) {
            'identity.name_vs_crb',
            'identity.marital_vs_crb',
            'credit_file.crb_reviewed',
            'guarantor_wrap.crb_reviewed',
            'member_wrap.crb_reviewed' => 'crb',
            'contacts.guarantor_capacity',
            'guarantor_wrap.capacity_confirmed' => 'income',
            'documents.required_docs_complete',
            'documents.doc_authenticity',
            'member_wrap.docs_ok' => 'identity',
            'credit_file.risk_flags_addressed',
            'credit_file.recommendation_ready',
            'guarantor_wrap.file_ready',
            'member_wrap.file_ready' => 'final',
            default => match ($groupKey) {
                'identity', 'residence', 'contacts' => 'identity',
                'activity_income' => 'income',
                'collateral' => str_starts_with($fullKey, 'collateral.valuation') || $fullKey === 'collateral.ltv_covers'
                    ? 'final'
                    : 'collateral',
                'credit_file', 'guarantor_wrap', 'member_wrap' => str_contains($fullKey, 'crb')
                    ? 'crb'
                    : (str_contains($fullKey, 'capacity') ? 'income' : 'final'),
                default => 'final',
            },
        };
    }

    /**
     * @param  array<string, mixed>  $subject
     * @return array{href: string, cta: string, gate: string}
     */
    public function destination(
        LoanApplication $application,
        string $fullKey,
        array $subject = [],
        ?int $documentRequestId = null,
        array $item = [],
    ): array {
        $person = (string) ($subject['person'] ?? 'borrower');
        $code = (string) ($item['fail_reason_code'] ?? $subject['fail_reason_code'] ?? '');
        $customerId = (int) ($subject['customer_id'] ?? 0);
        $base = [
            'loan_application' => $application,
            'workspace' => 'checklist',
            'review_person' => $person,
            'review_g' => $subject['g'] ?? null,
            'review_m' => $subject['m'] ?? null,
        ];

        $map = match (true) {
            str_starts_with($fullKey, 'activity_income.income_evidence') => [
                'cta' => 'Review statements',
                'gate' => 'income',
                'query' => [
                    'desk_phase' => 'capacity',
                    'gate' => 'income',
                    'capacity_tab' => 'checks',
                    'open_group' => 'activity_income',
                    'open_item' => 'activity_income.income_evidence',
                ],
                'hash' => 'item-activity_income.income_evidence',
            ],
            str_starts_with($fullKey, 'activity_income.') => [
                'cta' => 'Open Activity & Income',
                'gate' => 'income',
                'query' => [
                    'desk_phase' => 'capacity',
                    'gate' => 'income',
                    'capacity_tab' => 'checks',
                    'open_group' => 'activity_income',
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            $fullKey === 'identity.face_vs_nida' || $fullKey === 'identity.id_document_quality' => [
                'cta' => match ($code) {
                    'face_photo_missing' => 'Request face photos',
                    'id_photo_missing' => 'Request ID photo',
                    'photos_missing' => 'Request face and ID photos',
                    default => 'Open identity photos',
                },
                'gate' => 'identity',
                'query' => [
                    'desk_phase' => 'person',
                    'gate' => 'identity',
                    'open_group' => 'identity',
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            $fullKey === 'identity.nida_vs_dob' => [
                'cta' => match ($code) {
                    'nida_missing', 'nida_malformed', 'nida_impossible', 'nida_unverifiable' => 'National ID not provided',
                    'nida_incomplete' => 'Open member profile (date of birth)',
                    default => 'Open identity',
                },
                'gate' => 'identity',
                'query' => [
                    'desk_phase' => 'person',
                    'gate' => 'identity',
                    'open_group' => 'identity',
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            $fullKey === 'identity.phone_ownership' => [
                'cta' => 'Review payment/ownership evidence',
                'gate' => 'identity',
                'query' => [
                    'desk_phase' => 'person',
                    'gate' => 'identity',
                    'open_group' => 'identity',
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            str_contains($fullKey, 'crb') || $fullKey === 'identity.name_vs_crb' || $fullKey === 'identity.marital_vs_crb' => [
                'cta' => match ($code) {
                    'crb_never_checked' => 'Run CRB check',
                    'crb_no_record' => 'Review CRB — no member record',
                    'crb_name_unusable' => 'Review CRB name data',
                    'profile_name_missing' => 'Open member profile (name)',
                    default => 'Open CRB comparison',
                },
                'gate' => 'crb',
                'query' => [
                    'desk_phase' => 'capacity',
                    'gate' => 'crb',
                    'capacity_tab' => 'crb',
                    'open_group' => explode('.', $fullKey)[0],
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            str_starts_with($fullKey, 'identity.') || str_starts_with($fullKey, 'residence.') => [
                'cta' => 'Open identity',
                'gate' => 'identity',
                'query' => [
                    'desk_phase' => 'person',
                    'gate' => 'identity',
                    'open_group' => explode('.', $fullKey)[0],
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            str_starts_with($fullKey, 'collateral.valuation') => [
                'cta' => 'Open valuation',
                'gate' => 'collateral',
                'query' => [
                    'desk_phase' => 'security',
                    'gate' => 'collateral',
                    'security_tab' => 'checks',
                    'open_group' => 'collateral',
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            str_starts_with($fullKey, 'collateral.') => [
                'cta' => 'Open collateral',
                'gate' => 'collateral',
                'query' => [
                    'desk_phase' => 'security',
                    'gate' => 'collateral',
                    'security_tab' => 'checks',
                    'open_group' => 'collateral',
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            str_contains($fullKey, 'recommendation') || str_ends_with($fullKey, 'file_ready') => [
                'cta' => 'Open decision',
                'gate' => 'final',
                'query' => ['workspace' => 'decision'],
                'hash' => 'review-recommendation',
            ],
            str_starts_with($fullKey, 'contacts.') => [
                'cta' => 'Record contact result',
                'gate' => $this->gateFor('contacts', $fullKey),
                'query' => [
                    'desk_phase' => 'person',
                    'gate' => $this->gateFor('contacts', $fullKey),
                    'open_group' => 'contacts',
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            $fullKey === 'documents.requested_docs_reviewed' || $fullKey === 'documents.falsified_docs' => [
                'cta' => 'Review submission',
                'gate' => 'final',
                'query' => [
                    'desk_phase' => 'capacity',
                    'gate' => 'final',
                    'capacity_tab' => 'documents',
                    'open_group' => 'documents',
                    'open_item' => $fullKey,
                ],
                'hash' => $documentRequestId ? 'doc-request-'.$documentRequestId : 'review-document-pipeline',
            ],
            str_starts_with($fullKey, 'documents.') => [
                'cta' => 'Open documents',
                'gate' => 'identity',
                'query' => [
                    'desk_phase' => 'person',
                    'gate' => 'identity',
                    'open_group' => 'documents',
                    'open_item' => $fullKey,
                ],
                'hash' => 'item-'.$fullKey,
            ],
            default => [
                'cta' => 'Open check',
                'gate' => $this->gateFor(explode('.', $fullKey)[0] ?? 'documents', $fullKey),
                'query' => [
                    'open_group' => explode('.', $fullKey)[0] ?? null,
                    'open_item' => $fullKey,
                    'gate' => $this->gateFor(explode('.', $fullKey)[0] ?? 'documents', $fullKey),
                ],
                'hash' => 'item-'.$fullKey,
            ],
        };

        $query = array_filter([...$base, ...($map['query'] ?? [])], fn ($v) => $v !== null && $v !== '');
        if (($query['workspace'] ?? 'checklist') === 'decision') {
            $query = array_filter([
                'loan_application' => $application,
                'workspace' => 'decision',
            ]);
        }

        $out = [
            'href' => route('admin.loan-applications.show', $query).'#'.($map['hash'] ?? 'review-desk'),
            'cta' => $map['cta'],
            'gate' => $map['gate'],
        ];
        if ($customerId > 0 && (str_starts_with($fullKey, 'identity.') || $fullKey === 'identity.name_vs_crb')) {
            try {
                $out['profile_href'] = route('admin.customers.show', $customerId);
            } catch (\Throwable) {
                $out['profile_href'] = null;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, array<string, mixed>>
     */
    public function regroup(array $groups, ?LoanApplication $application = null): array
    {
        $gates = [];
        foreach (self::GATES as $key => $label) {
            $gates[$key] = [
                'key' => $key,
                'label' => $label,
                'short' => self::SHORT[$key] ?? $label,
                'groups' => [],
                'decided' => 0,
                'total' => 0,
                'failed' => 0,
                'complete' => true,
                'human_open' => 0,
                'status_label' => 'Waiting',
                'chip' => self::SHORT[$key] ?? $label,
            ];
        }

        foreach ($groups as $group) {
            $buckets = [];
            foreach ($group['items'] ?? [] as $item) {
                if ($this->isQuietAuto($item)) {
                    continue;
                }
                $fullKey = (string) ($item['key'] ?? '');
                $gate = $this->gateFor((string) ($group['key'] ?? ''), $fullKey);
                $buckets[$gate][] = $item;
            }
            foreach ($buckets as $gate => $items) {
                $clone = $group;
                $clone['items'] = $items;
                $clone['decided'] = collect($items)->whereNotNull('verdict')->count();
                $clone['total'] = count($items);
                $clone['failed'] = collect($items)->where('verdict', 'fail')->count();
                $clone['complete'] = $clone['total'] > 0 && $clone['decided'] === $clone['total'];
                $gates[$gate]['groups'][] = $clone;
                $gates[$gate]['decided'] += $clone['decided'];
                $gates[$gate]['total'] += $clone['total'];
                $gates[$gate]['failed'] += $clone['failed'];
                $gates[$gate]['human_open'] += collect($items)->filter(function ($item) {
                    return ($item['verdict'] ?? null) === null;
                })->count();
                if (! $clone['complete']) {
                    $gates[$gate]['complete'] = false;
                }
            }
        }

        foreach ($gates as $key => $gate) {
            if ((int) $gate['total'] === 0) {
                $gates[$key]['complete'] = false;
            }
        }

        $sequence = $application
            ? app(ScreeningSequenceService::class)->snapshot($application, $gates)
            : null;
        $unlocked = is_array($sequence['unlocked'] ?? null) ? $sequence['unlocked'] : [];

        foreach ($gates as $key => $gate) {
            $remaining = max(0, (int) $gate['total'] - (int) $gate['decided']);
            $status = match (true) {
                (int) $gate['total'] < 1 => 'Waiting',
                (int) $gate['failed'] > 0 => 'Attention',
                (bool) ($gate['complete'] ?? false) => 'Complete',
                $remaining > 0 => $remaining.' remaining',
                default => 'Waiting',
            };
            $short = (string) ($gate['short'] ?? self::SHORT[$key] ?? $gate['label']);
            $isUnlocked = $sequence === null
                ? true
                : (bool) ($unlocked[$key] ?? ($key === 'income'));
            $locked = ! $isUnlocked;
            $lockDetail = $locked ? (self::LOCK_REASONS[$key] ?? 'Locked') : null;
            $gates[$key]['locked'] = $locked;
            $gates[$key]['lock_detail'] = $lockDetail;
            $gates[$key]['status_label'] = $locked ? 'Locked' : $status;
            $gates[$key]['chip'] = $locked
                ? $short.' · '.$lockDetail
                : match ($status) {
                    'Complete' => $short.' ✓',
                    'Attention' => $short.' · Attention',
                    'Waiting' => $short,
                    default => $short.' · '.$status,
                };
        }

        return array_filter(
            $gates,
            fn ($gate) => ($gate['total'] ?? 0) > 0 || ! empty($gate['locked']),
        );
    }
}
