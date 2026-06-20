<?php

namespace App\Services;

use App\Models\RecoveryAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecoveryEscalationService
{
    public function __construct(
        private readonly RecoveryAssignmentService $assignments,
        private readonly RecoveryPartnerService $partners,
        private readonly RecoveryPolicyService $policy,
        private readonly LoanCollectionActionService $collectionActions,
    ) {}

    public function nextPartnerType(string $currentType): ?string
    {
        $chain = config('recovery.escalation_chain', []);
        $index = array_search($currentType, $chain, true);

        if ($index === false) {
            return null;
        }

        return $chain[$index + 1] ?? null;
    }

    /**
     * After a partner SLA breach, assign the next recovery stage when possible.
     */
    public function advanceAfterEscalation(RecoveryAssignment $assignment, User $actor): ?RecoveryAssignment
    {
        $assignment->loadMissing('arrearCase');
        $arrearCase = $assignment->arrearCase;

        if (! $arrearCase) {
            return null;
        }

        $nextType = $this->nextPartnerType($assignment->partner_type);
        if (! $nextType) {
            return null;
        }

        $alreadyOpen = RecoveryAssignment::query()
            ->where('arrear_case_id', $arrearCase->id)
            ->where('partner_type', $nextType)
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
            ->exists();

        if ($alreadyOpen) {
            return null;
        }

        $vendor = $this->partners
            ->activePartnersForType($nextType)
            ->sortBy(fn ($partner) => RecoveryAssignment::query()
                ->where('partner_id', $partner->id)
                ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
                ->count())
            ->first();

        $nextLabel = $this->policy->partnerTypeLabel($nextType);

        if (! $vendor) {
            $this->collectionActions->logForCase(
                $arrearCase,
                $actor,
                'escalation',
                'Recovery stage advanced to '.$nextLabel.' but no active partner is available — manual assignment required.',
                'pending_assignment',
            );

            return null;
        }

        return DB::transaction(function () use ($arrearCase, $vendor, $nextType, $actor, $assignment, $nextLabel) {
            $newAssignment = $this->assignments->assign(
                $arrearCase,
                $vendor,
                $nextType,
                $actor,
                'Auto-assigned after '.$this->policy->partnerTypeLabel($assignment->partner_type).' SLA expired.',
            );

            $this->collectionActions->logForCase(
                $arrearCase,
                $actor,
                'recovery_stage_advanced',
                'Advanced to '.$nextLabel.' · assigned to '.$vendor->name.'.',
                'assigned',
            );

            return $newAssignment;
        });
    }

    /** @return array{escalated: int, advanced: int} */
    public function processExpiredSlas(bool $dryRun = false): array
    {
        if (! $this->policy->autoEscalate()) {
            return ['escalated' => 0, 'advanced' => 0];
        }

        $actor = User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->orderBy('id')
            ->first();

        if (! $actor) {
            return ['escalated' => 0, 'advanced' => 0];
        }

        $breaches = RecoveryAssignment::query()
            ->with(['arrearCase', 'vendorTask'])
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->orderBy('sla_due_at')
            ->get();

        if ($dryRun) {
            return ['escalated' => $breaches->count(), 'advanced' => 0];
        }

        $escalated = 0;
        $advanced = 0;

        foreach ($breaches as $assignment) {
            $this->assignments->escalate(
                $assignment,
                $actor,
                'Auto-escalated: partner SLA expired on '.$assignment->sla_due_at?->format('d M Y H:i').'.',
            );
            $escalated++;

            if ($this->advanceAfterEscalation($assignment->fresh(['arrearCase']), $actor)) {
                $advanced++;
            }
        }

        return compact('escalated', 'advanced');
    }
}
