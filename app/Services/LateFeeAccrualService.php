<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanFee;
use App\Models\RepaymentSchedule;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LateFeeAccrualService
{
    /**
     * Accrue late fees for all loans with overdue installments as of $asOf.
     * Returns ['loans' => N, 'fees_created' => N, 'amount' => float, 'journals' => N].
     */
    public function accrue(?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();
        $stats = ['loans' => 0, 'fees_created' => 0, 'amount' => 0.0, 'journals' => 0];

        $rule = ChargesFee::where('code', 'LATE_FEE')
            ->where('is_active', true)
            ->first();
        if (!$rule) return $stats + ['note' => 'No active LATE_FEE rule.'];

        $loanIds = RepaymentSchedule::where('status', '!=', 'paid')
            ->whereDate('due_date', '<', $asOf->toDateString())
            ->distinct()
            ->pluck('loan_id');

        foreach ($loanIds as $loanId) {
            /** @var Loan|null $loan */
            $loan = Loan::find($loanId);
            if (!$loan || in_array($loan->status, ['closed', 'written_off'])) continue;

            $accrued = $this->accrueForLoan($loan, $rule, $asOf);
            if ($accrued['fees_created'] > 0) {
                $stats['loans']++;
                $stats['fees_created'] += $accrued['fees_created'];
                $stats['amount']       += $accrued['amount'];
                if ($accrued['journal']) $stats['journals']++;
            }
        }
        return $stats;
    }

    /**
     * For one loan, compute days overdue across unpaid schedule rows (capped to first overdue row).
     * Creates a LoanFee row per accrual day that isn't yet recorded, then posts a single JE per call
     * summing the new fees.
     */
    protected function accrueForLoan(Loan $loan, ChargesFee $rule, Carbon $asOf): array
    {
        $out = ['fees_created' => 0, 'amount' => 0.0, 'journal' => null];

        $firstOverdue = RepaymentSchedule::where('loan_id', $loan->id)
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', $asOf->toDateString())
            ->orderBy('due_date')
            ->first();
        if (!$firstOverdue) return $out;

        $start = Carbon::parse($firstOverdue->due_date)->copy()->startOfDay()->addDay(); // grace = 0; first late day = due_date+1
        $end   = $asOf->copy()->startOfDay();

        if ($end->lt($start)) return $out;

        // What's the per-day amount?
        $base = (float) ($firstOverdue->total_due - $firstOverdue->amount_paid);
        $perDay = $this->perDayAmount($rule, $base);
        if ($perDay <= 0) return $out;

        // Find dates already accrued (LoanFee notes will store accrual date YYYY-MM-DD)
        $existing = LoanFee::where('loan_id', $loan->id)
            ->where('code', 'LATE_FEE')
            ->where('charge_when', 'late')
            ->pluck('notes')
            ->filter()
            ->all();

        $created = [];
        DB::transaction(function () use ($loan, $rule, $start, $end, $perDay, $existing, &$created, &$out) {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $tag = 'accrual:' . $d->toDateString();
                if (in_array($tag, $existing, true)) continue;
                $fee = LoanFee::create([
                    'loan_id'                 => $loan->id,
                    'charges_fee_id'          => $rule->id,
                    'code'                    => 'LATE_FEE',
                    'name'                    => $rule->name ?? 'Late payment fee',
                    'type'                    => $rule->type ?? 'fixed',
                    'basis'                   => 'overdue_balance',
                    'rate_or_amount'          => (float) ($rule->amount ?? $rule->value ?? $perDay),
                    'computed_amount'         => $perDay,
                    'deducted_from_principal' => false,
                    'status'                  => 'charged',
                    'charge_when'             => 'late',
                    'gl_account_id'           => $rule->gl_account_id ?? null,
                    'charged_at'              => $d->copy()->endOfDay(),
                    'notes'                   => $tag,
                ]);
                $created[] = $fee;
                $out['fees_created']++;
                $out['amount'] += $perDay;
            }

            if ($out['amount'] > 0) {
                $out['journal'] = $this->postJournal($loan, $out['amount'], $created, $end);
            }
        });

        return $out;
    }

    protected function perDayAmount(ChargesFee $rule, float $base): float
    {
        // Best-effort: if charge type=percentage and basis daily, use rate of base.
        // Otherwise, treat rule.amount as per-day fixed.
        $type = $rule->type ?? 'fixed';
        $value = (float) ($rule->amount ?? $rule->value ?? $rule->rate ?? 0);
        if ($type === 'percentage') {
            return round($base * ($value / 100), 2);
        }
        return round($value, 2);
    }

    /**
     * Dr Loan Receivable: total
     *   Cr Penalty Income: total
     * Idempotency: tied to first LoanFee created in the batch (source).
     */
    protected function postJournal(Loan $loan, float $amount, array $fees, Carbon $date): ?JournalEntry
    {
        $ledger = app(LedgerService::class);
        $recvId    = $ledger->loanReceivableAccountId();
        $penaltyId = (int) (Setting::get('finance.penalty_income_gl_account_id') ?? 0) ?: null;
        if (!$recvId || !$penaltyId) return null;

        $source = $fees[0] ?? null;

        $lines = [
            ['account_id' => $recvId,    'debit' => $amount, 'credit' => 0, 'description' => 'Late fee accrual ' . $loan->loan_number],
            ['account_id' => $penaltyId, 'debit' => 0, 'credit' => $amount, 'description' => 'Penalty income ' . $loan->loan_number],
        ];
        try {
            return $ledger->post(
                $lines,
                'Late fee accrual ' . $loan->loan_number,
                $source,
                $date->toDateString(),
                'Auto-accrued by LateFeeAccrualService for '.count($fees).' day(s).'
            );
        } catch (\Throwable $e) {
            logger()->warning('Late fee JE not posted: ' . $e->getMessage());
            return null;
        }
    }
}
