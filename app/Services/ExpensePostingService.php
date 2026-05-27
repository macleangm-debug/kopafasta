<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class ExpensePostingService
{
    /**
     * Post an expense to the General Ledger.
     *
     *   Dr Expense (per-expense gl_account_id, else default expense GL)
     *     Cr Cash/Bank
     */
    public function post(Expense $expense): ?JournalEntry
    {
        // Idempotency
        $existing = JournalEntry::where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $ledger    = app(LedgerService::class);
        $cashId    = $ledger->cashAccountId();
        $expenseId = (int) ($expense->gl_account_id ?? 0) ?:
                     (int) (Setting::get('finance.default_expense_gl_account_id') ?? 0) ?: null;
        if (!$cashId || !$expenseId) return null;

        $amount = (float) $expense->amount;
        if ($amount <= 0) return null;

        $lines = [
            ['account_id' => $expenseId, 'debit' => $amount, 'credit' => 0, 'description' => 'Expense ' . ($expense->category ?? '')],
            ['account_id' => $cashId,    'debit' => 0, 'credit' => $amount, 'description' => 'Cash out '.($expense->reference ?? '')],
        ];

        try {
            return DB::transaction(function () use ($ledger, $lines, $expense) {
                $entry = $ledger->post(
                    $lines,
                    'Expense '.($expense->reference ?? '#'.$expense->id),
                    $expense,
                    optional($expense->expense_date)->toDateString() ?? now()->toDateString(),
                    $expense->description ?? null
                );
                $expense->update(['journal_posted_at' => now()]);
                return $entry;
            });
        } catch (\Throwable $e) {
            logger()->warning('Expense JE not posted: ' . $e->getMessage());
            return null;
        }
    }
}
