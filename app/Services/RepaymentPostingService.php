<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class RepaymentPostingService
{
    /**
     * Auto-allocate a flat amount across penalty → interest → principal using the
     * loan's unpaid schedule rows. Returns ['penalty'=>..,'interest'=>..,'principal'=>..].
     */
    public function allocate(Loan $loan, float $amount): array
    {
        $remaining = max(0.0, (float) $amount);
        $out = ['penalty' => 0.0, 'interest' => 0.0, 'principal' => 0.0];

        // Sum any outstanding penalty fees on the loan (LoanFee charge_when='late' status='charged')
        $penaltyDue = (float) \App\Models\LoanFee::where('loan_id', $loan->id)
            ->where('charge_when', 'late')
            ->where('status', 'charged')
            ->sum('computed_amount');

        $payPenalty = min($remaining, $penaltyDue);
        $out['penalty'] += $payPenalty;
        $remaining -= $payPenalty;

        // Then walk schedule rows
        $schedules = RepaymentSchedule::where('loan_id', $loan->id)
            ->where('status', '!=', 'paid')
            ->orderBy('installment_no')
            ->get();

        foreach ($schedules as $s) {
            if ($remaining <= 0) break;
            $interestRemaining = max(0, (float) $s->interest_due);
            $principalRemaining = max(0, (float) $s->principal_due);

            // Approximate split of already-paid amount across interest/principal proportionally
            $alreadyPaid = (float) $s->amount_paid;
            if ($alreadyPaid > 0 && ($interestRemaining + $principalRemaining) > 0) {
                $interestRatio = $interestRemaining / ($interestRemaining + $principalRemaining);
                $intPaid = $alreadyPaid * $interestRatio;
                $prnPaid = $alreadyPaid - $intPaid;
                $interestRemaining = max(0, $interestRemaining - $intPaid);
                $principalRemaining = max(0, $principalRemaining - $prnPaid);
            }

            $payInt = min($remaining, $interestRemaining);
            $out['interest'] += $payInt;
            $remaining -= $payInt;

            $payPrn = min($remaining, $principalRemaining);
            $out['principal'] += $payPrn;
            $remaining -= $payPrn;
        }

        // Anything left over → principal (extra prepayment)
        if ($remaining > 0) {
            $out['principal'] += $remaining;
        }

        return [
            'penalty' => round($out['penalty'], 2),
            'interest' => round($out['interest'], 2),
            'principal' => round($out['principal'], 2),
        ];
    }

    /**
     * Apply repayment to schedule rows (mark as paid), update loan outstanding, post journal.
     * Idempotency: if repayment already has a journal entry, skip the post.
     */
    public function post(Repayment $repayment): ?JournalEntry
    {
        return DB::transaction(function () use ($repayment) {
            $loan = Loan::findOrFail($repayment->loan_id);

            // 1) Decrement penalty fees first
            $penaltyLeft = (float) $repayment->penalty_component;
            if ($penaltyLeft > 0) {
                $fees = \App\Models\LoanFee::where('loan_id', $loan->id)
                    ->where('charge_when', 'late')
                    ->where('status', 'charged')
                    ->orderBy('id')
                    ->get();
                foreach ($fees as $fee) {
                    if ($penaltyLeft <= 0) break;
                    $amt = (float) $fee->computed_amount;
                    if ($amt <= $penaltyLeft) {
                        $fee->update(['status' => 'paid', 'paid_at' => now()]);
                        $penaltyLeft -= $amt;
                    } else {
                        break; // partial penalty payments stay 'charged'
                    }
                }
            }

            // 2) Apply payment across schedule rows (interest + principal components)
            $this->applyToSchedules($loan, $repayment);

            // 3) Recalculate outstanding from schedule components
            $loan = app(LoanBalanceService::class)->syncOutstandingBalance($loan);
            if ($loan->outstanding_balance <= 0) {
                $loan->status = 'closed';
                $loan->closed_at = now();
                app(GuarantorNotificationService::class)->notifyLoanClosed($loan->fresh(['application']));
            } elseif ($loan->status === 'arrears' && ! RepaymentSchedule::where('loan_id', $loan->id)->where('status', 'overdue')->exists()) {
                $loan->status = 'active';
            }
            $loan->save();

            // 4) Mark repayment as allocated
            if ($repayment->status === 'received' || $repayment->status === 'pending') {
                $repayment->update(['status' => 'allocated']);
            }

            $capital = app(CapitalPartnerAllocationService::class);
            $capital->distributeInterest($loan, (float) $repayment->interest_component);
            $capital->reduceExposure($loan, (float) $repayment->principal_component);

            try {
                app(AssetLendingRepaymentService::class)->accruePrincipalPayout($loan, $repayment);
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                app(GroupLendingService::class)->recordSuccessfulRepayment($loan);
            } catch (\Throwable $e) {
                report($e);
            }

            // 5) Post journal entry
            return $this->postJournal($repayment->fresh(), $loan);
        });
    }

    /** Walk unpaid installments and apply interest + principal components in order. */
    private function applyToSchedules(Loan $loan, Repayment $repayment): void
    {
        $remaining = round((float) $repayment->interest_component + (float) $repayment->principal_component, 2);
        if ($remaining <= 0) {
            return;
        }

        $schedules = RepaymentSchedule::query()
            ->where('loan_id', $loan->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('installment_no')
            ->get();

        $firstTouchedId = null;

        foreach ($schedules as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            $rowRemaining = max(0, round((float) $schedule->total_due - (float) $schedule->amount_paid, 2));
            if ($rowRemaining <= 0) {
                continue;
            }

            $apply = min($remaining, $rowRemaining);
            $schedule->amount_paid = round((float) $schedule->amount_paid + $apply, 2);

            if ($schedule->amount_paid >= (float) $schedule->total_due - 0.01) {
                $schedule->status = 'paid';
                $schedule->paid_at = now();
            } else {
                $schedule->status = 'partial';
            }

            $schedule->save();
            $remaining = round($remaining - $apply, 2);
            $firstTouchedId ??= $schedule->id;

            if ($schedule->status === 'paid') {
                try {
                    app(MemberEngagementRewardService::class)->afterRepaymentSchedulePaid($schedule->fresh(), $repayment);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        if ($firstTouchedId && ! $repayment->repayment_schedule_id) {
            $repayment->repayment_schedule_id = $firstTouchedId;
            $repayment->save();
        }

        $nextDue = RepaymentSchedule::query()
            ->where('loan_id', $loan->id)
            ->whereNotIn('status', ['paid'])
            ->orderBy('installment_no')
            ->value('due_date');

        if ($nextDue) {
            $loan->next_due_date = $nextDue;
        }
    }

    /**
     * Dr Cash/Bank: amount
     *   Cr Loan Receivable: principal_component
     *   Cr Interest Income: interest_component
     *   Cr Penalty Income: penalty_component
     */
    protected function postJournal(Repayment $repayment, Loan $loan): ?JournalEntry
    {
        // Idempotency
        $existing = JournalEntry::where('source_type', Repayment::class)
            ->where('source_id', $repayment->id)
            ->first();
        if ($existing) return $existing;

        $ledger = app(LedgerService::class);
        $cashId = $ledger->cashAccountId();
        $recvId = $ledger->loanReceivableAccountId();
        $interestId = (int) (Setting::get('finance.interest_income_gl_account_id') ?? 0) ?: null;
        $penaltyId  = (int) (Setting::get('finance.penalty_income_gl_account_id') ?? 0) ?: null;
        if (!$cashId || !$recvId) return null;

        $principal = (float) $repayment->principal_component;
        $interest  = (float) $repayment->interest_component;
        $penalty   = (float) $repayment->penalty_component;
        $total = (float) $repayment->amount;

        $lines = [];
        $lines[] = ['account_id' => $cashId, 'debit' => $total, 'credit' => 0, 'description' => 'Repayment ' . $repayment->reference];

        $creditTotal = 0.0;
        if ($principal > 0) {
            $lines[] = ['account_id' => $recvId, 'debit' => 0, 'credit' => $principal, 'description' => 'Principal ' . $loan->loan_number];
            $creditTotal += $principal;
        }
        if ($interest > 0 && $interestId) {
            $lines[] = ['account_id' => $interestId, 'debit' => 0, 'credit' => $interest, 'description' => 'Interest ' . $loan->loan_number];
            $creditTotal += $interest;
        }
        if ($penalty > 0 && $penaltyId) {
            $lines[] = ['account_id' => $penaltyId, 'debit' => 0, 'credit' => $penalty, 'description' => 'Penalty ' . $loan->loan_number];
            $creditTotal += $penalty;
        }

        // Plug any rounding gap or missing interest/penalty GL into receivable so entry balances
        $diff = round($total - $creditTotal, 2);
        if (abs($diff) > 0.0001) {
            $lines[] = ['account_id' => $recvId, 'debit' => 0, 'credit' => $diff, 'description' => 'Balancing ' . $loan->loan_number];
        }

        try {
            return $ledger->post(
                $lines,
                'Repayment ' . $repayment->reference,
                $repayment,
                optional($repayment->paid_at)->toDateString() ?? now()->toDateString(),
                'Auto-posted on repayment allocation.'
            );
        } catch (\Throwable $e) {
            logger()->warning('Repayment journal not posted: ' . $e->getMessage());
            return null;
        }
    }
}
