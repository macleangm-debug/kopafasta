<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\User;

/**
 * Guided Gate 3 presentation — reuses CRB summary, freshness, and Profile↔CRB cross-check.
 * Does not invent policy or create a second checklist engine.
 */
class Gate3CrbPanelService
{
    public function __construct(
        private readonly CrbCreditCheckService $crb,
        private readonly CrbFreshnessService $freshness,
        private readonly ProfileCrbCrossCheckService $crossCheck,
        private readonly ScreeningExceptionService $exceptions,
    ) {}

    /**
     * @param  array{person?: string, m?: int|null, g?: int|null}  $subject
     * @return array<string, mixed>
     */
    public function panel(
        LoanApplication $application,
        ?Customer $customer,
        array $subject = [],
        ?User $actor = null,
    ): array {
        $person = (string) ($subject['person'] ?? 'borrower');
        $m = isset($subject['m']) ? (int) $subject['m'] : null;
        $g = isset($subject['g']) ? (int) $subject['g'] : null;
        $role = match ($person) {
            'guarantor' => 'Guarantor',
            'member' => 'Member',
            default => 'Borrower',
        };

        if (! $customer) {
            return $this->emptyPanel($role, 'WAITING', 'Member profile is missing. Gate 3 cannot run.');
        }

        $summary = $this->crb->summaryForCustomer($customer, $application);
        $history = $this->crb->latest($customer);
        $validFor = $this->freshness->freshnessDays();
        $age = $this->freshness->daysSinceCheck($history);
        $reportDate = $history?->checked_at;
        $freshStatus = match (true) {
            ! $history?->checked_at => 'MISSING',
            $this->freshness->isExpired($history) => 'EXPIRED',
            default => 'CURRENT',
        };

        $analysis = $this->crossCheck->analyze($customer, $summary);
        $identityFlags = collect($analysis['identity_flags'] ?? []);
        $creditFlags = collect($analysis['credit_flags'] ?? []);
        $allFlags = $identityFlags->merge($creditFlags)->values();

        $hardCodes = ScreeningExceptionService::HARD_CODES;
        $identityHardCodes = ['name_mismatch', 'dob_mismatch', 'gender_mismatch', 'id_mismatch'];
        $identityHardOpen = $allFlags->filter(function ($flag) use ($identityHardCodes, $application, $person, $m, $g) {
            $code = (string) ($flag['code'] ?? '');
            if (! in_array($code, $identityHardCodes, true)) {
                return false;
            }

            return ! $this->exceptions->waiverStored($application, $code, $person, $m, $g);
        });

        $hardOpen = $allFlags->filter(function ($flag) use ($hardCodes, $application, $person, $m, $g) {
            $code = (string) ($flag['code'] ?? '');
            if (! in_array($code, $hardCodes, true)) {
                return false;
            }

            return ! $this->exceptions->waiverStored($application, $code, $person, $m, $g);
        });

        $subjectName = (string) ($summary['personal']['full_name']
            ?? $summary['identity']['full_name']
            ?? '');
        // Subject ownership is identity-only. Credit hard fails (reject/delinquency) are separate.
        $subjectMatches = $identityHardOpen->isEmpty()
            && $freshStatus === 'CURRENT'
            && $subjectName !== ''
            && $this->namesLikelyMatch($customer->full_name, $subjectName);

        // Explicit identity mismatches always mean subject does not match.
        if ($identityHardOpen->isNotEmpty()) {
            $subjectMatches = false;
        }

        $comparisons = $this->identityComparisons($customer, $summary, $analysis);
        $flagRows = $allFlags->map(function ($flag) use ($application, $person, $m, $g) {
            $code = (string) ($flag['code'] ?? '');
            $waiver = $code !== ''
                ? $this->exceptions->waiverFor($application, $code, $person, $m, $g)
                : null;
            $level = $this->exceptions->flagLevel($flag, is_array($waiver));
            $classification = match (true) {
                in_array($code, ScreeningExceptionService::HARD_CODES, true) => 'AUTOMATIC SYSTEM CHECK',
                $this->exceptions->isReviewableCode($code) => 'HUMAN RESOLUTION REQUIRED',
                ($flag['severity'] ?? '') === 'info' => 'INFORMATION ONLY',
                default => 'INFORMATION ONLY',
            };

            return [
                'code' => $code,
                'title' => $flag['title'] ?? $code,
                'detail' => $flag['detail'] ?? '',
                'severity' => $flag['severity'] ?? 'info',
                'level' => $level,
                'classification' => $classification,
                'reviewable' => $this->exceptions->isReviewableCode($code),
                'hard' => in_array($code, ScreeningExceptionService::HARD_CODES, true),
                'waiver' => $waiver,
                'profile' => null,
                'crb' => null,
            ];
        })->all();

        $openReviewable = collect($flagRows)->first(
            fn ($row) => ! empty($row['reviewable']) && ($row['level'] ?? '') !== 'resolved'
        );
        $criticalOpen = collect($flagRows)->where('level', 'critical')->count();
        $needsReviewOpen = collect($flagRows)->where('level', 'needs_review')->count();

        $chip = match (true) {
            $freshStatus !== 'CURRENT' => 'WAITING',
            $criticalOpen > 0 || $hardOpen->isNotEmpty() => 'FAILED',
            $needsReviewOpen > 0 => 'REFER',
            ($summary['recommendation'] ?? '') === 'refer' && ! $this->exceptions->waiverStored($application, 'crb_refer', $person, $m, $g) => 'REFER',
            default => 'PASSED',
        };

        $next = match ($chip) {
            'WAITING' => $freshStatus === 'MISSING'
                ? 'Obtain a current CRB report before Gate 3 can be completed.'
                : 'A current CRB report is required before Gate 3 can be completed. Historical reports stay on file.',
            'FAILED' => ! $subjectMatches
                ? 'This CRB report does not match this person. Hard identity mismatches cannot be accepted. Follow the Screening rejection / replacement path.'
                : 'Hard CRB policy failures block Gate 3. Follow the existing Screening path.',
            'REFER' => $subjectMatches
                ? 'Resolve reviewable CRB discrepancies (explain & accept where policy allows), then finish remaining Gate 3 checks.'
                : 'Confirm this CRB belongs to this person before resolving secondary discrepancies.',
            default => 'Gate 3 checks for this participant are clear. Continue when every required participant also passes Gate 3.',
        };

        $crbHref = route('admin.loan-applications.show', array_filter([
            'loan_application' => $application,
            'workspace' => 'checklist',
            'desk_phase' => 'capacity',
            'capacity_tab' => 'crb',
            'gate' => 'crb',
            'review_person' => $person,
            'review_m' => $m,
            'review_g' => $g,
            'from' => 'guided',
        ]));

        return [
            'title' => 'GATE 3 · CRB & CREDIT HISTORY',
            'participant_name' => $customer->full_name,
            'role' => $role,
            'person' => $person,
            'm' => $m,
            'g' => $g,
            'freshness' => [
                'report_date' => $reportDate?->format('d M Y'),
                'age_days' => $age,
                'valid_for_days' => $validFor,
                'status' => $freshStatus,
                'label' => match ($freshStatus) {
                    'CURRENT' => '✓ CURRENT',
                    'EXPIRED' => '✕ EXPIRED',
                    default => '✕ MISSING',
                },
                'source' => match (true) {
                    ($history?->source === 'crb_stub') => 'TEST / CRB STUB',
                    filled($history?->source) => 'D&B LIVE',
                    default => null,
                },
                'provider_mode' => match (true) {
                    ($history?->source === 'crb_stub') => 'STUB',
                    filled($history?->source) => 'LIVE',
                    default => app(\App\Services\CrbService::class)->operationalStatus()['mode_short'] ?? null,
                },
            ],
            'subject' => [
                'profile_name' => $customer->full_name,
                'crb_name' => $subjectName !== '' ? $subjectName : '—',
                'matches' => $subjectMatches,
                'label' => $subjectMatches ? '✓ SUBJECT MATCH' : '✕ SUBJECT MISMATCH',
            ],
            'result_banner' => match (true) {
                $freshStatus !== 'CURRENT' => 'WAITING — Current CRB required',
                $criticalOpen > 0 => '⚠ CRITICAL ISSUES FOUND',
                $needsReviewOpen > 0 => 'NEEDS REVIEW',
                default => 'CRB CLEAR',
            },
            'comparisons' => $comparisons,
            'flags' => $flagRows,
            'credit' => [
                'score' => $summary['score'] ?? null,
                'recommendation' => strtoupper((string) ($summary['recommendation'] ?? '—')),
                'existing_loans' => (int) ($summary['existing_loans'] ?? 0),
                'outstanding' => (float) ($summary['outstanding_balance'] ?? 0),
                'delinquencies' => (int) ($summary['delinquencies'] ?? 0),
                'loan_history' => collect($summary['loan_history'] ?? [])->take(5)->values()->all(),
            ],
            'chip' => $chip,
            'next_action' => $next,
            'block_secondary' => $freshStatus !== 'CURRENT' || ! $subjectMatches,
            'open_reviewable_code' => $openReviewable['code'] ?? null,
            'crb_href' => $crbHref,
            'allow_accept' => $freshStatus === 'CURRENT' && $subjectMatches,
            'actor_can_accept' => $actor?->hasPermission('applications.review') ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $analysis
     * @return list<array{label: string, profile: string, crb: string, status: string}>
     */
    private function identityComparisons(Customer $customer, array $summary, array $analysis): array
    {
        $personal = (array) ($summary['personal'] ?? []);
        $rows = [
            [
                'label' => 'Name',
                'profile' => (string) ($customer->full_name ?: '—'),
                'crb' => (string) ($personal['full_name'] ?? $summary['identity']['full_name'] ?? '—'),
                'code' => 'name_mismatch',
            ],
            [
                'label' => 'Date of birth',
                'profile' => optional($customer->date_of_birth)->format('d M Y') ?: '—',
                'crb' => (string) ($personal['date_of_birth'] ?? $summary['identity']['date_of_birth'] ?? '—'),
                'code' => 'dob_mismatch',
            ],
            [
                'label' => 'Gender',
                'profile' => ucfirst((string) ($customer->gender ?: '—')),
                'crb' => ucfirst((string) ($personal['gender'] ?? $summary['identity']['gender'] ?? '—')),
                'code' => 'gender_mismatch',
            ],
            [
                'label' => 'National ID',
                'profile' => (string) ($customer->national_id ?: '—'),
                'crb' => $this->crbIdDisplay($personal),
                'code' => 'id_mismatch',
            ],
        ];

        $flagCodes = collect($analysis['identity_flags'] ?? [])->pluck('code')->all();
        $matchCodes = collect($analysis['matches'] ?? [])->pluck('code')->all();

        return array_map(function (array $row) use ($flagCodes, $matchCodes) {
            $status = match (true) {
                in_array($row['code'], $flagCodes, true) => 'MISMATCH',
                in_array($row['code'] === 'name_mismatch' ? 'full_name' : ($row['code'] === 'dob_mismatch' ? 'date_of_birth' : ($row['code'] === 'id_mismatch' ? 'national_id' : 'gender')), $matchCodes, true) => 'MATCH',
                ($row['profile'] === '—' || $row['crb'] === '—') => 'MISSING',
                default => 'COMPARE',
            };

            return [
                'label' => $row['label'],
                'profile' => $row['profile'],
                'crb' => $row['crb'],
                'status' => $status,
            ];
        }, $rows);
    }

    /** @param  array<string, mixed>  $personal */
    private function crbIdDisplay(array $personal): string
    {
        $ids = collect($personal['ids'] ?? [])
            ->pluck('id_number')
            ->filter()
            ->values();
        if ($ids->isEmpty()) {
            return '—';
        }

        return $ids->implode(', ');
    }

    private function namesLikelyMatch(?string $a, ?string $b): bool
    {
        $a = strtolower(trim(preg_replace('/\s+/', ' ', (string) $a) ?? ''));
        $b = strtolower(trim(preg_replace('/\s+/', ' ', (string) $b) ?? ''));
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        similar_text($a, $b, $pct);

        return $pct >= 85;
    }

    /** @return array<string, mixed> */
    private function emptyPanel(string $role, string $chip, string $next): array
    {
        return [
            'title' => 'GATE 3 · CRB & CREDIT HISTORY',
            'participant_name' => '—',
            'role' => $role,
            'freshness' => [
                'report_date' => null,
                'age_days' => null,
                'valid_for_days' => $this->freshness->freshnessDays(),
                'status' => 'MISSING',
                'label' => '✕ MISSING',
                'source' => null,
            ],
            'subject' => [
                'profile_name' => '—',
                'crb_name' => '—',
                'matches' => false,
                'label' => '✕ SUBJECT MISMATCH',
            ],
            'result_banner' => 'WAITING — Current CRB required',
            'comparisons' => [],
            'flags' => [],
            'credit' => [
                'score' => null,
                'recommendation' => '—',
                'existing_loans' => 0,
                'outstanding' => 0,
                'delinquencies' => 0,
                'loan_history' => [],
            ],
            'chip' => $chip,
            'next_action' => $next,
            'block_secondary' => true,
            'open_reviewable_code' => null,
            'crb_href' => null,
            'allow_accept' => false,
            'actor_can_accept' => false,
        ];
    }
}
