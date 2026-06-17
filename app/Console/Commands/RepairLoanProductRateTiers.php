<?php

namespace App\Console\Commands;

use App\Services\LoanRateTierRepairService;
use Illuminate\Console\Command;

class RepairLoanProductRateTiers extends Command
{
    protected $signature = 'loan-products:repair-rate-tiers {--dry-run : Preview fixes without saving}';

    protected $description = 'Normalize loan product rate tiers stored as percents (e.g. 19 or 70.9 instead of 0.19 / 0.709)';

    public function handle(LoanRateTierRepairService $repair): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $corrupt = $repair->corruptTiers();

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Found {$corrupt->count()} tier(s) needing repair.");

        foreach ($corrupt->take(10) as $tier) {
            $product = $tier->product;
            $this->line(sprintf(
                '  product %s tier #%d: monthly=%s bot=%s',
                $product?->code ?? $tier->loan_product_id,
                $tier->id,
                $tier->monthly_rate,
                $tier->bot_regulated_rate,
            ));
        }

        if ($corrupt->count() > 10) {
            $this->line('  … and '.($corrupt->count() - 10).' more');
        }

        $result = $repair->repairAll($dryRun);

        $this->info(sprintf(
            'Tiers fixed: %d · Products interest_rate normalized: %d · Skipped: %d',
            $result['tiers_fixed'],
            $result['products_updated'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
