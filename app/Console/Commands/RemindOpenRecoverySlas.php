<?php

namespace App\Console\Commands;

use App\Services\RecoverySlaReminderService;
use Illuminate\Console\Command;

class RemindOpenRecoverySlas extends Command
{
    protected $signature = 'recovery:remind-open-slas';

    protected $description = 'Send automated Recovery SLA reminders from sla_due_at and Recovery Policy remind_days';

    public function handle(RecoverySlaReminderService $reminders): int
    {
        $sent = $reminders->sendDueReminders();
        $this->info("Sent {$sent} recovery SLA reminder(s).");

        return self::SUCCESS;
    }
}
