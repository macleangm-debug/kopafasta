<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\ValuationAssignment;

/**
 * Sequential Screening orchestration. Does not change affordability math —
 * it only sequences existing Gate 1–6 results.
 *
 * Canonical order: Affordability → Verified income → CRB → Collateral → Identity → Final → Decision.
 */
class ScreeningSequenceService
{
    public const GATE_DECLARED = 'declared';

    public const GATE_INCOME = 'income';

    public const GATE_COLLATERAL = 'collateral';

    public const GATE_CRB = 'crb';

    public const GATE_IDENTITY = 'identity';

    public const GATE_FINAL = 'final';

    public const SEQUENCE = [
        self::GATE_DECLARED => '1 · Initial affordability',
        self::GATE_INCOME => '2 · Verified income & statements',
        self::GATE_CRB => '3 · CRB / Credit history',
        self::GATE_COLLATERAL => '4 · Collateral & security',
        self::GATE_IDENTITY => '5 · Identity, people & contacts',
        self::GATE_FINAL => '6 · Final review',
    ];

    public const SHORT = [
        self::GATE_DECLARED => '1 Affordability',
        self::GATE_INCOME => '2 Income',
        self::GATE_CRB => '3 CRB',
        self::GATE_COLLATERAL => '4 Collateral',
        self::GATE_IDENTITY => '5 Identity',
        self::GATE_FINAL => '6 Final review',
    ];

    public const LOCK_REASONS = [
        self::GATE_INCOME => 'Locked — complete initial affordability first',
        self::GATE_CRB => 'Locked — complete verified income first',
        self::GATE_COLLATERAL => 'Locked — complete CRB first',
        self::GATE_IDENTITY => 'Locked — complete collateral first',
        self::GATE_FINAL => 'Locked — complete identity, people & contacts first',
    ];

    /** @deprecated Use sequential unlocked[] — kept as G1+G2 passed for workflow compatibility. */
    public const LATER_GATES = [
        self::GATE_CRB,
        self::GATE_COLLATERAL,
        self::GATE_IDENTITY,
        self::GATE_FINAL,
    ];

    public static function gateIndex(string $key): int
    {
        return match ($key) {
            self::GATE_DECLARED => 1,
            self::GATE_INCOME => 2,
            self::GATE_CRB => 3,
            self::GATE_COLLATERAL => 4,
            self::GATE_IDENTITY => 5,
            default => 6,
        };
    }

