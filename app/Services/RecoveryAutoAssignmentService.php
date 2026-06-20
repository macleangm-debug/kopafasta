<?php

namespace App\Services;

use App\Models\ArrearCase;
use App\Models\RecoveryAssignment;
use App\Models\User;

class RecoveryAutoAssignmentService
{
    public function __construct(
        private readonly RecoveryPolicyService $policy,
        private readonly RecoveryPartnerService $partners,
        private readonly RecoveryAssignmentService $assignments,
        private readonly LoanCollectionActionService $collectionActions,
    ) {}

    /** Day past due when call center should be auto-assigned (2 days before grace ends by default). */
    public function callCenterAssignmentDay(): int
    {
        return max(1, $this->policy->gracePeriodDays() - $this->policy->callCenterLeadDays());
    }

    public function maybeAssignCallCenter(ArrearCase $case): ?RecoveryAssignment
    {
        if (! $this->policy->autoAssignCallCenter()) {
            return null;
        }

        if (! in_array($case->status, ['open', 'escalated'], true)) {
            return null;
        }

        if ((int) $case->days_past_due < $this->callCenterAssignmentDay()) {
            return null;
        }

        $existing = RecoveryAssignment::query()
            ->where('arrear_case_id', $case->id)
            ->where('partner_type', 'call_center')
            ->whereNotIn('status', [RecoveryAssignment::STATUS_FAILED])
            ->exists();

        if ($existing) {
            return null;
        }

        $actor = $this->systemActor();
        if (! $actor) {
            return null;
        }

        $vendor = $this->partners
            ->activePartnersForType('call_center')
            ->sortBy(fn ($partner) => RecoveryAssignment::query()
                ->where('partner_id', $partner->id)
                ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
                ->count())
            ->first();

        if (! $vendor) {
            $this->collectionActions->logForCase(
                $case,
                $actor,
                'escalation',
                'Call center auto-assignment due (day '.$case->days_past_due.') but no active call center partner is available.',
                'pending_assignment',
            );

            return null;
        }

        $assignment = $this->assignments->assign(
            $case,
            $vendor,
            'call_center',
            $actor,
            'Auto-assigned call center '.$this->policy->callCenterLeadDays().' day(s) before grace period ends.',
        );

        $this->collectionActions->logForCase(
            $case,
            $actor,
            'recovery_stage_advanced',
            'Call center auto-assigned to '.$vendor->name.' on day '.$case->days_past_due.' past due.',
            'assigned',
        );

        return $assignment;
    }

    private function systemActor(): ?User
    {
        return User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->orderBy('id')
            ->first();
    }
}
