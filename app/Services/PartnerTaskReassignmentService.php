<?php

namespace App\Services;

use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PartnerTaskReassignmentService
{
    public const ORIGINATION_TYPES = ['asset_valuation', 'gps_install', 'vehicle_insurance'];

    public const RECOVERY_TYPES = [
        'collection_call',
        'field_visit',
        'repossession',
        'auction',
        'legal_notice',
        'gps_removal',
        'collection',
    ];

    public function isOpen(PartnerTask $task): bool
    {
        return in_array($task->status, ['assigned', 'in_progress'], true);
    }

    public function isOrigination(PartnerTask $task): bool
    {
        return in_array((string) $task->task_type, self::ORIGINATION_TYPES, true);
    }

    public function isRecovery(PartnerTask $task): bool
    {
        return in_array((string) $task->task_type, self::RECOVERY_TYPES, true);
    }

    public function can(User $user, PartnerTask $task): bool
    {
        if (! $this->isOpen($task)) {
            return false;
        }

        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            return $this->isOrigination($task) || $this->isRecovery($task);
        }

        if ($this->isOrigination($task)) {
            return $user->hasPermission('applications.review');
        }

        if ($this->isRecovery($task)) {
            return $user->role === 'manager';
        }

        return false;
    }

    /** @return Collection<int, Vendor> */
    public function candidates(PartnerTask $task): Collection
    {
        if ($this->isOrigination($task)) {
            return app(ServicePartnerReassignmentService::class)->candidatesFor($task);
        }

        if ($this->isRecovery($task)) {
            $assignment = $this->recoveryAssignment($task);
            if (! $assignment) {
                return collect();
            }

            $region = $assignment->arrearCase?->loan?->customer?->region
                ?? $assignment->loan?->customer?->region
                ?? null;

            return app(RecoveryPartnerService::class)
                ->activePartnersForTypeInRegion((string) $assignment->partner_type, $region)
                ->reject(fn (Vendor $vendor) => (int) $vendor->id === (int) $task->partner_id)
                ->values();
        }

        return collect();
    }

    public function reassign(PartnerTask $task, User $actor, ?int $toPartnerId, string $reason = 'Reassigned by staff.'): PartnerTask
    {
        if (! $this->can($actor, $task)) {
            throw ValidationException::withMessages([
                'task' => 'You cannot reassign this task.',
            ]);
        }

        $candidates = $this->candidates($task);
        $replacement = $toPartnerId
            ? $candidates->first(fn (Vendor $vendor) => (int) $vendor->id === $toPartnerId)
            : $candidates->first();

        if (! $replacement instanceof Vendor) {
            throw ValidationException::withMessages([
                'partner_id' => 'No other eligible partner covers this region. Add coverage or assign nationwide.',
            ]);
        }

        if ($this->isOrigination($task)) {
            $category = app(ServicePartnerReassignmentService::class)->categoryFor($task);
            $ok = $category && app(ServicePartnerReassignmentService::class)
                ->reassignTask($task, $category, $actor, $replacement, $reason);

            if (! $ok) {
                throw ValidationException::withMessages([
                    'task' => 'Could not reassign this origination task.',
                ]);
            }

            return $task->fresh();
        }

        $assignment = $this->recoveryAssignment($task);
        if (! $assignment) {
            throw ValidationException::withMessages([
                'task' => 'This recovery task is not linked to a collection assignment.',
            ]);
        }

        app(RecoveryAssignmentService::class)->reassignTo($assignment, $replacement, $actor, $reason);

        return $task->fresh();
    }

    private function recoveryAssignment(PartnerTask $task): ?RecoveryAssignment
    {
        return RecoveryAssignment::query()
            ->with(['arrearCase.loan.customer'])
            ->where('partner_task_id', $task->id)
            ->first();
    }
}