    public function __construct(
        private readonly CapacityAutoRejectService $autoReject,
        private readonly CreditEligibilityPolicyService $policy,
        private readonly UnderwritingSettingsService $settings,
        private readonly GroupAffordabilityService $affordability,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>|null  $gateMeta  Optional regroup() output to refine later-gate completeness.
     * @return array<string, mixed>
     */
    public function snapshot(LoanApplication $application, ?array $gateMeta = null): array
    {
        $park = $this->autoReject->state($application);
        $parkPending = ($park['status'] ?? null) === CapacityAutoRejectService::STATUS_PENDING;
        $parkGate = (string) ($park['gate'] ?? self::GATE_DECLARED);
        $grandfathered = $this->isGrandfathered($application);
        $policy = $this->policy->evaluate($application);

        $declared = $this->declaredStatus($application, $park, $parkPending, $parkGate, $policy, $grandfathered);
        $verified = $this->verifiedStatus($application, $park, $parkPending, $parkGate, $policy, $declared, $grandfathered);

        $earlyPassed = ($declared['pass'] ?? false) && ($verified['pass'] ?? false) && ! $parkPending;
        $progress = $this->mergeProgress($this->payloadProgress($application), $gateMeta);
        $unlocked = $this->sequentialUnlocks($declared, $verified, $parkPending, $grandfathered, $progress, $application);

        $next = $this->nextAction(
            $application,
            $declared,
            $verified,
            $policy,
            $park,
            $parkPending,
            $unlocked,
            $progress,
            $grandfathered,
        );

        return [
            'grandfathered' => $grandfathered,
            'later_unlocked' => $earlyPassed,
            'unlocked' => $unlocked,
            'progress' => $progress,
            'pending_rejection' => $parkPending,
            'park' => $park,
            'park_gate' => $parkGate,
            'remaining_label' => $this->autoReject->remainingLabel($application),
            'gate_1_delay_hours' => $this->settings->capacityAutoRejectDelayHours(),
            'gate_2_delay_hours' => $this->settings->verifiedCapacityAutoRejectDelayHours(),
            'declared' => $declared,
            'verified' => $verified,
            'policy' => $policy,
            'next_action' => $next,
            'sequence' => $this->sequenceRows($declared, $verified, $unlocked, $progress, $parkPending, $parkGate),
            'headline' => $next['label'] ?? 'Continue screening',
            'summary_kicker' => $this->summaryKicker($declared, $verified, $unlocked, $parkPending),
            'decision_unlocked' => (bool) ($unlocked[self::GATE_FINAL] ?? false)
                && ($progress[self::GATE_FINAL]['complete'] ?? false),
        ];
    }

    public function laterGatesUnlocked(LoanApplication $application): bool
    {
        return (bool) ($this->snapshot($application)['later_unlocked'] ?? false);
    }

    public function gateUnlocked(LoanApplication $application, string $gate): bool
    {
        $snap = $this->snapshot($application);
        if ($gate === self::GATE_DECLARED) {
            return true;
        }

        return (bool) ($snap['unlocked'][$gate] ?? false);
    }

    /**
     * Files already past early screening must not be locked behind the new sequence
     * for gates they already started. Completed answers stay completed.
     */
    public function isGrandfathered(LoanApplication $application): bool
    {
        if ($this->autoReject->isPending($application)) {
            return false;
        }

        if (filled($application->recommended_at) || filled($application->recommendation_type)) {
            return true;
        }

        $valuation = data_get($application->screening_payload, 'collateral_secure.valuation.status')
            ?? data_get($application->screening_payload, 'valuation.status');
        if (in_array((string) $valuation, ['completed', 'accepted', 'complete'], true)) {
            return true;
        }

        $secure = (string) data_get($application->screening_payload, 'collateral_secure.status', '');
        if (in_array($secure, ['secured', 'complete', 'completed', 'valuation_complete'], true)) {
            return true;
        }

        if (ValuationAssignment::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', ['completed', 'accepted', 'complete'])
            ->exists()) {
            return true;
        }

        $bySubject = (array) data_get($application->screening_payload, 'screening_checklist.by_subject', []);
        foreach ($bySubject as $subject) {
            foreach ((array) ($subject['items'] ?? []) as $key => $row) {
                if (! is_array($row) || ($row['verdict'] ?? null) === null) {
                    continue;
                }
                if (str_starts_with((string) $key, 'collateral.')
                    || str_starts_with((string) $key, 'identity.')
                    || str_contains((string) $key, 'crb')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $park
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function declaredStatus(
        LoanApplication $application,
        ?array $park,
        bool $parkPending,
        string $parkGate,
        array $policy,
        bool $grandfathered,
    ): array {
        if ($parkPending && $parkGate !== 'verified') {
            return [
                'pass' => false,
                'fail' => true,
                'status' => 'pending_rejection',
                'label' => 'Initial affordability failed',
                'detail' => 'Pending automatic rejection · '.($this->autoReject->remainingLabel($application) ?? 'waiting'),
            ];
        }

        if ($grandfathered) {
            return [
                'pass' => true,
                'fail' => false,
                'status' => 'passed',
                'label' => 'Initial affordability passed',
                'detail' => 'Already past early screening on this file.',
            ];
        }

        $action = (string) ($policy['application_action'] ?? '');
        if ($action === CreditEligibilityPolicyService::ACTION_PENDING_REJECTION
            && ($policy['failing_gate'] ?? self::GATE_DECLARED) === self::GATE_DECLARED) {
            return [
                'pass' => false,
                'fail' => true,
                'status' => 'fail',
                'label' => 'Initial affordability failed',
                'detail' => (string) ($policy['reason'] ?? 'Declared income cannot support this loan.'),
            ];
        }

        $declared = $this->affordability->evaluate($application, declaredOnly: true);
        if (($declared['verdict'] ?? '') === 'fail' && ($policy['application_action'] ?? '') === CreditEligibilityPolicyService::ACTION_PENDING_REJECTION) {
            return [
                'pass' => false,
                'fail' => true,
                'status' => 'fail',
                'label' => 'Initial affordability failed',
                'detail' => (string) ($declared['reason'] ?? ''),
            ];
        }

        return [
            'pass' => true,
            'fail' => false,
            'status' => 'passed',
            'label' => 'Initial affordability passed',
            'detail' => 'Declared income supports the requested terms.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $park
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>  $declared
     * @return array<string, mixed>
     */
    private function verifiedStatus(
        LoanApplication $application,
        ?array $park,
        bool $parkPending,
        string $parkGate,
        array $policy,
        array $declared,
        bool $grandfathered,
    ): array {
        if (! ($declared['pass'] ?? false)) {
            return [
                'pass' => false,
                'fail' => false,
                'status' => 'locked',
                'label' => 'Verified income & statements',
                'detail' => self::LOCK_REASONS[self::GATE_INCOME],
            ];
        }

        if ($parkPending && $parkGate === 'verified') {
            return [
                'pass' => false,
                'fail' => true,
                'status' => 'pending_rejection',
                'label' => 'Verified affordability failed',
                'detail' => 'Pending automatic rejection · '.($this->autoReject->remainingLabel($application) ?? 'waiting'),
            ];
        }

        if ($grandfathered) {
            return [
                'pass' => true,
                'fail' => false,
                'status' => 'passed',
                'label' => 'Verified income & affordability passed',
                'detail' => 'This file already progressed past statement review.',
            ];
        }

        if (($policy['failing_gate'] ?? null) === 'verified'
            && ($policy['application_action'] ?? '') === CreditEligibilityPolicyService::ACTION_PENDING_REJECTION) {
            return [
                'pass' => false,
                'fail' => true,
                'status' => 'fail',
                'label' => 'Verified affordability failed',
                'detail' => (string) ($policy['reason'] ?? ''),
            ];
        }

        if ($this->policy->verifiedIncomeComplete($application) && ($policy['verified_pass'] ?? false)) {
            return [
                'pass' => true,
                'fail' => false,
                'status' => 'passed',
                'label' => 'Verified income & affordability passed',
                'detail' => 'Continue to CRB / credit history',
            ];
        }

        return [
            'pass' => false,
            'fail' => false,
            'status' => 'in_progress',
            'label' => 'Verified income & statements',
            'detail' => 'Enter statement totals, then the system evaluates verified affordability.',
        ];
    }

    /**
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $verified
     * @param  array<string, mixed>  $progress
     * @return array<string, bool>
     */
    private function sequentialUnlocks(
        array $declared,
        array $verified,
        bool $parkPending,
        bool $grandfathered,
        array $progress,
        LoanApplication $application,
    ): array {
        $g1 = (bool) ($declared['pass'] ?? false);
        $g2 = (bool) ($verified['pass'] ?? false) && ! $parkPending;

        $unlocked = [
            self::GATE_DECLARED => true,
            self::GATE_INCOME => $g1 && ! ($parkPending && ($verified['status'] ?? '') !== 'pending_rejection'),
            self::GATE_CRB => false,
            self::GATE_IDENTITY => false,
            self::GATE_COLLATERAL => false,
            self::GATE_FINAL => false,
        ];

        if ($parkPending && (($declared['status'] ?? '') === 'pending_rejection')) {
            $unlocked[self::GATE_INCOME] = false;
        } elseif ($g1) {
            $unlocked[self::GATE_INCOME] = true;
        }

        if ($g2) {
            $unlocked[self::GATE_CRB] = true;
        }
        if ($unlocked[self::GATE_CRB] && $this->gateResolved($progress, self::GATE_CRB)) {
            $unlocked[self::GATE_COLLATERAL] = true;
        }
        if ($unlocked[self::GATE_COLLATERAL] && $this->collateralResolved($application, $progress)) {
            $unlocked[self::GATE_IDENTITY] = true;
        }
        if ($unlocked[self::GATE_IDENTITY] && $this->gateResolved($progress, self::GATE_IDENTITY)) {
            $unlocked[self::GATE_FINAL] = true;
        }

        if ($grandfathered) {
            foreach ([self::GATE_CRB, self::GATE_COLLATERAL, self::GATE_IDENTITY, self::GATE_FINAL] as $gate) {
                if ($this->gateHasWork($progress, $gate) || ($progress[$gate]['complete'] ?? false)) {
                    $unlocked[$gate] = true;
                }
            }
            // In-progress files keep later gates they already started; do not force them back.
            if ($unlocked[self::GATE_FINAL]) {
                $unlocked[self::GATE_IDENTITY] = true;
                $unlocked[self::GATE_COLLATERAL] = true;
                $unlocked[self::GATE_CRB] = true;
            } elseif ($unlocked[self::GATE_IDENTITY]) {
                $unlocked[self::GATE_COLLATERAL] = true;
                $unlocked[self::GATE_CRB] = true;
            } elseif ($unlocked[self::GATE_COLLATERAL]) {
                $unlocked[self::GATE_CRB] = true;
            }
            if ($g1) {
                $unlocked[self::GATE_INCOME] = true;
            }
        }

        return $unlocked;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function gateResolved(array $progress, string $gate): bool
    {
        $row = $progress[$gate] ?? [];
        if (! empty($row['complete'])) {
            return true;
        }
        $total = (int) ($row['total'] ?? 0);
        $decided = (int) ($row['decided'] ?? 0);
        $open = (int) ($row['human_open'] ?? max(0, $total - $decided));

        return $total > 0 && $open === 0 && $decided >= $total;
    }

    /** Collateral is N/A when policy does not require it and nothing is pledged. */
    private function collateralResolved(LoanApplication $application, array $progress): bool
    {
        if ($this->gateResolved($progress, self::GATE_COLLATERAL)) {
            return true;
        }

        $application->loadMissing('collateralAssets');
        if ($application->collateralAssets->isNotEmpty()
            && (int) ($progress[self::GATE_COLLATERAL]['failed'] ?? 0) === 0) {
            return true;
        }

        $total = (int) ($progress[self::GATE_COLLATERAL]['total'] ?? 0);
        if ($total > 0) {
            return false;
        }

        if (app(CollateralSecureService::class)->isAwaitingCustomerCollateral($application)) {
            return false;
        }

        return ! app(LoanPolicyService::class)->applicationRequiresCollateral($application);
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function gateHasWork(array $progress, string $gate): bool
    {
        return (int) ($progress[$gate]['decided'] ?? 0) > 0
            || (int) ($progress[$gate]['failed'] ?? 0) > 0;
    }

    /**
     * @return array<string, array{total: int, decided: int, failed: int, complete: bool, human_open: int}>
     */
    private function payloadProgress(LoanApplication $application): array
    {
        $empty = [
            'total' => 0,
            'decided' => 0,
            'failed' => 0,
            'complete' => false,
            'human_open' => 0,
        ];
        $progress = [
            self::GATE_INCOME => $empty,
            self::GATE_CRB => $empty,
            self::GATE_IDENTITY => $empty,
            self::GATE_COLLATERAL => $empty,
            self::GATE_FINAL => $empty,
        ];

        $gateSvc = app(ScreeningChecklistGateService::class);
        $bySubject = (array) data_get($application->screening_payload, 'screening_checklist.by_subject', []);
        foreach ($bySubject as $subject) {
            foreach ((array) ($subject['items'] ?? []) as $key => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $fullKey = (string) $key;
                $groupKey = (string) (explode('.', $fullKey, 2)[0] ?? '');
                $gate = $gateSvc->gateFor($groupKey, $fullKey);
                if (! isset($progress[$gate])) {
                    continue;
                }
                $progress[$gate]['total']++;
                $verdict = $row['verdict'] ?? null;
                if ($verdict !== null && $verdict !== '') {
                    $progress[$gate]['decided']++;
                    if ($verdict === 'fail') {
                        $progress[$gate]['failed']++;
                    }
                } else {
                    $progress[$gate]['human_open']++;
                }
            }
        }

        foreach ($progress as $key => $row) {
            $progress[$key]['complete'] = $row['total'] > 0
                && $row['decided'] === $row['total']
                && $row['human_open'] === 0;
        }

        return $progress;
    }

    /**
     * @param  array<string, array<string, mixed>>  $payload
     * @param  array<string, array<string, mixed>>|null  $gateMeta
     * @return array<string, array<string, mixed>>
     */
    private function mergeProgress(array $payload, ?array $gateMeta): array
    {
        if (! is_array($gateMeta) || $gateMeta === []) {
            return $payload;
        }

        foreach ($payload as $gate => $row) {
            $meta = $gateMeta[$gate] ?? null;
            if (! is_array($meta)) {
                continue;
            }
            $total = (int) ($meta['total'] ?? $row['total']);
            $decided = (int) ($meta['decided'] ?? $row['decided']);
            $failed = (int) ($meta['failed'] ?? $row['failed']);
            $open = (int) ($meta['human_open'] ?? max(0, $total - $decided));
            $payload[$gate] = [
                'total' => $total,
                'decided' => $decided,
                'failed' => $failed,
                'human_open' => $open,
                'complete' => (bool) ($meta['complete'] ?? ($total > 0 && $decided === $total && $open === 0)),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $verified
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>|null  $park
     * @param  array<string, bool>  $unlocked
     * @param  array<string, mixed>  $progress
     * @return array{label: string, detail: string, cta: string, href: ?string, resolution: ?string}
     */
    private function nextAction(
        LoanApplication $application,
        array $declared,
        array $verified,
        array $policy,
        ?array $park,
        bool $parkPending,
        array $unlocked,
        array $progress,
        bool $grandfathered,
    ): array {
        $resolution = $policy['resolution'] ?? null;
        if (is_array($resolution) && ($resolution['blocking'] ?? false) && ! $parkPending) {
            return [
                'label' => (string) ($resolution['next_action'] ?? 'Resolve eligibility'),
                'detail' => (string) ($resolution['detail'] ?? ''),
                'cta' => (string) ($resolution['cta'] ?? 'Resolve'),
                'href' => $resolution['href'] ?? null,
                'resolution' => $resolution['code'] ?? null,
            ];
        }

        if ($parkPending) {
            $gate = (($park['gate'] ?? '') === 'verified') ? 'Verified affordability' : 'Initial affordability';

            return [
                'label' => $gate.' failed',
                'detail' => 'Pending automatic rejection · '.($this->autoReject->remainingLabel($application) ?? 'waiting'),
                'cta' => 'View status',
                'href' => $this->deskHref($application, 'income'),
                'resolution' => null,
            ];
        }

        if (! ($declared['pass'] ?? false)) {
            return [
                'label' => 'Next action: Complete initial affordability',
                'detail' => (string) ($declared['detail'] ?? ''),
                'cta' => 'View affordability',
                'href' => $this->deskHref($application, 'income'),
                'resolution' => null,
            ];
        }

        if (! ($verified['pass'] ?? false)) {
            return [
                'label' => 'Next action: Review income statements',
                'detail' => 'Step 2.1 Enter statement totals → 2.2 Activity support → 2.3 Patterns → 2.4 Affordability',
                'cta' => 'Review statements',
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'checklist',
                    'gate' => 'income',
                    'open_item' => 'activity_income.income_evidence',
                ]).'#item-activity_income.income_evidence',
                'resolution' => null,
            ];
        }

        $later = [
            self::GATE_CRB => [
                'label' => 'Next action: Complete CRB review',
                'detail' => 'Review bureau history for the borrower'.($grandfathered ? ' and each group member' : '').' before collateral.',
                'cta' => 'Open CRB',
            ],
            self::GATE_COLLATERAL => [
                'label' => 'Next action: Complete collateral & security',
                'detail' => 'Confirm required security is pledged and potentially acceptable. Valuation is not required to pass this gate.',
                'cta' => 'Open collateral',
            ],
            self::GATE_IDENTITY => [
                'label' => 'Next action: Complete identity, people & contacts',
                'detail' => 'National ID, face, phone, next of kin, LGO and related people checks.',
                'cta' => 'Open identity',
            ],
            self::GATE_FINAL => [
                'label' => 'Next action: Complete final review',
                'detail' => 'Resolve remaining exceptions, then continue to Decision.',
                'cta' => 'Open final review',
            ],
        ];

        foreach ($later as $gate => $copy) {
            if (! ($unlocked[$gate] ?? false)) {
                return [
                    'label' => $copy['label'],
                    'detail' => self::LOCK_REASONS[$gate] ?? $copy['detail'],
                    'cta' => $copy['cta'],
                    'href' => $this->deskHref($application, $gate === self::GATE_CRB ? 'crb' : ($gate === self::GATE_IDENTITY ? 'identity' : $gate)),
                    'resolution' => null,
                ];
            }
            $resolved = $gate === self::GATE_COLLATERAL
                ? $this->collateralResolved($application, $progress)
                : $this->gateResolved($progress, $gate);
            if (! $resolved || (int) ($progress[$gate]['failed'] ?? 0) > 0) {
                return [
                    'label' => $copy['label'],
                    'detail' => $copy['detail'],
                    'cta' => $copy['cta'],
                    'href' => $this->deskHref($application, $gate),
                    'resolution' => null,
                ];
            }
        }

        return [
            'label' => 'Next action: Continue to Decision',
            'detail' => 'All required Screening checks complete.',
            'cta' => 'Continue to Decision',
            'href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'decision',
            ]).'#review-recommendation',
            'resolution' => null,
        ];
    }

    private function deskHref(LoanApplication $application, string $gate): string
    {
        return route('admin.loan-applications.show', [
            'loan_application' => $application,
            'workspace' => 'checklist',
            'gate' => $gate,
        ]).'#review-desk';
    }

    /**
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $verified
     * @param  array<string, bool>  $unlocked
     * @param  array<string, mixed>  $progress
     * @return list<array<string, mixed>>
     */
    private function sequenceRows(
        array $declared,
        array $verified,
        array $unlocked,
        array $progress,
        bool $parkPending,
        string $parkGate,
    ): array {
        $rows = [];
        foreach (self::SEQUENCE as $key => $label) {
            $status = 'locked';
            $chip = 'Locked';
            $detail = self::LOCK_REASONS[$key] ?? 'Locked';

            if ($key === self::GATE_DECLARED) {
                $status = $declared['status'] ?? 'waiting';
                $chip = match ($status) {
                    'passed' => 'Passed',
                    'pending_rejection', 'fail' => 'Failed',
                    default => 'In progress',
                };
                $detail = (string) ($declared['detail'] ?? '');
            } elseif ($key === self::GATE_INCOME) {
                $status = $verified['status'] ?? 'locked';
                $chip = match ($status) {
                    'passed' => 'Passed',
                    'pending_rejection', 'fail' => 'Failed',
                    'in_progress' => 'In progress',
                    default => 'Locked',
                };
                $detail = (string) ($verified['detail'] ?? '');
            } elseif (! ($unlocked[$key] ?? false)) {
                $status = 'locked';
                $chip = self::LOCK_REASONS[$key] ?? 'Locked';
                $detail = $parkPending
                    ? (($parkGate === 'verified')
                        ? 'Verified affordability failed — later gates stay locked'
                        : self::LOCK_REASONS[self::GATE_INCOME])
                    : (self::LOCK_REASONS[$key] ?? 'Locked');
            } else {
                $row = $progress[$key] ?? [];
                if ($this->gateResolved($progress, $key) && (int) ($row['failed'] ?? 0) === 0) {
                    $status = 'passed';
                    $chip = 'Passed';
                    $detail = 'Complete';
                } elseif ((int) ($row['failed'] ?? 0) > 0) {
                    $status = 'attention';
                    $chip = 'Attention';
                    $detail = 'Needs resolution';
                } elseif ((int) ($row['decided'] ?? 0) > 0) {
                    $status = 'in_progress';
                    $chip = ((int) $row['decided']).'/'.((int) $row['total']);
                    $detail = 'In progress';
                } else {
                    $status = 'open';
                    $chip = 'In progress';
                    $detail = 'Continue screening';
                }
            }

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'short' => self::SHORT[$key],
                'status' => $status,
                'chip' => $chip,
                'detail' => $detail,
                'unlocked' => $status !== 'locked',
                'desk_gate' => $key === self::GATE_DECLARED ? self::GATE_INCOME : $key,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $verified
     * @param  array<string, bool>  $unlocked
     */
    private function summaryKicker(array $declared, array $verified, array $unlocked, bool $parkPending): string
    {
        if ($parkPending) {
            return 'Screening · pending automatic rejection';
        }
        if (! ($declared['pass'] ?? false)) {
            return 'Screening · Gate 1 of 6 · Initial affordability';
        }
        if (! ($verified['pass'] ?? false)) {
            return 'Screening · Gate 2 of 6 · Verified income';
        }
        if (! ($unlocked[self::GATE_COLLATERAL] ?? false)) {
            return 'Screening · Gate 3 of 6 · CRB';
        }
        if (! ($unlocked[self::GATE_IDENTITY] ?? false)) {
            return 'Screening · Gate 4 of 6 · Collateral';
        }
        if (! ($unlocked[self::GATE_FINAL] ?? false)) {
            return 'Screening · Gate 5 of 6 · Identity, people & contacts';
        }

        return 'Screening · Gate 6 of 6 · Final review';
    }
}
