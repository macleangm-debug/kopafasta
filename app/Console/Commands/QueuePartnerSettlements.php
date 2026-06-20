<?php

namespace App\Console\Commands;

use App\Services\PartnerSettlementService;
use Illuminate\Console\Command;

class QueuePartnerSettlements extends Command
{
    protected $signature = 'partners:queue-weekly-settlements {--dry-run}';

    protected $description = 'Batch approved partner vendor payments into weekly settlement records';

    public function handle(PartnerSettlementService $settlements): int
    {
        if ($this->option('dry-run')) {
            $count = \App\Models\VendorPayment::query()
                ->where('status', 'approved')
                ->whereNull('partner_settlement_id')
                ->distinct('partner_id')
                ->count('partner_id');

            $this->info("Dry run: would create up to {$count} settlement batch(es).");

            return self::SUCCESS;
        }

        $created = $settlements->queueWeeklySettlements();
        $this->info("Partner settlement batches created: {$created}");

        return self::SUCCESS;
    }
}
