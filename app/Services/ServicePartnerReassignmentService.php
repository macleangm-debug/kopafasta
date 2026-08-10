<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\PartnerTask;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServicePartnerReassignmentService
{
    public function __construct(
        private readonly PartnerAutoAssignPolicy $policy,
        private readonly PartnerAutoAssignSelector $selector,
        private readonly ValuationPartnerService $valuation,
        private readonly GpsPartnerService $gps,
        private readonly PartnerAssignmentNotifier $notifier,
    ) {}

    /**
     * Reassign overdue open service tasks when reassign_on_sla is enabled.
     *
     * @return array{reassigned: int, skipped: int}
     */
    public function reassignExpired(): array
    {
        $reassigned = 0;
        $skipped = 0;
        $actor = User::query()->whereIn('role', ['admin', 'super_admin'])->orderBy('id')->first();

        foreach (['valuer' => 'asset_valuation', 'gps_installer' => 'gps_install', 'insurance' => CollateralInsurancePartnerService::TASK_TYPE] as $category => $taskType) {
            $settings = $this->policy->forServiceCategory($category);
            if (! ($settings['enabled'] ?? false) || ! ($settings['reassign_on_sla'] ?? false)) {
                continue;
            }

            $tasks = PartnerTask::query()
                ->where('task_type', $taskType)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->orderBy('due_at')
                ->limit(100)
                ->get();

            foreach ($tasks as $task) {
                try {
                    if ($this->reassignTask($task, $category, $actor)) {
                        $reassigned++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::warning('Service partner reassignment failed', [
                        'task_id' => $task->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return compact('reassigned', 'skipped');
    }

    private function reassignTask(PartnerTask $task, string $category, ?User $actor): bool
    {
        $application = $task->loan_application_id
            ? LoanApplication::query()->find($task->loan_application_id)
            : null;

        if (! $application) {
            return false;
        }

        $exclude = [(int) $task->partner_id];
        $replacement = match ($category) {
            'valuer' => $this->selector->pickService('valuer', app(PartnerMatchingService::class)->valuersForRegion($application->customer?->region), $exclude),
            'gps_installer' => $this->selector->pickService('gps_installer', $this->gps->installersForApplication($application), $exclude),
            'insurance' => $this->selector->pickService(
                'insurance',
                app(CollateralInsurancePartnerService::class)->insurersForRegion($application->customer?->region),
                $exclude
            ),
            default => null,
        };

        if (! $replacement) {
            return false;
        }

        return DB::transaction(function () use ($task, $replacement, $category, $application, $actor) {
            $task->update([
                'status' => 'cancelled',
                'notes' => trim(($task->notes ? $task->notes."\n" : '').'Reassigned after SLA expiry.'),
                'completed_at' => now(),
            ]);

            if ($category === 'valuer') {
                ValuationAssignment::query()
                    ->where(function ($q) use ($task) {
                        $q->where('partner_task_id', $task->id);
                    })
                    ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
                    ->update([
                        'status' => ValuationAssignment::STATUS_CANCELLED,
                        'notes' => 'Cancelled for SLA reassignment.',
                        'completed_at' => now(),
                    ]);

                $this->valuation->assign($application, $replacement, $actor, 'Auto-reassigned after valuation SLA expired.');

                return true;
            }

            if ($category === 'gps_installer' && $actor) {
                $this->gps->assign($application, $replacement, $actor, 'Auto-reassigned after GPS SLA expired.');

                return true;
            }

            if ($category === 'insurance') {
                $slaDays = $this->policy->slaDaysForService('insurance');
                $newTask = PartnerTask::query()->create([
                    'partner_id' => $replacement->id,
                    'loan_id' => $task->loan_id,
                    'loan_application_id' => $task->loan_application_id,
                    'task_type' => CollateralInsurancePartnerService::TASK_TYPE,
                    'status' => 'assigned',
                    'due_at' => now()->addDays($slaDays),
                    'customer_name' => $task->customer_name,
                    'customer_phone' => $task->customer_phone,
                    'vehicle_details' => $task->vehicle_details,
                    'location' => $task->location,
                    'instructions' => $task->instructions,
                    'notes' => 'Auto-reassigned after insurance SLA expired. Prior task #'.$task->id,
                    'fee_amount' => $task->fee_amount,
                ]);

                $this->notifier->notifyAssigned($replacement, 'Insurance cover (reassigned)', [
                    'title' => 'Insurance task reassigned to you',
                    'body' => 'Previous partner missed SLA. New due date in '.$slaDays.' day(s).',
                    'action_url' => '/partner/tasks',
                    'staff_url' => route('admin.loan-applications.show', $application),
                ]);

                return (bool) $newTask->id;
            }

            return false;
        });
    }
}
