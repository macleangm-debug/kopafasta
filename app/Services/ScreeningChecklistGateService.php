<?php

namespace App\Services;

use App\Models\LoanApplication;

/**
 * Presentation map for Screening — does not change credit rules.
 */
class ScreeningChecklistGateService
{
    public const GATES = [
        'identity' => 'Gate 1 — Identity',
        'income' => 'Gate 2 — Income & activity',
        'crb' => 'Gate 3 — Credit bureau',
        'collateral' => 'Gate 4 — Collateral',
        'final' => 'Gate 5 — Final review',
    ];

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
    ): array {
        $person = (string) ($subject['person'] ?? 'borrower');
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
                'cta' => 'Open identity',
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
                'cta' => 'Open identity',
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
            str_contains($fullKey, 'crb') || $fullKey === 'identity.name_vs_crb' || $fullKey === 'identity.marital_vs_crb' => [
                'cta' => 'Open CRB comparison',
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

        return [
            'href' => route('admin.loan-applications.show', $query).'#'.($map['hash'] ?? 'review-desk'),
            'cta' => $map['cta'],
            'gate' => $map['gate'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, array{key: string, label: string, groups: list<array<string, mixed>>, decided: int, total: int, failed: int, complete: bool, human_open: int}>
     */
    public function regroup(array $groups): array
    {
        $gates = [];
        foreach (self::GATES as $key => $label) {
            $gates[$key] = [
                'key' => $key,
                'label' => $label,
                'groups' => [],
                'decided' => 0,
                'total' => 0,
                'failed' => 0,
                'complete' => true,
                'human_open' => 0,
            ];
        }

        foreach ($groups as $group) {
            $buckets = [];
            foreach ($group['items'] ?? [] as $item) {
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
                    $verdict = $item['verdict'] ?? null;
                    $auto = ! empty($item['system_checked']) || ! empty($item['catalog_system']) || ! empty($item['read_only']);

                    return $verdict === null && ! $auto;
                })->count();
                if (! $clone['complete']) {
                    $gates[$gate]['complete'] = false;
                }
            }
        }

        return array_filter($gates, fn ($gate) => ($gate['total'] ?? 0) > 0);
    }
}
