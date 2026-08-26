<?php

namespace App\Console\Commands;

use App\Services\Marketing\MarketingDemoService;
use Illuminate\Console\Command;

class ExpireMarketingDemosCommand extends Command
{
    protected $signature = 'marketing:expire-demos';

    protected $description = 'Archive expired isolated marketing demo sessions.';

    public function handle(MarketingDemoService $demos): int
    {
        $count = $demos->expireOverdue();
        $this->info("Expired {$count} marketing demo session(s).");

        return self::SUCCESS;
    }
}
