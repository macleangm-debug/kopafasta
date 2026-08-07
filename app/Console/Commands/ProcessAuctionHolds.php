<?php

namespace App\Console\Commands;

use App\Services\AuctionHoldService;
use Illuminate\Console\Command;

class ProcessAuctionHolds extends Command
{
    protected $signature = 'recovery:process-auction-holds {--dry-run : Count eligible cases without assigning}';

    protected $description = 'After repossession hold ends, auto-assign auctioneer partners';

    public function handle(AuctionHoldService $auctions): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $auctions->processEligibleAuctions($dryRun);

        if ($dryRun) {
            $this->info("Found {$result['assigned']} case(s) eligible for auction assignment (dry run).");

            return self::SUCCESS;
        }

        $this->info("Assigned auctioneer for {$result['assigned']} case(s). Skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
