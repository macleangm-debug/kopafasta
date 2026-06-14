<?php

namespace App\Services;

use App\Models\BorrowerRefund;
use App\Models\JournalEntry;
use App\Models\MobileMoneyAccount;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class BorrowerRefundPostingService
{
    /**
     * Accrue borrower refund liability when auction surplus is owed.
     *
     *   Dr Cash (surplus received, not yet journaled via repayment)
     *     Cr Borrower refunds payable
     */
    public function accrue(BorrowerRefund $refund): ?JournalEntry
    {
        $refund = $refund->fresh();

        if ($refund->accrual_journal_entry_id) {
            return JournalEntry::find($refund->accrual_journal_entry_id);
        }

        $ledger = app(LedgerService::class);
        $cashId = $ledger->cashAccountId();
        $payableId = $ledger->borrowerRefundsPayableAccountId();
        $amount = (float) $refund->amount;

        if (! $cashId || ! $payableId || $amount <= 0) {
            return null;
        }

        $lines = [
            ['account_id' => $cashId, 'debit' => $amount, 'credit' => 0, 'description' => 'Auction surplus '.$refund->reference],
            ['account_id' => $payableId, 'debit' => 0, 'credit' => $amount, 'description' => 'Borrower refund '.$refund->reference],
        ];

        try {
            return DB::transaction(function () use ($ledger, $lines, $refund, $amount) {
                $entry = $ledger->post(
                    $lines,
                    'Borrower refund accrual '.$refund->reference,
                    $refund,
                    now()->toDateString(),
                    'Auction surplus owed to borrower after settlement.',
                );

                if ($entry) {
                    $refund->update([
                        'accrual_journal_entry_id' => $entry->id,
                        'accrual_posted_at'        => now(),
                    ]);
                }

                return $entry;
            });
        } catch (\Throwable $e) {
            logger()->warning('Borrower refund accrual not posted: '.$e->getMessage(), [
                'refund_id' => $refund->id,
                'amount'    => $amount,
            ]);

            return null;
        }
    }

    /**
     * Post payout when refund is paid to borrower.
     *
     *   Dr Borrower refunds payable
     *     Cr Cash / mobile money GL
     */
    public function postPayout(BorrowerRefund $refund): ?JournalEntry
    {
        $refund = $refund->fresh();

        if ($refund->payout_journal_entry_id) {
            return JournalEntry::find($refund->payout_journal_entry_id);
        }

        $ledger = app(LedgerService::class);
        $payableId = $ledger->borrowerRefundsPayableAccountId();
        $cashId = $this->cashAccountIdForPayout($refund);
        $amount = (float) $refund->amount;

        if (! $cashId || ! $payableId || $amount <= 0) {
            return null;
        }

        $lines = [
            ['account_id' => $payableId, 'debit' => $amount, 'credit' => 0, 'description' => 'Refund paid '.$refund->reference],
            ['account_id' => $cashId, 'debit' => 0, 'credit' => $amount, 'description' => 'Payout '.$refund->payment_reference],
        ];

        try {
            return DB::transaction(function () use ($ledger, $lines, $refund) {
                $entry = $ledger->post(
                    $lines,
                    'Borrower refund payout '.$refund->reference,
                    null,
                    optional($refund->paid_at)->toDateString() ?? now()->toDateString(),
                    'Borrower refund disbursed'.($refund->payment_reference ? ' · '.$refund->payment_reference : ''),
                );

                if ($entry) {
                    $refund->update([
                        'payout_journal_entry_id' => $entry->id,
                        'payout_posted_at'        => now(),
                    ]);
                }

                return $entry;
            });
        } catch (\Throwable $e) {
            logger()->warning('Borrower refund payout not posted: '.$e->getMessage(), [
                'refund_id' => $refund->id,
            ]);

            return null;
        }
    }

    protected function cashAccountIdForPayout(BorrowerRefund $refund): ?int
    {
        if ($refund->disbursement_mobile_money_account_id) {
            $account = MobileMoneyAccount::query()->find($refund->disbursement_mobile_money_account_id);
            if ($account?->gl_account_id) {
                return (int) $account->gl_account_id;
            }
        }

        return app(LedgerService::class)->cashAccountId();
    }
}
