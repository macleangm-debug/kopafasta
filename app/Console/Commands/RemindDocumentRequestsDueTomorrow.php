<?php

namespace App\Console\Commands;

use App\Services\ApplicationDocumentRequestService;
use Illuminate\Console\Command;

class RemindDocumentRequestsDueTomorrow extends Command
{
    protected $signature = 'applications:remind-document-requests-due';

    protected $description = 'Send Screening document-request reminders and close overdue requests';

    public function handle(ApplicationDocumentRequestService $documents): int
    {
        $sent = $documents->sendScheduledReminders();
        $closed = $documents->expireOverdueRequests();
        $this->info("Sent {$sent} document-request reminder(s). Closed {$closed->count()} overdue file(s).");

        return self::SUCCESS;
    }
}
