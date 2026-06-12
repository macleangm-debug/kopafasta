<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Services\GuarantorNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkOverdueInstallments extends Command
{
    protected $signature = 'loans:mark-overdue';

    protected $description = 'Flip pending repayment installments whose due_date is in the past to status=overdue, and roll the parent loan into arrears';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        $changed = RepaymentSchedule::whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', $today)
            ->update(['status' => 'overdue']);

        $loanIds = Loan::query()
            ->whereIn('status', ['active', 'disbursed'])
            ->whereHas('repaymentSchedules', fn ($q) => $q->where('status', 'overdue'))
            ->pluck('id');

        $loansInArrears = Loan::query()
            ->whereIn('id', $loanIds)
            ->whereIn('status', ['active', 'disbursed'])
            ->update(['status' => 'arrears']);

        if ($loansInArrears > 0) {
            $notifier = app(GuarantorNotificationService::class);
            Loan::query()
                ->whereIn('id', $loanIds)
                ->where('status', 'arrears')
                ->with('application.customer')
                ->each(fn (Loan $loan) => $notifier->notifyLoanArrears($loan));
        }

        $this->info("Marked {$changed} installment(s) overdue · moved {$loansInArrears} loan(s) into arrears.");
        return self::SUCCESS;
    }
}
