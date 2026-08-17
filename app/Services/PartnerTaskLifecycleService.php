<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\Partner;
use App\Models\PartnerTask;
use App\Models\ValuationAssignment;

class PartnerTaskLifecycleService
{
    public const OPEN_STATUSES = ['assigned', 'in_progress'];

    public const CLOSED_APPLICATION_STATUSES = ['rejected', 'withdrawn', 'expired', 'cancelled'];

    public function applicationIsClosed(?LoanApplication $application): bool
    {
        if (! $application) {
            return false;
        }

        return in_array((string) $application->status, self::CLOSED_APPLICATION_STATUSES, true)
            || in_array((string) $application->current_stage, self::CLOSED_APPLICATION_STATUSES, true);
    }

    public function closeForApplication(LoanApplication $application, string $reason): int
    {
        $closed = 0;

        $tasks = PartnerTask::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->get();

        foreach ($tasks as $task) {
            $this->closeTask($task, $reason);
            $closed++;
        }

        $assignments = ValuationAssignment::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
            ->get();

        foreach ($assignments as $assignment) {
            $this->closeValuationAssignment($assignment, $reason);
            $closed++;
        }

        return $closed;
    }

    public function reconcileOpenTasksOnClosedFiles(): void
    {
        $tasks = PartnerTask::query()
            ->with('loanApplication')
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('loan_application_id')
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($tasks as $task) {
            if ($this->applicationIsClosed($task->loanApplication)) {
                $this->closeTask($task, 'Closed because the loan application is no longer active.');
            }
        }
    }

    public function reconcilePartner(Partner $partner): void
    {
        $tasks = PartnerTask::query()
            ->with('loanApplication')
            ->where('partner_id', $partner->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->get();

        foreach ($tasks as $task) {
            if ($this->applicationIsClosed($task->loanApplication)) {
                $this->closeTask($task, 'Closed because the loan application is no longer active.');
            }
        }

        $assignments = ValuationAssignment::query()
            ->with(['vendorTask', 'application'])
            ->where('partner_id', $partner->id)
            ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
            ->get();

        foreach ($assignments as $assignment) {
            $task = $assignment->vendorTask;
            if ($task && in_array((string) $task->status, ['completed', 'cancelled', 'failed'], true)) {
                $this->closeValuationAssignment(
                    $assignment,
                    $task->status === 'completed'
                        ? 'Aligned with the completed partner task.'
                        : 'Aligned with the closed partner task.',
                    $task->status === 'completed' ? ValuationAssignment::STATUS_COMPLETED : ValuationAssignment::STATUS_CANCELLED,
                );

                continue;
            }

            if ($this->applicationIsClosed($assignment->application)) {
                $this->closeValuationAssignment($assignment, 'Closed because the loan application is no longer active.');
            }
        }
    }

    public function closeTask(PartnerTask $task, string $reason): void
    {
        if (! in_array((string) $task->status, self::OPEN_STATUSES, true)) {
            $this->closeLinkedValuation($task, $reason);

            return;
        }

        $task->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'notes' => trim(($task->notes ? $task->notes."\n" : '').$reason),
        ]);

        $this->closeLinkedValuation($task, $reason);
    }

    public function closeLinkedValuation(PartnerTask $task, string $reason): void
    {
        $assignment = ValuationAssignment::query()
            ->where('partner_task_id', $task->id)
            ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
            ->first();

        if ($assignment) {
            $status = $task->status === 'completed'
                ? ValuationAssignment::STATUS_COMPLETED
                : ValuationAssignment::STATUS_CANCELLED;
            $this->closeValuationAssignment($assignment, $reason, $status);
        }
    }

    public function closeValuationAssignment(
        ValuationAssignment $assignment,
        string $reason,
        string $status = ValuationAssignment::STATUS_CANCELLED,
    ): void {
        if (! in_array($assignment->status, [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS], true)) {
            return;
        }

        $assignment->update([
            'status' => $status,
            'completed_at' => now(),
            'notes' => trim(($assignment->notes ? $assignment->notes."\n" : '').$reason),
        ]);
    }
}
