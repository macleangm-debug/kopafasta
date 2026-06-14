<?php

namespace App\Services;

use App\Models\ArrearCase;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Services\NotificationService;

class ArrearCaseSyncService
{
    public function __construct(
        private readonly ActiveLoanServicingService $servicing,
        private readonly NotificationService $notifications,
    ) {}

    public function syncForLoan(Loan $loan): ?ArrearCase
    {
        $loan->loadMissing(['repaymentSchedules', 'customer']);
        $metrics = $this->servicing->forLoan($loan);

        if (! ($metrics['in_arrears'] ?? false)) {
            return null;
        }

        $daysPastDue = max(0, -1 * (int) ($metrics['days_remaining'] ?? 0));

        $case = ArrearCase::query()
            ->where('loan_id', $loan->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($case) {
            $case->update([
                'days_past_due'     => max((int) $case->days_past_due, $daysPastDue),
                'amount_in_arrears' => (float) ($metrics['amount_in_arrears'] ?? 0),
            ]);

            return $case->fresh();
        }

        return ArrearCase::create([
            'loan_id'           => $loan->id,
            'days_past_due'     => $daysPastDue,
            'amount_in_arrears' => (float) ($metrics['amount_in_arrears'] ?? 0),
            'penalty_amount'    => 0,
            'status'            => 'open',
        ]);
    }

    public function notifyBorrowerArrears(Loan $loan): void
    {
        $customer = $loan->customer;
        if (! $customer) {
            return;
        }

        $metrics = $this->servicing->forLoan($loan);
        $title = __('borrower.loan_servicing.arrears_notify_title');
        $message = __('borrower.loan_servicing.arrears_notify_message', [
            'reference' => $loan->loan_number,
            'amount'    => format_money((float) ($metrics['amount_in_arrears'] ?? 0)),
            'count'     => (int) ($metrics['overdue_installments'] ?? 0),
        ]);

        $recent = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', 'loan_arrears')
            ->where('created_at', '>=', now()->subDays(7))
            ->where('message', 'like', '%'.$loan->loan_number.'%')
            ->exists();

        if ($recent) {
            return;
        }

        $this->notifications->notifyInApp(
            $customer,
            $message,
            'loan',
            'loan_arrears',
            $title,
            route('site.borrower.loans.show', $loan),
            __('borrower.loans_page.make_payment'),
        );
    }
}
