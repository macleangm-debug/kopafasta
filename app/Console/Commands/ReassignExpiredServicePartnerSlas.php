<?php

namespace App\Console\Commands;

use App\Services\ServicePartnerReassignmentService;
use Illuminate\Console\Command;

class ReassignExpiredServicePartnerSlas extends Command
{
    protected $signature = 'partners:reassign-expired-service-slas';

    protected $description = 'Reassign overdue valuer / GPS / insurance tasks when auto-assign reassign-on-SLA is enabled';

    public function handle(ServicePartnerReassignmentService $service): int
    {
        $result = $service->reassignExpired();
        $this->info('Reassigned: '.$result['reassigned'].' · Skipped: '.$result['skipped']);

        return self::SUCCESS;
    }
}
