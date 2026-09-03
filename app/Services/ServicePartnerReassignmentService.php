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
     * Remind approaching SLAs, escalate breaches, then reassign after grace.
     *
     * @return array{reassigned: int, skipped: int, reminded: int, escalated: int}
     */
    public function processSla(): array
    {
        $reminded = $this->sendReminders();
        $escalated = $this->notifyBreaches();
        $result = $this->reassignExpired();

        return [
            'reassigned' => $result['reassigned'],
            'skipped' => $result['skipped'],
            'reminded' => $reminded,
            'escalated' => $escalated,
        ];
    }

    public function sendReminders(): int
    {
        $count = 0;
        foreach (['valuer' => 'asset_valuation', 'gps_installer' => 'gps_install', 'insurance' => CollateralInsurancePartnerService::TASK_TYPE] as $category => $taskType) {
            $settings = $this->policy->forServiceCategory($category);
            if (! ($settings['enabled'] ?? false)) {
                continue;
            }
            $marks = $this->policy->remindHoursForService($category);
            if ($marks === []) {
                continue;
            }

            $tasks = PartnerTask::query()
                ->with('partner')
                ->where('task_type', $taskType)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->whereNotNull('due_at')
                ->where('due_at', '>', now())
                ->orderBy('due_at')
                ->limit(200)
                ->get();

            foreach ($tasks as $task) {
                $hoursLeft = now()->diffInHours($task->due_at, false);
                $sent = (array) ($task->notesMeta()['sla_reminders'] ?? []);
                foreach ($marks as $mark) {
                    if ($hoursLeft > $mark || isset($sent[(string) $mark])) {
                        continue;
                    }
                    $locale = app(PartnerTermsService::class)->partnerLocale($task->partner);
                    $this->notifier->notifyAssigned($task->partner, $task->task_type, [
                        'title' => trans('partner_governance.task_due_title', [], $locale),
                        'body' => trans('partner_governance.task_due_body', [
                            'hours' => $mark,
                        ], $locale),
                        'action_url' => '/partner/tasks/'.$task->id,
                    ]);
                    $sent[(string) $mark] = now()->toIso8601String();
                    $task->mergeNotesMeta(['sla_reminders' => $sent]);
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    public function notifyBreaches(): int
    {
        $count = 0;
        foreach (['valuer' => 'asset_valuation', 'gps_installer' => 'gps_install', 'insurance' => CollateralInsurancePartnerService::TASK_TYPE] as $category => $taskType) {
            $tasks = PartnerTask::query()
                ->with('partner')
                ->where('task_type', $taskType)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->orderBy('due_at')
                ->limit(100)
                ->get();

            foreach ($tasks as $task) {
                $meta = $task->notesMeta();
                if (! empty($meta['sla_breached_at'])) {
                    continue;
                }
                $locale = $task->partner
                    ? app(PartnerTermsService::class)->partnerLocale($task->partner)
                    : app()->getLocale();
                $this->notifier->notifyStaff(
                    trans('partner_governance.sla_breached_title', ['type' => $task->task_type, 'id' => $task->id], $locale),
                    trans('partner_governance.sla_breached_body', [
                        'partner' => $task->partner?->name ?? 'Partner',
                    ], $locale),
                    '/partner/tasks/'.$task->id,
                );
                $task->mergeNotesMeta(['sla_breached_at' => now()->toIso8601String()]);
                $count++;
            }
        }

        return $count;
    }

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
            if ($this->policy->reassignModeForService($category) !== 'auto') {
                continue;
            }

            $grace = $this->policy->graceHoursForService($category);
            $cutoff = now()->subHours($grace);

            $tasks = PartnerTask::query()
                ->where('task_type', $taskType)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', $cutoff)
                ->orderBy('due_at')
                ->limit(100)
                ->get();

            foreach ($tasks as $task) {
                try {
                    $rounds = (int) ($task->notesMeta()['reassignment_count'] ?? 0);
                    if ($rounds >= $this->policy->maxReassignmentsForService($category)) {
                        $this->notifier->notifyStaff(
                            'Needs reassignment: '.$task->task_type.' #'.$task->id,
                            'Maximum automatic reassignments reached. Operations must pick a partner.',
                            '/admin/partners/tasks',
                        );
                        $skipped++;
                        continue;
                    }
                    if ($this->reassignTask($task, $category, $actor, reason: 'Reassigned after SLA expiry.')) {
                        $reassigned++;
                    } else {
                        $this->notifier->notifyStaff(
                            'Needs reassignment: '.$task->task_type.' #'.$task->id,
                            'No eligible replacement partner. Task stays with the current partner until staff reassigns.',
                            '/admin/partners/tasks',
                        );
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

    /**
     * @return \Illuminate\Support\Collection<int, Vendor>
     */
    public function candidatesFor(PartnerTask $task): \Illuminate\Support\Collection
    {
        $category = $this->categoryFor($task);
        $application = $task->loan_application_id
            ? LoanApplication::query()->with('customer')->find($task->loan_application_id)
            : null;

        if (! $category || ! $application) {
            return collect();
        }

        $exclude = [(int) $task->partner_id];

        $pool = match ($category) {
            'valuer' => app(PartnerMatchingService::class)->valuersForRegion($application->customer?->region),
            'gps_installer' => $this->gps->installersForApplication($application),
            'insurance' => app(CollateralInsurancePartnerService::class)->insurersForRegion($application->customer?->region),
            default => collect(),
        };

        return $pool
            ->reject(fn (Vendor $vendor) => in_array((int) $vendor->id, $exclude, true))
            ->values();
    }

    /**
     * Staff pick list: every active partner of that type, not only the region pool.
     *
     * @return \Illuminate\Support\Collection<int, Vendor>
     */
    public function manualCandidatesFor(PartnerTask $task): \Illuminate\Support\Collection
    {
        $category = $this->categoryFor($task);
        if (! $category) {
            return collect();
        }

        return $this->allActiveForCategory($category)
            ->reject(fn (Vendor $vendor) => (int) $vendor->id === (int) $task->partner_id)
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Vendor>
     */
    public function allActiveForCategory(string $category): \Illuminate\Support\Collection
    {
        return Vendor::query()
            ->where('status', 'active')
            ->where(function ($q) use ($category): void {
                $q->where('category', $category)->orWhere('roles', 'like', '%"'.$category.'"%');
            })
            ->orderBy('name')
            ->get();
    }

    public function categoryFor(PartnerTask $task): ?string
    {
        return match ((string) $task->task_type) {
            'asset_valuation' => 'valuer',
            'gps_install' => 'gps_installer',
            CollateralInsurancePartnerService::TASK_TYPE => 'insurance',
            default => null,
        };
    }

    public function reassignTask(
        PartnerTask $task,
        string $category,
        ?User $actor,
        ?Vendor $replacement = null,
        string $reason = 'Reassigned after SLA expiry.',
    ): bool {
        $application = $task->loan_application_id
            ? LoanApplication::query()->find($task->loan_application_id)
            : null;

        if (! $application) {
            return false;
        }

        $exclude = [(int) $task->partner_id];
        $replacement ??= match ($category) {
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

        return DB::transaction(function () use ($task, $replacement, $category, $application, $actor, $reason) {
            $previous = $task->partner;
            $documents = $task->documents()->get();
            $rounds = (int) ($task->notesMeta()['reassignment_count'] ?? 0) + 1;
            $task->mergeNotesMeta([
                'reassigned_reason' => $reason,
                'reassigned_at' => now()->toIso8601String(),
            ]);
            $task->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

            $newTask = null;

            if ($category === 'valuer') {
                ValuationAssignment::query()
                    ->where(function ($q) use ($task) {
                        $q->where('partner_task_id', $task->id);
                    })
                    ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
                    ->update([
                        'status' => ValuationAssignment::STATUS_CANCELLED,
                        'notes' => $reason,
                        'completed_at' => now(),
                    ]);

                $assignment = $this->valuation->assign($application, $replacement, $actor, $reason);
                $newTask = $assignment->vendorTask
                    ?? PartnerTask::query()
                        ->where('loan_application_id', $application->id)
                        ->where('partner_id', $replacement->id)
                        ->where('task_type', 'asset_valuation')
                        ->latest('id')
                        ->first();
            } elseif ($category === 'gps_installer' && $actor) {
                $newTask = $this->gps->assign($application, $replacement, $actor, $reason);
            } elseif ($category === 'insurance') {
                $slaHours = $this->policy->slaHoursForService('insurance');
                $newTask = PartnerTask::query()->create([
                    'partner_id' => $replacement->id,
                    'loan_id' => $task->loan_id,
                    'loan_application_id' => $task->loan_application_id,
                    'task_type' => CollateralInsurancePartnerService::TASK_TYPE,
                    'status' => 'assigned',
                    'due_at' => now()->addHours($slaHours),
                    'customer_name' => $task->customer_name,
                    'customer_phone' => $task->customer_phone,
                    'vehicle_details' => $task->vehicle_details,
                    'location' => $task->location,
                    'instructions' => $task->instructions,
                    'fee_amount' => $task->fee_amount,
                ]);

                $this->notifier->notifyAssigned($replacement, 'Insurance cover (reassigned)', [
                    'title' => 'New insurance task',
                    'body' => $reason,
                    'action_url' => '/partner/tasks/'.$newTask->id,
                    'staff_url' => route('admin.loan-applications.show', $application),
                ]);
            }

            if (! $newTask) {
                return false;
            }

            $newTask->mergeNotesMeta([
                'reassignment_count' => $rounds,
                'prior_task_id' => $task->id,
                'message' => $reason.' Prior task #'.$task->id,
            ]);
            $this->copyEvidence($documents, $newTask);

            if ($previous) {
                $this->notifier->notifyAssigned($previous, $task->task_type, [
                    'title' => 'Task reassigned',
                    'body' => 'Task #'.$task->id.' has been reassigned. No further action is required.',
                    'action_url' => '/partner/tasks/'.$task->id,
                ]);
            }

            return true;
        });
    }

    public function declineTask(PartnerTask $task, string $reasonCode, ?string $detail = null): bool
    {
        $category = $this->categoryFor($task);
        if (! $category || ! $task->isWritable()) {
            return false;
        }

        $labels = [
            'too_far' => 'Too far away',
            'unavailable' => 'Not available',
            'conflict' => 'Conflict',
            'other' => 'Other',
        ];
        $label = $labels[$reasonCode] ?? $reasonCode;
        $reason = 'Partner declined: '.$label.($detail ? ' — '.$detail : '');
        $actor = User::query()->whereIn('role', ['admin', 'super_admin'])->orderBy('id')->first();

        return $this->reassignTask($task, $category, $actor, reason: $reason);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\PartnerDocument>  $documents
     */
    private function copyEvidence($documents, PartnerTask $to): void
    {
        foreach ($documents as $doc) {
            $payload = [
                'partner_id' => $to->partner_id,
                'partner_task_id' => $to->id,
                'label' => trim((string) ($doc->label ?? 'Evidence')).' (prior #'.$doc->id.')',
                'file_path' => $doc->file_path ?? $doc->path ?? null,
                'mime' => $doc->mime,
                'size_bytes' => $doc->size_bytes,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('partner_documents', 'doc_type')
                || \Illuminate\Support\Facades\Schema::hasColumn('vendor_documents', 'doc_type')) {
                $payload['doc_type'] = $doc->doc_type;
            }
            try {
                \App\Models\PartnerDocument::query()->create($payload);
            } catch (\Throwable $e) {
                Log::warning('Could not copy partner evidence on reassignment', [
                    'from' => $doc->id,
                    'to' => $to->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
