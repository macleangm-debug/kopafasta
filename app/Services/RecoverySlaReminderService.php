<?php

namespace App\Services;

use App\Models\RecoveryAssignment;

class RecoverySlaReminderService
{
    public function __construct(
        private readonly RecoveryPolicyService $policy,
        private readonly PartnerAssignmentNotifier $notifier,
    ) {}

    public function sendDueReminders(): int
    {
        $count = 0;
        $open = RecoveryAssignment::query()
            ->with('vendor')
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '>', now())
            ->orderBy('sla_due_at')
            ->limit(300)
            ->get();

        foreach ($open as $assignment) {
            $marks = $this->policy->remindDaysForType((string) $assignment->partner_type);
            if ($marks === []) {
                continue;
            }

            $daysLeft = now()->startOfDay()->diffInDays($assignment->sla_due_at->copy()->startOfDay(), false);
            $sent = is_array($assignment->sla_reminder_meta) ? $assignment->sla_reminder_meta : [];
            $partner = $assignment->vendor;
            if (! $partner) {
                continue;
            }

            foreach ($marks as $mark) {
                if ($daysLeft > $mark || isset($sent[(string) $mark])) {
                    continue;
                }

                $locale = app(PartnerTermsService::class)->partnerLocale($partner);
                $sla = $assignment->sla_due_at->format('d M Y');
                $this->notifier->notifyAssigned($partner, $this->policy->partnerTypeLabel((string) $assignment->partner_type).' recovery reminder', [
                    'title' => trans('partner_governance.recovery_reminder_title', [], $locale),
                    'body' => trans('partner_governance.recovery_reminder_body', [
                        'days' => $mark,
                        'sla' => $sla,
                    ], $locale),
                    'action_url' => '/partner/recovery',
                    'staff_permission' => 'partners.manage',
                    'staff_url' => route('admin.recovery.assignments.show', $assignment),
                ]);
                $sent[(string) $mark] = now()->toIso8601String();
                $assignment->update(['sla_reminder_meta' => $sent]);
                $count++;
                break;
            }
        }

        return $count;
    }
}
