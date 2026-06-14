<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanFee;
use App\Services\LoanDisbursementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDisbursementJournals extends Command
{
    protected $signature = 'gl:backfill-disbursements {--dry-run : List loans without posting journals}';

    protected $description = 'Post missing disbursement journal entries for already-disbursed loans';

    public function handle(LoanDisbursementService $disbursement): int
    {
        $loans = Loan::query()
            ->whereIn('status', ['active', 'arrears', 'restructuring', 'defaulted', 'closed'])
            ->whereNotNull('disbursement_date')
            ->get();

        $missing = $loans->filter(function (Loan $loan) {
            return ! JournalEntry::query()
                ->where('source_type', Loan::class)
                ->where('source_id', $loan->id)
                ->where('description', 'like', 'Loan disbursement%')
                ->exists();
        });

        if ($missing->isEmpty()) {
            $this->info('All disbursed loans already have disbursement journals.');

            return self::SUCCESS;
        }

        $this->info($missing->count().' loan(s) missing disbursement journals.');

        if ($this->option('dry-run')) {
            foreach ($missing as $loan) {
                $this->line($loan->loan_number.' · '.format_money((float) ($loan->approved_amount ?? $loan->principal_amount)));
            }

            return self::SUCCESS;
        }

        $posted = 0;

        foreach ($missing as $loan) {
            DB::transaction(function () use ($loan, $disbursement, &$posted) {
                $disbursement->backfillJournal($loan);
                $posted++;
            });
        }

        $this->info("Posted {$posted} disbursement journal(s).");

        return self::SUCCESS;
    }
}
