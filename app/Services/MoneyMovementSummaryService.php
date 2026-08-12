<?php

namespace App\Services;

use App\Models\CapitalWithdrawalRequest;
use App\Models\CustomerPayment;
use App\Models\Disbursement;
use App\Models\PartnerPayment;

/**
 * Complete money-in / money-out totals for the Payments desk and Money ledger.
 */
class MoneyMovementSummaryService
{
    /** @return array{count: int, amount: float} */
    public function completeIncoming(): array
    {
        $query = CustomerPayment::query()->complete();

        return [
            'count' => (clone $query)->count(),
            'amount' => (float) (clone $query)->sum('amount'),
        ];
    }

    /**
     * Paid partner payouts + approved capital withdrawals + released disbursements.
     *
     * @return array{count: int, amount: float}
     */
    public function completeOutgoing(): array
    {
        $partners = PartnerPayment::query()->where('status', 'paid');
        $capital = CapitalWithdrawalRequest::query()->whereIn('status', ['approved', 'paid']);
        $disbursements = Disbursement::query()->where(function ($q) {
            $q->where('status', 'released')
                ->orWhereNotNull('released_at');
        });

        $partnerCount = (clone $partners)->count();
        $capitalCount = (clone $capital)->count();
        $disbursementCount = (clone $disbursements)->count();

        return [
            'count' => $partnerCount + $capitalCount + $disbursementCount,
            'amount' => (float) (clone $partners)->sum('amount')
                + (float) (clone $capital)->sum('amount')
                + (float) (clone $disbursements)->sum('amount'),
        ];
    }
}
