<?php

namespace App\Services;

use App\Models\LoanApplication;

/**
 * Presentation map for Screening — does not change credit rules.
 */
class ScreeningChecklistGateService
{
    public const GATES = [
        'income' => 'Gate 2 — Income & activity',
        'identity' => 'Gate 3 — Identity',
        'crb' => 'Gate 4 — CRB',
        'collateral' => 'Gate 5 — Collateral',
        'final' => 'Gate 6 — Final review',
    ];

    public const SHORT = [
        'income' => '2 Income',
        'identity' => '3 Identity',
        'crb' => '4 CRB',
        'collateral' => '5 Collateral',
        'final' => '6 Final review',
    ];

    /**
     * @param  array<string, mixed>  $item
     */
    public function isQuietAuto(array $item): bool
    {
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
     * @param  array<string, mixed>  $item
     */
    public function isHumanWork(array $item): bool
    {
        return ! $this->isQuietAuto($item);
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
            default => match ($groupKey) {
                'identity', 'residence' => 'identity',
                'activity_income' => 'income',
                'collateral' => 'collateral',
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
                    'nida_missing', 'nida_malformed', 'nida_impossible', 'nida_unverifiable' => 'Open member National ID',
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
            str_starts_with($fullKey, 'documents.') => [
                'cta' => 'Open request',
                'gate' => 'final',
                'query' => [
                    'desk_phase' => 'capacity',
                    'gate' => 'final',
                    'capacity_tab' => 'documents',
                    'open_group' => 'documents',
                    'open_item' => $fullKey,
                ],
                'hash' => $documentRequestId ? 'doc-request-'.$documentRequestId : 'review-documents',
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
        $sequence = $application
            ? app(ScreeningSequenceService::class)->snapshot($application)
            : null;
        $laterUnlocked = $sequence['later_unlocked'] ?? true;
        $incomeUnlocked = $sequence
            ? (bool) ($sequence['declared']['pass'] ?? false) && ! ($sequence['pending_rejection'] ?? false)
            : true;

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
            $remaining = max(0, (int) $gate['total'] - (int) $gate['decided']);
            $status = match (true) {
                (int) $gate['total'] < 1 => 'Waiting',
                (int) $gate['failed'] > 0 => 'Attention',
                (bool) ($gate['complete'] ?? false) => 'Complete',
                $remaining > 0 => $remaining.' remaining',
                default => 'Waiting',
            };
            $short = (string) ($gate['short'] ?? self::SHORT[$key] ?? $gate['label']);
            $locked = match ($key) {
                'income' => ! $incomeUnlocked,
                'identity', 'crb', 'collateral', 'final' => ! $laterUnlocked,
                default => false,
            };
            $gates[$key]['locked'] = $locked;
            $gates[$key]['lock_detail'] = $locked
                ? ($key === 'income'
                    ? 'Locked — complete initial affordability first'
                    : 'Complete Income & Statement Review to continue screening.')
                : null;
            $gates[$key]['status_label'] = $locked ? 'Locked' : $status;
            $gates[$key]['chip'] = $locked
                ? $short.' · Locked'
                : match ($status) {
                    'Complete' => $short.' ✓',
                    'Attention' => $short.' · Attention',
                    'Waiting' => $short,
                    default => $short.' · '.$status,
                };
        }

        return array_filter($gates, fn ($gate) => ($gate['total'] ?? 0) > 0);
    }
}
