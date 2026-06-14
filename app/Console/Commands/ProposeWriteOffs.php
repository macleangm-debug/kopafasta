<?php

namespace App\Console\Commands;

use App\Services\WriteOffRequestService;
use Illuminate\Console\Command;

class ProposeWriteOffs extends Command
{
    protected $signature = 'loans:propose-write-offs';

    protected $description = 'Auto-propose recommended write-offs (never executes — approval workflow required)';

    public function handle(WriteOffRequestService $service): int
    {
        $created = $service->proposeFromRules();

        $this->info('Created '.count($created).' write-off proposal(s).');

        return self::SUCCESS;
    }
}
