<?php

namespace App\Console\Commands;

use App\Services\ApplicationDocumentRequestService;
use Illuminate\Console\Command;

class RemindDocumentRequestsDueTomorrow extends Command
{
    protected $signature = 'applications:remind-document-requests-due';

    protected $description = 'Send in-app reminders for open document requests due tomorrow';

    public function handle(ApplicationDocumentRequestService $documents): int
    {
        $sent = $documents->sendDueTomorrowReminders();
        $this->info("Sent {$sent} document-request reminder(s).");

        return self::SUCCESS;
    }
}
