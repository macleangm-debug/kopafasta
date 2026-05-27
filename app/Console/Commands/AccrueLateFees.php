<?php

namespace App\Console\Commands;

use App\Services\LateFeeAccrualService;
use Illuminate\Console\Command;

class AccrueLateFees extends Command
{
    protected $signature = 'loans:accrue-late-fees {--date= : ISO date (defaults to today)}';
    protected $description = 'Accrue late-payment fees for overdue loans and post General Ledger entries.';

    public function handle(LateFeeAccrualService $svc): int
    {
        $asOf = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now();
        $this->info('Accruing late fees as of ' . $asOf->toDateString() . '…');
        $stats = $svc->accrue($asOf);
        $this->line(json_encode($stats, JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
