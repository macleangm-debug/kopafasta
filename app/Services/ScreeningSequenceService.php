<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\ValuationAssignment;

/**
 * Sequential Screening orchestration: Gate 1 (declared) then Gate 2 (verified)
 * must complete before Identity / CRB / Collateral / Final are opened.
 *
 * Does not change affordability math — it only sequences existing results.
 */
class ScreeningSequenceService
{
    public const GATE_DECLARED = 'declared';

    public const GATE_INCOME = 'income';

    public const GATE_IDENTITY = 'identity';

    public const GATE_CRB = 'crb';

    public const GATE_COLLATERAL = 'collateral';

    public const GATE_FINAL = 'final';

    public const SEQUENCE = [
        self::GATE_DECLARED => '1 · Initial affordability',
        self::GATE_INCOME => '2 · Income & statement review',
        self::GATE_IDENTITY => '3 · Identity & KYC',
        self::GATE_CRB => '4 · CRB',
        self::GATE_COLLATERAL => '5 · Collateral & valuation',
        self::GATE_FINAL => '6 · Final review',
    ];

    public const SHORT = [
        self::GATE_DECLARED => '1 Affordability',
        self::GATE_INCOME => '2 Income',
        self::GATE_IDENTITY => '3 Identity',
        self::GATE_CRB => '4 CRB',
        self::GATE_COLLATERAL => '5 Collateral',
        self::GATE_FINAL => '6 Final review',
    ];

    public const LATER_GATES = [
        self::GATE_IDENTITY,
        self::GATE_CRB,
        self::GATE_COLLATERAL,
        self::GATE_FINAL,
    ];

    public function __construct(
        private readonly CapacityAutoRejectService $autoReject,
        private readonly CreditEligibilityPolicyService $policy,
        private readonly UnderwritingSettingsService $settings,
        private readonly GroupAffordabilityService $affordability,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(LoanApplication $application): array
    {
        $park = $this->autoReject->state($application);
        $parkPending = ($park['status'] ?? null) === CapacityAutoRejectService::STATUS_PENDING;
        $parkGate = (string) ($park['gate'] ?? self::GATE_DECLARED);
        $grandfathered = $this->isGrandfathered($application);
        $policy = $this->policy->evaluate($application);

        $declared = $this->declaredStatus($application, $park, $parkPending, $parkGate, $policy, $grandfathered);
        $verified = $this->verifiedStatus($application, $park, $parkPending, $parkGate, $policy, $declared, $grandfathered);

        $laterUnlocked = $grandfathered
            || (($declared['pass'] ?? false) && ($verified['pass'] ?? false) && ! $parkPending);

        if ($parkPending) {
            $laterUnlocked = false;
        }

        $next = $this->nextAction($application, $declared, $verified, $policy, $park, $parkPending, $laterUnlocked, $grandfathered);

        return [
            'grandfathered' => $grandfathered,
            'later_unlocked' => $laterUnlocked,
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
            'sequence' => $this->sequenceRows($declared, $verified, $laterUnlocked, $parkPending, $parkGate),
            'headline' => $next['label'] ?? 'Continue screening',
            'summary_kicker' => $this->summaryKicker($declared, $verified, $laterUnlocked, $parkPending),
        ];
    }

    public function laterGatesUnlocked(LoanApplication $application): bool
    {
        return (bool) ($this->snapshot($application)['later_unlocked'] ?? false);
    }

    public function gateUnlocked(LoanApplication $application, string $gate): bool
    {
        $snap = $this->snapshot($application);
        if (in_array($gate, self::LATER_GATES, true)) {
            return (bool) $snap['later_unlocked'];
        }
        if ($gate === self::GATE_INCOME) {
            return (bool) ($snap['declared']['pass'] ?? false) && ! ($snap['pending_rejection'] ?? false);
        }

        return true;
    }

    /**
     * Files already past early screening must not be locked behind the new sequence.
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
                'label' => 'Income & statement review',
                'detail' => 'Locked — complete initial affordability first',
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
                'detail' => 'Continue screening',
            ];
        }

        return [
            'pass' => false,
            'fail' => false,
            'status' => 'in_progress',
            'label' => 'Income & statement review',
            'detail' => 'Enter statement totals, then the system evaluates verified affordability.',
        ];
    }

    /**
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $verified
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>|null  $park
     * @return array{label: string, detail: string, cta: string, href: ?string, resolution: ?string}
     */
    private function nextAction(
        LoanApplication $application,
        array $declared,
        array $verified,
        array $policy,
        ?array $park,
        bool $parkPending,
        bool $laterUnlocked,
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
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'checklist',
                    'gate' => 'income',
                ]).'#review-desk',
                'resolution' => null,
            ];
        }

        if (! ($declared['pass'] ?? false)) {
            return [
                'label' => 'Initial affordability must pass first',
                'detail' => (string) ($declared['detail'] ?? ''),
                'cta' => 'View affordability',
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'checklist',
                    'gate' => 'income',
                ]).'#review-desk',
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

        if ($laterUnlocked) {
            return [
                'label' => $grandfathered
                    ? 'Early screening passed — continue review'
                    : 'Early screening passed — Continue to Identity',
                'detail' => 'Identity, CRB, collateral and final review are open.',
                'cta' => 'Continue screening',
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'checklist',
                    'gate' => 'identity',
                ]).'#review-desk',
                'resolution' => null,
            ];
        }

        return [
            'label' => 'Continue screening',
            'detail' => '',
            'cta' => 'Open checklist',
            'href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
            ]).'#review-desk',
            'resolution' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $verified
     * @return list<array<string, mixed>>
     */
    private function sequenceRows(
        array $declared,
        array $verified,
        bool $laterUnlocked,
        bool $parkPending,
        string $parkGate,
    ): array {
        $rows = [];
        foreach (self::SEQUENCE as $key => $label) {
            $status = 'locked';
            $chip = 'Locked';
            $detail = 'Complete Income & Statement Review to continue screening.';

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
            } elseif ($laterUnlocked) {
                $status = 'open';
                $chip = 'Open';
                $detail = 'Continue screening';
            } else {
                $status = 'locked';
                $chip = 'Locked';
                $detail = $parkPending
                    ? (($parkGate === 'verified')
                        ? 'Verified affordability failed — later gates stay locked'
                        : 'Locked — complete initial affordability first')
                    : 'Locked — complete income & statement review first';
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
     */
    private function summaryKicker(array $declared, array $verified, bool $laterUnlocked, bool $parkPending): string
    {
        if ($parkPending) {
            return 'Screening · pending automatic rejection';
        }
        if (! ($declared['pass'] ?? false)) {
            return 'Screening · Gate 1 of 6 · Initial affordability';
        }
        if (! ($verified['pass'] ?? false)) {
            return 'Screening · Gate 2 of 6 · Income review';
        }
        if ($laterUnlocked) {
            return 'Screening · 2 of 6 gates passed · Continue with Identity';
        }

        return 'Screening';
    }
}
