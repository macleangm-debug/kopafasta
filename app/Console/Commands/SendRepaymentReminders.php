<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendRepaymentReminders extends Command
{
    protected $signature = 'loans:send-reminders
                            {--days=3,1,0 : Comma list of days-before-due to remind on (0 = today)}
                            {--overdue    : Also remind on overdue installments (1-day delinquent only)}';

    protected $description = 'Send SMS/email payment reminders for upcoming or overdue repayment installments';

    public function handle(NotificationService $notifier): int
    {
        $messaging = app(\App\Services\Messaging\TransactionalMessagingService::class);
        $messaging->ensureDefaults();

        if (! $messaging->isGloballyEnabled()) {
            $this->warn('Transactional messaging is disabled — no reminders sent.');

            return self::SUCCESS;
        }

        $optionDays = (string) $this->option('days');
        $days = $optionDays !== '3,1,0'
            ? collect(explode(',', $optionDays))
            : collect($messaging->reminderOffsetsDays());

        $days = $days
            ->map(fn ($d) => (int) trim((string) $d))
            ->filter(fn ($d) => $d >= 0)
            ->unique()
            ->values();

        $today = Carbon::today();
        $sent  = 0;

        foreach ($days as $offset) {
            $target = $today->copy()->addDays($offset);
            $rows = RepaymentSchedule::with(['loan.customer'])
                ->whereIn('status', ['pending', 'partial'])
                ->whereDate('due_date', $target->toDateString())
                ->get();

            foreach ($rows as $row) {
                if ($this->notifyForRow($notifier, $row, $offset, false)) {
                    $sent++;
                }
            }
        }

        if ($this->option('overdue') && $messaging->overdueRemindersEnabled()) {
            $rows = RepaymentSchedule::with(['loan.customer'])
                ->where('status', 'overdue')
                ->whereDate('due_date', $today->copy()->subDay()->toDateString())
                ->get();
            foreach ($rows as $row) {
                if ($this->notifyForRow($notifier, $row, -1, true)) {
                    $sent++;
                }
            }
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    private function notifyForRow(NotificationService $notifier, RepaymentSchedule $row, int $daysOffset, bool $isOverdue): bool
    {
        /** @var Loan|null $loan */
        $loan = $row->loan;
        if (! $loan) return false;
        /** @var Customer|null $customer */
        $customer = $loan->customer;
        if (! $customer || ! $customer->phone) return false;

        $balanceDue = max(0, (float) $row->total_due - (float) $row->amount_paid);
        if ($balanceDue <= 0) return false;

        $vars = [
            'name'           => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Customer',
            'loan_number'    => $loan->loan_number,
            'amount'         => format_money($balanceDue),
            'due_date'       => Carbon::parse($row->due_date)->format('d M Y'),
            'installment_no' => $row->installment_no,
        ];

        if ($isOverdue) {
            $templateCode = 'repayment_overdue';
            $vars['_fallback_body'] = "Dear {$vars['name']}, your loan {$vars['loan_number']} installment {$vars['amount']} (due {$vars['due_date']}) is overdue. Please pay today to avoid further penalties. — Kopa Fasta";
        } elseif ($daysOffset === 0) {
            $templateCode = 'repayment_due_today';
            $vars['_fallback_body'] = "Dear {$vars['name']}, your loan {$vars['loan_number']} installment {$vars['amount']} is due today. Thank you. — Kopa Fasta";
        } else {
            $templateCode = 'repayment_due_soon';
            $vars['_fallback_body'] = "Dear {$vars['name']}, your loan {$vars['loan_number']} installment {$vars['amount']} is due on {$vars['due_date']} ({$daysOffset} day(s) from today). — Kopa Fasta";
        }
        $vars['_fallback_subject'] = 'Loan repayment reminder';

        $notifier->notifyCustomer($customer, $templateCode, $vars);
        return true;
    }
}
