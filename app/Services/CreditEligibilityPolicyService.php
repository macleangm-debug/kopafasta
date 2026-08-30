<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;

/**
 * Participant-aware credit eligibility: borrower, group member, guarantor.
 *
 * Reuses GroupAffordabilityService / AffordabilityService. Hard affordability
 * failures cannot be waived. Guarantor PASS cannot rescue a failed borrower.
 */
class CreditEligibilityPolicyService
{
    public const ACTION_CONTINUE = 'continue';

    public const ACTION_PENDING_REJECTION = 'pending_rejection';

    public const ACTION_REPLACE_MEMBER = 'replace_group_member';

    public const ACTION_CONTINUE_ELIGIBLE = 'continue_with_eligible_members';

    public const ACTION_REPLACE_GUARANTOR = 'replace_guarantor';

    public const ACTION_RESOLVE_MEMBERS = 'resolve_group_members';

    public const GROUP_FAIL_REPLACE = 'replace_member';

    public const GROUP_FAIL_REJECT = 'reject_group';

    public const GUARANTOR_FAIL_REPLACE = 'replace';

    public const GUARANTOR_FAIL_REJECT = 'reject_application';

    public function __construct(
        private readonly GroupAffordabilityService $groupAffordability,
        private readonly AffordabilityService $affordability,
        private readonly UnderwritingSettingsService $settings,
        private readonly GroupLendingService $groupLending,
        private readonly StatementCapacityService $statements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(LoanApplication $application, bool $verified = false): array
    {
        $application->loadMissing(['customer', 'product', 'loanGroup.members.customer', 'customerGuarantors.invitation']);

        $eval = $this->groupAffordability->evaluate($application, declaredOnly: ! $verified);
        $isGroup = (bool) ($eval['is_group'] ?? false);
        $settingsVersion = $this->settings->policySnapshot();

        $participants = [];
        if ($isGroup) {
            foreach ((array) ($eval['members'] ?? []) as $row) {
                $participants[] = $this->participantRow(
                    role: (string) ($row['role'] ?? 'member') === 'leader' ? 'borrower' : 'group_member',
                    customerId: isset($row['customer_id']) ? (int) $row['customer_id'] : null,
                    name: (string) ($row['name'] ?? 'Member'),
                    gate1: $verified ? 'pass' : (string) ($row['verdict'] ?? 'pass'),
                    gate2: $verified ? (string) ($row['verdict'] ?? 'pass') : null,
                    reason: (string) ($row['reason'] ?? ''),
                    extra: $row,
                );
            }
        } else {
            $single = $eval['affordability'] ?? $this->affordability->evaluate($application);
            $verdict = (string) ($single['verdict'] ?? $eval['verdict'] ?? 'pass');
            $participants[] = $this->participantRow(
                role: 'borrower',
                customerId: $application->customer_id,
                name: $application->customer?->full_name ?: 'Borrower',
                gate1: $verified ? 'pass' : $verdict,
                gate2: $verified ? $verdict : null,
                reason: (string) ($single['reason'] ?? $eval['reason'] ?? ''),
                extra: is_array($single) ? $single : [],
            );
        }

        foreach ($this->guarantorParticipants($application, $verified) as $row) {
            $participants[] = $row;
        }

        return $this->applicationRule($application, $eval, $participants, $verified, $settingsVersion);
    }

    public function verifiedIncomeComplete(LoanApplication $application): bool
    {
        $application->loadMissing(['loanGroup.members', 'customer']);
        if ($this->groupLending->isGroupProduct($application->product)) {
            $members = $application->loanGroup?->members
                ?->filter(fn ($m) => ($m->member_status ?? 'active') === 'active')
                ?? collect();
            if ($members->isEmpty()) {
                return $this->statements->provenMonthly($application, 'borrower') !== null;
            }
            foreach ($members as $member) {
                if ($this->statements->provenMonthlyForGroupMember($application, $member) === null) {
                    return false;
                }
            }

            return true;
        }

        return $this->statements->provenMonthly($application, 'borrower') !== null;
    }

    /**
     * @param  array<string, mixed>  $eval
     * @param  list<array<string, mixed>>  $participants
     * @param  array<string, mixed>  $settingsVersion
     * @return array<string, mixed>
     */
    private function applicationRule(
        LoanApplication $application,
        array $eval,
        array $participants,
        bool $verified,
        array $settingsVersion,
    ): array {
        $borrower = collect($participants)->first(fn ($p) => ($p['role'] ?? '') === 'borrower');
        $members = collect($participants)->where('role', 'group_member')->values();
        $guarantors = collect($participants)->where('role', 'guarantor')->values();
        $failingGate = $verified ? 'verified' : 'declared';

        $borrowerFail = $this->hardFail($borrower);
        if ($borrowerFail) {
            return $this->result(
                $application,
                $participants,
                $eval,
                $verified,
                $settingsVersion,
                self::ACTION_PENDING_REJECTION,
                'Borrower failed '.($verified ? 'verified' : 'initial').' affordability. A guarantor cannot rescue this loan.',
                $failingGate,
                resolution: null,
                verifiedPass: false,
            );
        }

        $failedMembers = $members->filter(fn ($p) => $this->hardFail($p))->values();
        if ($failedMembers->isNotEmpty() && $this->groupLending->isGroupProduct($application->product)) {
            $activeCount = $members->count() + 1; // + borrower/leader
            $eligible = $activeCount - $failedMembers->count();
            $min = $this->groupLending->memberLimits()['min'];
            $strategy = $this->settings->groupMemberHardFailAction();

            if ($strategy === self::GROUP_FAIL_REJECT) {
                return $this->result(
                    $application,
                    $participants,
                    $eval,
                    $verified,
                    $settingsVersion,
                    self::ACTION_PENDING_REJECTION,
                    'Group policy rejects the application when a member fails a hard affordability gate.',
                    $failingGate,
                    verifiedPass: false,
                );
            }

            $names = $failedMembers->pluck('participant')->filter()->values()->all();
            if ($eligible < $min) {
                $resolution = [
                    'code' => self::ACTION_REPLACE_MEMBER,
                    'outcome' => 'member_not_eligible',
                    'application' => 'may_continue_after_resolution',
                    'resolution' => 'replace_group_member',
                    'blocking' => true,
                    'cta' => $failedMembers->count() > 1
                        ? 'Replace '.$failedMembers->count().' members'
                        : 'Replace member',
                    'next_action' => $failedMembers->count() > 1
                        ? 'Next action: Replace '.$failedMembers->count().' group members'
                        : 'Next action: Replace '.($names[0] ?? 'member'),
                    'detail' => 'Group requires at least '.$min.' eligible members. Only '.$eligible.' remain. Failed: '.implode(', ', $names).'.',
                    'minimum_eligible_members' => $min,
                    'current_eligible_members' => $eligible,
                    'failed_members' => $failedMembers->all(),
                ];

                return $this->result(
                    $application,
                    $participants,
                    $eval,
                    $verified,
                    $settingsVersion,
                    self::ACTION_RESOLVE_MEMBERS,
                    $resolution['detail'],
                    $failingGate,
                    $resolution,
                    verifiedPass: false,
                );
            }

            $resolution = [
                'code' => $failedMembers->count() > 1 ? self::ACTION_RESOLVE_MEMBERS : self::ACTION_CONTINUE_ELIGIBLE,
                'outcome' => 'member_not_eligible',
                'application' => 'may_continue_after_resolution',
                'resolution' => 'replace_group_member',
                'blocking' => true,
                'cta' => $failedMembers->count() === 1 ? 'Replace '.($names[0] ?? 'member') : 'Resolve group members',
                'continue_cta' => 'Continue with '.$eligible.' eligible members',
                'next_action' => $eligible.' of '.$activeCount.' members eligible',
                'detail' => implode(', ', $names).' failed '.($verified ? 'verified' : 'declared').' affordability. This group may continue with '.$eligible.' eligible members after replacement or reconstitution.',
                'minimum_eligible_members' => $min,
                'current_eligible_members' => $eligible,
                'failed_members' => $failedMembers->all(),
                'allow_continue_without_failed' => true,
            ];

            return $this->result(
                $application,
                $participants,
                $eval,
                $verified,
                $settingsVersion,
                self::ACTION_REPLACE_MEMBER,
                $resolution['detail'],
                $failingGate,
                $resolution,
                verifiedPass: false,
            );
        }

        $failedGuarantors = $guarantors->filter(fn ($p) => $this->hardFail($p))->values();
        $required = $this->settings->guarantorRequiredForProduct($application);
        $minGuarantors = $this->settings->minimumAcceptableGuarantors($application);
        $acceptable = $guarantors->filter(fn ($p) => ! $this->hardFail($p))->count();

        if ($required && $failedGuarantors->isNotEmpty() && $acceptable < $minGuarantors) {
            if ($this->settings->guarantorHardFailAction() === self::GUARANTOR_FAIL_REJECT) {
                return $this->result(
                    $application,
                    $participants,
                    $eval,
                    $verified,
                    $settingsVersion,
                    self::ACTION_PENDING_REJECTION,
                    'Required guarantor failed affordability and product policy rejects the application.',
                    $failingGate,
                    verifiedPass: false,
                );
            }

            $gName = (string) ($failedGuarantors->first()['name'] ?? 'Guarantor');
            $resolution = [
                'code' => self::ACTION_REPLACE_GUARANTOR,
                'outcome' => 'participant_not_eligible',
                'application' => 'may_continue_after_resolution',
                'resolution' => 'replace_guarantor',
                'blocking' => true,
                'cta' => 'Replace guarantor',
                'next_action' => 'Next action: Replace guarantor',
                'detail' => $gName.' is not acceptable. Borrower remains eligible. Further screening waits for an acceptable guarantor.',
                'replacement_hours' => $this->settings->guarantorReplacementHours(),
                'failed_guarantors' => $failedGuarantors->all(),
            ];

            return $this->result(
                $application,
                $participants,
                $eval,
                $verified,
                $settingsVersion,
                self::ACTION_REPLACE_GUARANTOR,
                $resolution['detail'],
                $failingGate,
                $resolution,
                verifiedPass: ! $verified || $this->verifiedIncomeComplete($application),
            );
        }

        $verifiedPass = ! $verified || (($eval['verdict'] ?? 'pass') !== 'fail');

        return $this->result(
            $application,
            $participants,
            $eval,
            $verified,
            $settingsVersion,
            self::ACTION_CONTINUE,
            'Eligible to continue screening.',
            null,
            null,
            $verifiedPass,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $participants
     * @param  array<string, mixed>  $eval
     * @param  array<string, mixed>  $settingsVersion
     * @param  array<string, mixed>|null  $resolution
     * @return array<string, mixed>
     */
    private function result(
        LoanApplication $application,
        array $participants,
        array $eval,
        bool $verified,
        array $settingsVersion,
        string $action,
        string $reason,
        ?string $failingGate,
        ?array $resolution = null,
        bool $verifiedPass = true,
    ): array {
        return [
            'application_id' => $application->id,
            'product_id' => $application->loan_product_id,
            'evaluated_at' => now()->toIso8601String(),
            'gate' => $verified ? 'verified' : 'declared',
            'failing_gate' => $failingGate,
            'application_action' => $action,
            'reason' => $reason,
            'participants' => $participants,
            'resolution' => $resolution,
            'verified_pass' => $verifiedPass,
            'settings_version' => $settingsVersion,
            'affordability' => $eval,
            'override_forbidden' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function participantRow(
        string $role,
        ?int $customerId,
        string $name,
        string $gate1,
        ?string $gate2,
        string $reason,
        array $extra = [],
    ): array {
        $g1 = $this->normalizeVerdict($gate1);
        $g2 = $gate2 !== null ? $this->normalizeVerdict($gate2) : null;

        return [
            'participant' => $name,
            'customer_id' => $customerId,
            'role' => $role,
            'gate_1' => $g1,
            'gate_2' => $g2,
            'hard_fail' => $g1 === 'fail' || $g2 === 'fail',
            'reason' => $reason,
            'requested_amount' => $extra['requested_amount'] ?? null,
            'available_capacity' => $extra['available_capacity'] ?? null,
            'income_basis' => $extra['income_basis'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function guarantorParticipants(LoanApplication $application, bool $verified): array
    {
        if (! $this->settings->guarantorGateRequired($verified ? 2 : 1, $application)) {
            return [];
        }

        $links = $application->customerGuarantors ?? collect();
        $rows = [];
        $borrowerEval = $this->affordability->evaluate($application, declaredOnly: ! $verified);
        $emi = (float) ($borrowerEval['proposed_installment'] ?? 0);

        foreach ($links as $link) {
            $invitation = $link->invitation;
            $customerId = (int) ($invitation?->guarantor_customer_id ?? 0);
            $customer = $customerId > 0 ? Customer::query()->find($customerId) : null;
            if (! $customer instanceof Customer) {
                continue;
            }
            $status = (string) ($link->status ?? '');
            if (in_array($status, ['replaced', 'declined', 'cancelled', 'withdrawn', 'rejected'], true)) {
                continue;
            }

            $single = $this->affordability->evaluateForGuarantor(
                $customer,
                $emi,
                $verified ? $application : null,
                $verified ? (int) $link->id : null,
            );

            $rows[] = $this->participantRow(
                role: 'guarantor',
                customerId: $customer->id,
                name: $customer->full_name ?: $link->displayName(),
                gate1: $verified ? 'pass' : (string) ($single['verdict'] ?? 'pass'),
                gate2: $verified ? (string) ($single['verdict'] ?? 'pass') : null,
                reason: (string) ($single['reason'] ?? ''),
                extra: $single,
            );
        }

        return $rows;
    }

    /** @param  array<string, mixed>|null  $participant */
    private function hardFail(?array $participant): bool
    {
        if (! is_array($participant)) {
            return false;
        }

        return ($participant['gate_1'] ?? '') === 'fail' || ($participant['gate_2'] ?? '') === 'fail';
    }

    private function normalizeVerdict(string $verdict): string
    {
        $v = strtolower($verdict);

        return in_array($v, ['pass', 'warn', 'fail'], true) ? $v : 'pass';
    }
}
