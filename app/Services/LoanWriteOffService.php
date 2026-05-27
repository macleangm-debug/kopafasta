<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class LoanWriteOffService
{
    /**
     * Mark a loan as written off and post the GL entry.
     *
     *   Dr Bad Debt Expense  (outstanding balance)
     *     Cr Loan Receivable (outstanding balance)
     */
    public function writeOff(Loan $loan, string $reason, ?float $amount = null): ?JournalEntry
    {
        $writeOffAmount = (float) ($amount ?? $loan->outstanding_balance);
        if ($writeOffAmount <= 0) {
            throw new \RuntimeException('Nothing to write off (outstanding balance is zero).');
        }

        return DB::transaction(function () use ($loan, $reason, $writeOffAmount) {
            $loan->update([
                'status'             => 'written_off',
                'written_off_at'     => now(),
                'written_off_amount' => $writeOffAmount,
                'write_off_reason'   => $reason,
                'outstanding_balance'=> max(0, (float) $loan->outstanding_balance - $writeOffAmount),
            ]);

            return $this->postJournal($loan->fresh(), $writeOffAmount);
        });
    }

    protected function postJournal(Loan $loan, float $amount): ?JournalEntry
    {
        // Idempotency
        $existing = JournalEntry::where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->where('description', 'like', 'Write-off%')
            ->first();
        if ($existing) return $existing;

        $ledger  = app(LedgerService::class);
        $recvId  = $ledger->loanReceivableAccountId();
        $badId   = (int) (Setting::get('finance.bad_debt_expense_gl_account_id') ?? 0) ?: null;
        if (!$recvId || !$badId) return null;

        $lines = [
            ['account_id' => $badId,  'debit' => $amount, 'credit' => 0, 'description' => 'Bad debt ' . $loan->loan_number],
            ['account_id' => $recvId, 'debit' => 0, 'credit' => $amount, 'description' => 'Receivable ' . $loan->loan_number],
        ];
        try {
            return $ledger->post(
                $lines,
                'Write-off ' . $loan->loan_number,
                $loan,
                now()->toDateString(),
                'Loan written off.'
            );
        } catch (\Throwable $e) {
            logger()->warning('Write-off JE not posted: ' . $e->getMessage());
            return null;
        }
    }
}
