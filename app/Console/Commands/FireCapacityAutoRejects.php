<?php

namespace App\Console\Commands;

use App\Services\CapacityAutoRejectService;
use Illuminate\Console\Command;

class FireCapacityAutoRejects extends Command
{
    protected $signature = 'applications:fire-capacity-auto-rejects';

    protected $description = 'Reject applications parked for insufficient repayment capacity once the delay elapses';

    public function handle(CapacityAutoRejectService $service): int
    {
        $fired = $service->fireDue();
        $this->info('Sent '.$fired->count().' capacity auto-rejection(s).');

        return self::SUCCESS;
    }
}
