<?php

namespace App\Console\Commands;

use App\Services\ServicePartnerReassignmentService;
use Illuminate\Console\Command;

class ReassignExpiredServicePartnerSlas extends Command
{
    protected $signature = 'partners:reassign-expired-service-slas';

    protected $description = 'Remind, escalate and reassign overdue valuer / GPS / insurance tasks when SLA rules are enabled';

    public function handle(ServicePartnerReassignmentService $service): int
    {
        $result = $service->processSla();
        $this->info('Reminded: '.$result['reminded'].' · Escalated: '.$result['escalated'].' · Reassigned: '.$result['reassigned'].' · Skipped: '.$result['skipped']);

        return self::SUCCESS;
    }
}
