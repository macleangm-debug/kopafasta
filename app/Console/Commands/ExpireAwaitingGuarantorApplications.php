<?php

namespace App\Console\Commands;

use App\Services\GuarantorDeadlineService;
use Illuminate\Console\Command;

class ExpireAwaitingGuarantorApplications extends Command
{
    protected $signature = 'applications:expire-awaiting-guarantor {--remind : Also send approaching-deadline reminders}';

    protected $description = 'Remind at 7/5/3/1 days then close applications stuck waiting for guarantor profile';

    public function handle(GuarantorDeadlineService $deadlines): int
    {
        if ($this->option('remind')) {
            $reminded = $deadlines->sendReminders();
            $this->info("Sent {$reminded} guarantor deadline reminder(s).");
        }

        $expired = $deadlines->expireStale();
        $this->info('Closed '.$expired->count().' application(s) past the guarantor deadline.');

        return self::SUCCESS;
    }
}
