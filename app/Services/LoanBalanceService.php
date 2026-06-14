<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanFee;
use App\Models\RepaymentSchedule;

class LoanBalanceService
{
    /** @return array<string, float> */
    public function breakdown(Loan $loan): array
    {
        $loan->loadMissing(['repaymentSchedules', 'fees', 'arrearCases', 'repayments']);

        $principalOutstanding = 0.0;
        $interestOutstanding = 0.0;

        foreach ($loan->repaymentSchedules as $row) {
            if ($this->isPaid($row)) {
                continue;
            }

            $remaining = max(0, (float) $row->total_due - (float) $row->amount_paid);
            if ($remaining <= 0) {
                continue;
            }

            $principalDue = (float) $row->principal_due;
            $interestDue = (float) $row->interest_due;
            $scheduledTotal = $principalDue + $interestDue;

            if ($scheduledTotal > 0) {
                $principalOutstanding += round($remaining * ($principalDue / $scheduledTotal), 2);
                $interestOutstanding += round($remaining * ($interestDue / $scheduledTotal), 2);
            } else {
                $principalOutstanding += $remaining;
            }
        }

        $penaltyOutstanding = (float) $loan->fees()
            ->whereNull('paid_at')
            ->whereIn('code', ['LATE_FEE', 'PENALTY'])
            ->sum('computed_amount');

        $penaltyOutstanding += (float) $loan->arrearCases()
            ->whereIn('status', ['open', 'escalated'])
            ->sum('penalty_amount');

        $recoveryCosts = (float) $loan->fees()
            ->whereNull('paid_at')
            ->whereIn('code', ['RECOVERY', 'LEGAL', 'COLLECTION'])
            ->sum('computed_amount');

        $totalOutstanding = round(
            $principalOutstanding + $interestOutstanding + $penaltyOutstanding + $recoveryCosts,
            2
        );

        return [
            'principal_outstanding' => round($principalOutstanding, 2),
            'interest_outstanding'  => round($interestOutstanding, 2),
            'penalty_outstanding'   => round($penaltyOutstanding, 2),
            'recovery_costs'        => round($recoveryCosts, 2),
            'total_outstanding'     => $totalOutstanding,
        ];
    }

    public function syncOutstandingBalance(Loan $loan): Loan
    {
        $breakdown = $this->breakdown($loan);
        $loan->update(['outstanding_balance' => $breakdown['total_outstanding']]);

        return $loan->fresh();
    }

    private function isPaid(RepaymentSchedule $row): bool
    {
        return $row->status === 'paid'
            || (float) $row->amount_paid >= (float) $row->total_due;
    }
}
