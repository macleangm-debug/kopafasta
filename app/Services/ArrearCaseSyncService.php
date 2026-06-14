<?php

namespace App\Services;

use App\Models\ArrearCase;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Models\Setting;

class ArrearCaseSyncService
{
    public const ESCALATION_DAYS_PAST_DUE = 180;

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

        $daysPastDue = (int) ($metrics['days_past_due'] ?? 0);
        $status = $daysPastDue >= self::ESCALATION_DAYS_PAST_DUE ? 'escalated' : 'open';

        $case = ArrearCase::query()
            ->where('loan_id', $loan->id)
            ->whereIn('status', ['open', 'escalated'])
            ->latest('id')
            ->first();

        if ($case) {
            $case->update([
                'days_past_due'     => max((int) $case->days_past_due, $daysPastDue),
                'amount_in_arrears' => (float) ($metrics['amount_in_arrears'] ?? 0),
                'status'            => $case->status === 'resolved' ? 'resolved' : $status,
            ]);

            $case = $case->fresh();
        } else {
            $case = ArrearCase::create([
                'loan_id'           => $loan->id,
                'days_past_due'     => $daysPastDue,
                'amount_in_arrears' => (float) ($metrics['amount_in_arrears'] ?? 0),
                'penalty_amount'    => 0,
                'status'            => $status,
            ]);
        }

        app(RecoveryAutoAssignmentService::class)->maybeAssignCallCenter($case);

        return $case;
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
