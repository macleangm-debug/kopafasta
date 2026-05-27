<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\MembershipService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Daily job: send membership-expiry reminders and transition statuses
 * (active → expiring → grace → expired).
 */
class SendMembershipReminders extends Command
{
    /** @var array<int> Days-before-expiry milestones at which to remind. */
    public const MILESTONES = [30, 14, 7, 1];

    protected $signature = 'membership:send-reminders {--dry-run}';
    protected $description = 'Send membership expiry reminders and update membership statuses';

    public function handle(NotificationService $notifier, MembershipService $membership): int
    {
        $dry   = (bool) $this->option('dry-run');
        $today = CarbonImmutable::today();
        $sent  = 0;
        $statusUpdates = 0;

        // 1) Active members within milestone windows — send reminders.
        foreach (self::MILESTONES as $days) {
            $rows = Customer::query()
                ->whereNotNull('membership_expires_at')
                ->whereDate('membership_expires_at', $today->addDays($days)->toDateString())
                ->get();

            foreach ($rows as $customer) {
                if ($dry) {
                    $this->line("  [dry] would remind {$customer->member_no} ({$days}d) -> {$customer->phone}");
                    continue;
                }
                if (! $membership->recordReminder($customer, (string) $days)) {
                    continue; // already sent this milestone
                }
                $this->sendReminder($notifier, $customer, $days);
                $sent++;
            }
        }

        // 2) Transition customers whose expiry has just passed into grace.
        $graceDays = (int) (MembershipService::config()['grace_period_days'] ?? 0);
        $justExpired = Customer::query()
            ->whereNotNull('membership_expires_at')
            ->whereDate('membership_expires_at', '<',  $today->toDateString())
            ->whereDate('membership_expires_at', '>=', $today->subDays($graceDays)->toDateString())
            ->where(function ($q): void {
                $q->whereNull('membership_status')->orWhereNotIn('membership_status', ['grace','expired','archived']);
            })
            ->get();

        foreach ($justExpired as $customer) {
            if ($dry) { $this->line("  [dry] would move {$customer->member_no} to GRACE"); continue; }
            $membership->markGrace($customer);
            $statusUpdates++;
        }

        // 3) Past grace -> expired.
        $expired = Customer::query()
            ->whereNotNull('membership_expires_at')
            ->whereDate('membership_expires_at', '<', $today->subDays($graceDays)->toDateString())
            ->where(function ($q): void {
                $q->whereNull('membership_status')->orWhereNotIn('membership_status', ['expired','archived']);
            })
            ->get();

        foreach ($expired as $customer) {
            if ($dry) { $this->line("  [dry] would mark {$customer->member_no} EXPIRED"); continue; }
            $membership->markExpired($customer);
            $statusUpdates++;
        }

        $this->info("Membership reminders sent: {$sent}; status transitions: {$statusUpdates}");
        return self::SUCCESS;
    }

    private function sendReminder(NotificationService $notifier, Customer $customer, int $days): void
    {
        $name   = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        $member = $customer->member_no ?? '';
        $expiry = optional($customer->membership_expires_at)->format('d M Y');

        $urgency = match (true) {
            $days <= 1 => 'FINAL REMINDER',
            $days <= 7 => 'URGENT',
            default    => 'Reminder',
        };

        $body = "{$urgency}: Habari {$name}, uanachama wako wa KopaFasta ({$member}) utaisha tarehe {$expiry} (siku {$days}). Lipa ada ya renewal ili kuendelea kupata huduma.";

        // Try template-driven flow first; falls back to plain SMS/email if template missing.
        $notifier->notifyCustomer($customer, 'membership_expiry_'.$days, [
            'name' => $name,
            'member_no' => $member,
            'expires_at' => $expiry,
            'days_remaining' => $days,
            '_fallback_body' => $body,
            '_fallback_subject' => 'KopaFasta membership expires in '.$days.' day(s)',
        ]);
    }
}
