<?php

namespace App\Console\Commands;

use App\Services\Plus\PlusReportService;
use Illuminate\Console\Command;

class GeneratePlusMonthlyReports extends Command
{
    protected $signature = 'plus:generate-monthly-reports';

    protected $description = 'Snapshot last month’s Plus progress reports and notify members when ready.';

    public function handle(PlusReportService $reports): int
    {
        $count = $reports->generateDueReports();
        $this->info("Processed {$count} Plus monthly reports.");

        return self::SUCCESS;
    }
}
