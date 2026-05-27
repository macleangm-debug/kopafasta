<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\RepaymentScheduleGenerator;
use Illuminate\Console\Command;

class BackfillRepaymentSchedules extends Command
{
    protected $signature = 'loans:backfill-schedules
                            {--force : Rebuild schedules even when they already exist}
                            {--method=reducing : Amortisation method (reducing|flat)}';

    protected $description = 'Generate a repayment schedule for every active loan that does not yet have one';

    public function handle(RepaymentScheduleGenerator $generator): int
    {
        $force  = (bool) $this->option('force');
        $method = $this->option('method') ?: 'reducing';

        $query = Loan::query()
            ->whereNotNull('disbursement_date')
            ->whereNotIn('status', ['pending', 'cancelled', 'rejected']);

        $total = $query->count();
        $built = 0;
        $skipped = 0;

        $this->info("Found {$total} loan(s) to consider · method={$method} · force=".($force ? 'yes' : 'no'));

        $query->orderBy('id')->chunk(100, function ($loans) use ($generator, $force, $method, &$built, &$skipped) {
            foreach ($loans as $loan) {
                $before = $loan->repaymentSchedules()->count();
                $count  = $generator->generate($loan, $force, $method);
                if ($count > 0 && ($force || $before === 0)) {
                    $built++;
                    $this->line("  loan #{$loan->id} ({$loan->loan_number}) · {$count} installments");
                } else {
                    $skipped++;
                }
            }
        });

        $this->newLine();
        $this->info("Done · built {$built} · skipped {$skipped} (already had a schedule)");
        return self::SUCCESS;
    }
}
