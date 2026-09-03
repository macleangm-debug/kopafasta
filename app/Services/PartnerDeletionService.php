<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\Partner;
use App\Models\PartnerPayment;
use App\Models\PartnerSettlement;
use App\Models\PartnerTask;
use App\Models\User;
use App\Models\ValuationAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerDeletionService
{
    public const OPEN_TASK_STATUSES = ['assigned', 'in_progress'];

    /**
     * Permanently delete empty partners; otherwise deactivate (status + linked user).
     *
     * @return array{action: 'deleted'|'deactivated', message: string}
     */
    public function remove(Partner $partner, ?User $actor = null): array
    {
        if ($this->hasOperationalHistory($partner)) {
            return $this->deactivate($partner, $actor);
        }

        return $this->hardDelete($partner, $actor);
    }

    /**
     * Cancel assigned / in-progress jobs and offer them to other active partners.
     * Completed valuations, payments, and history are left untouched.
     *
     * @return array{halted_tasks: int, halted_assignments: int, reassigned: int, message: string}
     */
    public function haltOpenWork(Partner $partner, ?User $actor = null): array
    {
        $tasks = $this->openTasks($partner);
        $assignments = $this->openValuationAssignments($partner);

        if ($tasks->isEmpty() && $assignments->isEmpty()) {
            return [
                'halted_tasks' => 0,
                'halted_assignments' => 0,
                'reassigned' => 0,
                'message' => 'No open tasks to halt.',
            ];
        }

        $plan = $this->workPlan($tasks, $assignments);

        DB::transaction(function () use ($tasks, $assignments): void {
            $this->cancelOpenWork($tasks, $assignments, 'Halted by admin.');
        });

        $reassigned = $this->reassignHaltedApplications($plan, $actor, [(int) $partner->id]);

        return [
            'halted_tasks' => $tasks->count(),
            'halted_assignments' => $assignments->count(),
            'reassigned' => $reassigned,
            'message' => $this->haltMessage($tasks->count(), $assignments->count(), $reassigned),
        ];
    }

    /**
     * @return array{action: 'deactivated', message: string}
     */
    public function deactivate(Partner $partner, ?User $actor = null, string $kind = 'admin'): array
    {
        $kind = in_array($kind, ['performance', 'compliance', 'fraud', 'admin'], true) ? $kind : 'admin';
        $tasks = $this->openTasks($partner);
        $assignments = $this->openValuationAssignments($partner);
        $plan = $this->workPlan($tasks, $assignments);

        DB::transaction(function () use ($partner, $tasks, $assignments, $kind) {
            $this->cancelOpenWork($tasks, $assignments, 'Halted because the partner was deactivated.');

            $updates = [
                'status' => 'suspended',
                'suspend_kind' => $kind,
            ];
            if ($kind === 'performance') {
                $updates['performance_status'] = \App\Support\PartnerPerformanceStatus::SUSPENDED;
            }
            if ($kind !== 'performance' && ($partner->isAffiliate() || filled($partner->affiliate_lifecycle_status))) {
                $updates['affiliate_lifecycle_status'] = AffiliateLifecycleService::TERMINATED;
            }

            $partner->update($updates);

            if ($partner->user_id) {
                User::query()->whereKey($partner->user_id)->update(['is_active' => false]);
            }
        });

        app(AuditService::class)->log(
            $actor,
            'partner.'.$kind.'_suspended',
            $partner->fresh(),
            ['status' => 'active'],
            ['status' => 'suspended', 'suspend_kind' => $kind],
        );

        $reassigned = $this->reassignHaltedApplications($plan, $actor, [(int) $partner->id], includeGps: true);

        $message = 'Partner deactivated (history kept). Portal login disabled.';
        if ($tasks->isNotEmpty() || $assignments->isNotEmpty()) {
            $message .= ' '.$this->haltMessage($tasks->count(), $assignments->count(), $reassigned);
        }

        return [
            'action' => 'deactivated',
            'message' => $message,
        ];
    }

    public function restoreAfterPerformance(Partner $partner): void
    {
        if (($partner->suspend_kind ?? '') !== 'performance') {
            return;
        }

        DB::transaction(function () use ($partner): void {
            $partner->update([
                'status' => 'active',
                'suspend_kind' => null,
                'performance_status' => null,
            ]);

            if ($partner->user_id) {
                User::query()->whereKey($partner->user_id)->update(['is_active' => true]);
            }
        });

        app(AuditService::class)->log(
            null,
            'partner.performance_recovered',
            $partner->fresh(),
            ['status' => 'suspended', 'suspend_kind' => 'performance'],
            ['status' => 'active', 'suspend_kind' => null],
        );
    }

    /**
     * @return array{action: 'deleted', message: string}
     */
    public function hardDelete(Partner $partner, ?User $actor = null): array
    {
        if ($this->hasOpenWork($partner)) {
            throw ValidationException::withMessages([
                'partner' => 'This partner has open tasks. Halt them first, or deactivate. Completed jobs stay on file and cannot be deleted.',
            ]);
        }

        if ($this->hasOperationalHistory($partner)) {
            throw ValidationException::withMessages([
                'partner' => 'This partner has tasks, payments, or assignments. Deactivate instead of deleting.',
            ]);
        }

        $userId = $partner->user_id;

        DB::transaction(function () use ($partner, $userId) {
            $partner->delete();

            if ($userId) {
                User::query()->whereKey($userId)->update(['is_active' => false]);
            }
        });

        return [
            'action' => 'deleted',
            'message' => 'Partner deleted.',
        ];
    }

    /** @return Collection<int, PartnerTask> */
    public function openTasks(Partner $partner): Collection
    {
        app(PartnerTaskLifecycleService::class)->reconcilePartner($partner);

        return PartnerTask::query()
            ->where('partner_id', $partner->id)
            ->whereIn('status', self::OPEN_TASK_STATUSES)
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, ValuationAssignment> */
    public function openValuationAssignments(Partner $partner): Collection
    {
        return ValuationAssignment::query()
            ->where('partner_id', $partner->id)
            ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
            ->orderByDesc('id')
            ->get();
    }

    public function hasOpenWork(Partner $partner): bool
    {
        return $this->openTasks($partner)->isNotEmpty()
            || $this->openValuationAssignments($partner)->isNotEmpty();
    }

    public function hasOperationalHistory(Partner $partner): bool
    {
        if (PartnerTask::query()->where('partner_id', $partner->id)->exists()) {
            return true;
        }

        if (PartnerPayment::query()->where('partner_id', $partner->id)->exists()) {
            return true;
        }

        if (PartnerSettlement::query()->where('partner_id', $partner->id)->exists()) {
            return true;
        }

        if (ValuationAssignment::query()->where('partner_id', $partner->id)->exists()) {
            return true;
        }

        if ($partner->affiliateEvents()->exists()) {
            return true;
        }

        if ($partner->marketplaceAssets()->exists()) {
            return true;
        }

        return false;
    }

    /**
     * @param  Collection<int, PartnerTask>  $tasks
     * @param  Collection<int, ValuationAssignment>  $assignments
     */
    private function cancelOpenWork(Collection $tasks, Collection $assignments, string $reason): void
    {
        foreach ($tasks as $task) {
            $task->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'notes' => trim(($task->notes ? $task->notes."\n" : '').$reason),
            ]);
        }

        foreach ($assignments as $assignment) {
            $assignment->update([
                'status' => ValuationAssignment::STATUS_CANCELLED,
                'completed_at' => now(),
                'notes' => trim(($assignment->notes ? $assignment->notes."\n" : '').$reason),
            ]);
        }
    }

    /**
     * @param  Collection<int, PartnerTask>  $tasks
     * @param  Collection<int, ValuationAssignment>  $assignments
     * @return array<int, list<string>>
     */
    private function workPlan(Collection $tasks, Collection $assignments): array
    {
        $plan = [];

        foreach ($tasks as $task) {
            $applicationId = (int) ($task->loan_application_id ?? 0);
            if ($applicationId < 1) {
                continue;
            }
            $plan[$applicationId][] = (string) $task->task_type;
        }

        foreach ($assignments as $assignment) {
            $applicationId = (int) ($assignment->loan_application_id ?? 0);
            if ($applicationId < 1) {
                continue;
            }
            $plan[$applicationId][] = 'asset_valuation';
        }

        return $plan;
    }

    /**
     * @param  array<int, list<string>>  $plan
     * @param  list<int>  $excludeIds
     */
    private function reassignHaltedApplications(array $plan, ?User $actor, array $excludeIds, bool $includeGps = false): int
    {
        $reassigned = 0;

        foreach ($plan as $applicationId => $types) {
            $application = LoanApplication::query()->find($applicationId);
            if (! $application) {
                continue;
            }

            $types = array_values(array_unique($types));

            if (in_array('asset_valuation', $types, true)) {
                $placed = app(ValuationPartnerService::class)->autoAssignIfPossible(
                    $application,
                    $actor,
                    $excludeIds,
                    'Auto-assigned after the previous valuer was halted.',
                );
                if ($placed) {
                    $reassigned++;
                }
            }

            if ($includeGps && in_array('gps_install', $types, true)) {
                $placed = app(GpsPartnerService::class)->autoAssignIfPossible($application, $actor);
                if ($placed) {
                    $reassigned++;
                }
            }
        }

        return $reassigned;
    }

    private function haltMessage(int $tasks, int $assignments, int $reassigned): string
    {
        $open = max($tasks, $assignments);

        return $open.' open job(s) cancelled'
            .($reassigned > 0 ? ', '.$reassigned.' reassigned to another partner.' : '. Waiting for another eligible partner.');
    }
}
