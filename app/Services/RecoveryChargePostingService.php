<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanFee;
use Illuminate\Support\Facades\DB;

class RecoveryChargePostingService
{
    /**
     * Post recovery fee accrual to GL when charged to borrower.
     *
     *   Dr Loan Receivable (total borrower charge)
     *     Cr Recovery Revenue (company markup)
     *     Cr Recovery Partner Payable (partner cost)
     */
    public function postFeeAccrual(Loan $loan, LoanFee $fee, float $partnerAmount, float $companyAmount): ?JournalEntry
    {
        $total = round($partnerAmount + $companyAmount, 2);
        if ($total <= 0) {
            return null;
        }

        $existing = JournalEntry::query()
            ->where('source_type', LoanFee::class)
            ->where('source_id', $fee->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $ledger = app(LedgerService::class);
        $receivableId = $ledger->loanReceivableAccountId();
        $revenueId = $ledger->recoveryRevenueAccountId();
        $payableId = $ledger->recoveryPartnerPayableAccountId();

        if (! $receivableId || ! $revenueId || ! $payableId) {
            return null;
        }

        $lines = [
            ['account_id' => $receivableId, 'debit' => $total, 'credit' => 0, 'description' => 'Recovery fee '.$fee->code],
        ];

        if ($companyAmount > 0) {
            $lines[] = ['account_id' => $revenueId, 'debit' => 0, 'credit' => $companyAmount, 'description' => 'Recovery markup'];
        }

        $partnerCredit = round($total - $companyAmount, 2);
        if ($partnerCredit > 0) {
            $lines[] = ['account_id' => $payableId, 'debit' => 0, 'credit' => $partnerCredit, 'description' => 'Partner cost accrual'];
        }

        try {
            return DB::transaction(function () use ($ledger, $lines, $fee, $loan, $total) {
                return $ledger->post(
                    $lines,
                    'Recovery charge '.$fee->code.' · '.$loan->loan_number,
                    $fee,
                    now()->toDateString(),
                    'Auto-posted recovery fee split (partner cost + company markup).',
                );
            });
        } catch (\Throwable $e) {
            logger()->warning('Recovery charge GL not posted: '.$e->getMessage(), [
                'loan_fee_id' => $fee->id,
            ]);

            return null;
        }
    }
}
