<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Carbon;

class ActiveLoanServicingService
{
    /** @return array<string, mixed> */
    public function forLoan(Loan $loan): array
    {
        $loan->loadMissing(['product', 'repaymentSchedules']);
        $breakdown = app(LoanBalanceService::class)->breakdown($loan);
        $recoveryCharges = app(RecoveryChargesService::class)->breakdownForLoan($loan);

        $principal = (float) $loan->principal_amount;
        $outstanding = $breakdown['total_outstanding'];
        $principalOutstanding = (float) ($breakdown['principal_outstanding'] ?? 0);
        $principalPaid = max(0, round($principal - $principalOutstanding, 2));
        $progressPct = $principal > 0 ? min(100, round(($principalPaid / $principal) * 100, 1)) : 0.0;

        $schedules = $loan->repaymentSchedules->sortBy('installment_no');
        $paidRows = $schedules->filter(fn (RepaymentSchedule $row) => in_array($row->status, ['paid'], true)
            || (float) $row->amount_paid >= (float) $row->total_due);
        $remainingRows = $schedules->reject(fn (RepaymentSchedule $row) => in_array($row->status, ['paid'], true)
            || (float) $row->amount_paid >= (float) $row->total_due);

        $remainingOn = static fn (RepaymentSchedule $row): float => max(0, (float) $row->total_due - (float) $row->amount_paid);

        $overdueRows = $schedules->filter(fn (RepaymentSchedule $row) => $this->isOverdue($row));
        $amountInArrears = (float) $overdueRows->sum($remainingOn);
        $nextUpcoming = $remainingRows->first(fn (RepaymentSchedule $row) => ! $this->isOverdue($row));
        $nextInstallment = $overdueRows->first() ?? $nextUpcoming;

        $today = now()->startOfDay();
        $daysRemaining = null;
        if ($nextUpcoming?->due_date) {
            $daysRemaining = (int) $today->diffInDays($nextUpcoming->due_date->startOfDay(), false);
        }

        $daysPastDue = (int) $overdueRows
            ->map(fn (RepaymentSchedule $row) => (int) $row->due_date?->startOfDay()->diffInDays($today))
            ->max() ?? 0;

        $maturityDate = $loan->maturity_date ?? $schedules->last()?->due_date;
        $daysToMaturity = $maturityDate
            ? (int) $today->diffInDays(Carbon::parse($maturityDate)->startOfDay(), false)
            : null;

        return [
            'loan_reference'      => $loan->loan_number,
            'product_name'        => $loan->product?->name,
            'status'              => $loan->status,
            'status_label'        => display_label($loan->status, 'loan_status'),
            'principal'           => $principal,
            'outstanding_balance' => $outstanding,
            'balance_breakdown'   => $breakdown,
            'recovery_charges'    => $recoveryCharges,
            'principal_paid'      => $principalPaid,
            'progress_pct'        => $progressPct,
            'next_installment'    => $nextInstallment,
            'next_due_date'       => $nextUpcoming?->due_date,
            'next_due_amount'     => $nextUpcoming ? $remainingOn($nextUpcoming) : null,
            'days_remaining'      => $daysRemaining,
            'days_to_maturity'    => $daysToMaturity,
            'maturity_date'       => $maturityDate,
            'in_arrears'          => $loan->status === 'arrears' || $overdueRows->isNotEmpty(),
            'amount_in_arrears'   => $amountInArrears,
            'overdue_installments'=> $overdueRows->count(),
            'days_past_due'       => $daysPastDue,
            'installments_paid'   => $paidRows->count(),
            'installments_remaining' => $remainingRows->count(),
            'installments_total'  => $schedules->count(),
            'arrears_status'      => $loan->status === 'arrears' || $overdueRows->isNotEmpty()
                ? 'in_arrears'
                : 'current',
            'disbursement_date'   => $loan->disbursement_date,
            'tenure_months'       => (int) $loan->tenure_months,
            'interest_rate'       => (float) $loan->interest_rate,
            'upcoming_rows'       => $remainingRows
                ->reject(fn (RepaymentSchedule $row) => $this->isOverdue($row))
                ->take(8)
                ->values(),
            'overdue_rows'        => $overdueRows->values(),
        ];
    }

    public function isOverdue(RepaymentSchedule $row): bool
    {
        if (in_array($row->status, ['paid'], true)) {
            return false;
        }

        if ((float) $row->amount_paid >= (float) $row->total_due) {
            return false;
        }

        return $row->due_date && $row->due_date->startOfDay()->lt(now()->startOfDay());
    }
}
