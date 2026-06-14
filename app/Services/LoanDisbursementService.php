<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanFee;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class LoanDisbursementService
{
    /** Backfill a disbursement journal for loans disbursed before GL posting was wired. */
    public function backfillJournal(Loan $loan): void
    {
        $base = (float) ($loan->approved_amount ?? $loan->principal_amount ?? 0);
        $net = (float) ($loan->net_disbursed_amount ?? $base);
        $fees = LoanFee::query()
            ->where('loan_id', $loan->id)
            ->where('charge_when', 'disbursement')
            ->get()
            ->all();

        $this->postDisbursementJournal($loan->fresh(), $fees, $base, $net);
    }

    /**
     * Apply all configured fees to a loan at disbursement time.
     *
     * - Reads ACTIVE rows from `charges_fees` where charge_when='disbursement'
     * - Plus pulls in unpaid APP_FEE from the loan application (if any) and bills it here
     * - Calculates each fee against principal/approved amount, respecting min/max
     * - Snapshots them into `loan_fees` (idempotent: skips if same code already charged)
     * - Updates loan.fees_total and loan.net_disbursed_amount (approved − deducted fees)
     */
    public function applyFees(Loan $loan): array
    {
        return DB::transaction(function () use ($loan) {
            $base = (float) ($loan->approved_amount ?? $loan->principal_amount ?? 0);
            $applied = [];

            $existingCodes = LoanFee::where('loan_id', $loan->id)
                ->where('charge_when', 'disbursement')
                ->pluck('code')->all();

            // 1) Configured disbursement-time fees
            $fees = ChargesFee::where('is_active', true)
                ->where('charge_when', 'disbursement')
                ->orderBy('id')
                ->get();

            foreach ($fees as $fee) {
                if (in_array($fee->code, $existingCodes, true)) continue;

                $amount = $this->compute((float) $fee->amount, $fee->basis, $base, $fee->min_amount, $fee->max_amount);
                if ($amount <= 0) continue;

                $applied[] = LoanFee::create([
                    'loan_id'                 => $loan->id,
                    'charges_fee_id'          => $fee->id,
                    'code'                    => $fee->code,
                    'name'                    => $fee->name,
                    'type'                    => $fee->type,
                    'basis'                   => $fee->basis,
                    'rate_or_amount'          => $fee->amount,
                    'computed_amount'         => $amount,
                    'deducted_from_principal' => true,
                    'status'                  => 'charged',
                    'charge_when'             => 'disbursement',
                    'gl_account_id'           => $fee->gl_account_id,
                    'charged_at'              => now(),
                ]);
            }

            // 2) Application fee carried over from LoanApplication (snapshot)
            if ($loan->loan_application_id && ! in_array('APP_FEE', $existingCodes, true)) {
                $app = LoanApplication::find($loan->loan_application_id);
                $appFee = (float) ($app->application_fee_amount ?? 0);
                if ($app && $appFee > 0 && ($app->application_fee_status ?? 'unpaid') !== 'paid') {
                    $cfg = ChargesFee::where('code', 'APP_FEE')->first();
                    $customer = $app->customer;
                    $chargedAmount = $appFee;
                    $discountAmount = 0.0;

                    if ($customer) {
                        $referrals = app(ReferralService::class);
                        $quote = $referrals->quoteFee($customer, $appFee, false, 'application_fee');
                        $chargedAmount = $quote['after_discount'];
                        $discountAmount = $quote['discount'];

                        if ($quote['has_referrer']) {
                            $referrals->settleFee(
                                $customer,
                                $appFee,
                                false,
                                'application_fee',
                                LoanApplication::class,
                                (int) $app->id,
                            );
                        } else {
                            app(AffiliateService::class)->accrueCommission(
                                $customer,
                                $appFee,
                                'application_fee',
                                LoanApplication::class,
                                (int) $app->id,
                            );
                        }
                    }

                    $applied[] = LoanFee::create([
                        'loan_id'                 => $loan->id,
                        'charges_fee_id'          => optional($cfg)->id,
                        'code'                    => 'APP_FEE',
                        'name'                    => optional($cfg)->name ?? 'Loan application fee',
                        'type'                    => optional($cfg)->type ?? 'processing',
                        'basis'                   => 'fixed',
                        'rate_or_amount'          => $appFee,
                        'computed_amount'         => $chargedAmount,
                        'deducted_from_principal' => true,
                        'status'                  => 'charged',
                        'charge_when'             => 'disbursement',
                        'gl_account_id'           => optional($cfg)->gl_account_id,
                        'charged_at'              => now(),
                    ]);
                    $app->update([
                        'application_fee_status'   => 'charged',
                        'referral_discount_amount' => $discountAmount > 0 ? $discountAmount : null,
                    ]);
                }
            }

            // 3) Roll up totals on the loan
            $deducted = LoanFee::where('loan_id', $loan->id)
                ->where('deducted_from_principal', true)
                ->sum('computed_amount');
            $net = max(0, $base - (float) $deducted);
            $loan->update([
                'fees_total'           => $deducted,
                'net_disbursed_amount' => $net,
            ]);

            // 4) Post journal entry (best-effort: skip if GL accounts not configured)
            $this->postDisbursementJournal($loan->fresh(), $applied, $base, $net);

            return $applied;
        });
    }

    /**
     * Dr Loan Receivable: approved_amount
     *   Cr Cash/Bank: net disbursed
     *   Cr Fee Income (per fee with gl_account_id)
     */
    protected function postDisbursementJournal(Loan $loan, array $applied, float $base, float $net): void
    {
        if (JournalEntry::query()
            ->where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->where('description', 'like', 'Loan disbursement%')
            ->exists()) {
            return;
        }

        $ledger = app(LedgerService::class);
        $receivableId = $ledger->loanReceivableAccountId();
        if (! $receivableId) {
            logger()->warning('Disbursement journal skipped: loan receivable GL account not configured.', [
                'loan_id' => $loan->id,
            ]);

            return;
        }

        $loan->loadMissing('capitalAllocations');
        $usesCapital = $loan->capitalAllocations()->exists();
        $capitalPoolId = $ledger->capitalPartnerPoolAccountId();
        $cashId = $ledger->cashAccountId();

        if (! $usesCapital && ! $cashId) {
            logger()->warning('Disbursement journal skipped: cash/bank GL account not configured.', [
                'loan_id' => $loan->id,
            ]);

            return;
        }

        if ($usesCapital && ! $capitalPoolId) {
            logger()->warning('Disbursement journal skipped: capital partner pool GL account not configured.', [
                'loan_id' => $loan->id,
            ]);

            return;
        }

        $lines = [];
        $lines[] = ['account_id' => $receivableId, 'debit' => $base, 'credit' => 0, 'description' => 'Loan receivable '.$loan->loan_number];

        $feeCreditTotal = 0.0;
        if (! $usesCapital) {
            foreach ($applied as $fee) {
                $accountId = $fee->gl_account_id ?: $this->defaultFeeIncomeAccountId($ledger);
                if (! $accountId) {
                    continue;
                }
                $amt = (float) $fee->computed_amount;
                if ($amt <= 0) {
                    continue;
                }
                $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => $amt, 'description' => $fee->code.' '.$loan->loan_number];
                $feeCreditTotal += $amt;
            }
        }

        $fundingCredit = $usesCapital ? $base : round($base - $feeCreditTotal, 2);
        if ($fundingCredit < 0) {
            logger()->warning('Disbursement journal skipped: fees exceed approved amount.', [
                'loan_id' => $loan->id,
            ]);

            return;
        }

        if ($usesCapital && $capitalPoolId) {
            $lines[] = [
                'account_id'  => $capitalPoolId,
                'debit'       => 0,
                'credit'      => $fundingCredit,
                'description' => 'Capital partner pool deployment '.$loan->loan_number,
            ];
        } else {
            $lines[] = [
                'account_id'  => $cashId,
                'debit'       => 0,
                'credit'      => $fundingCredit,
                'description' => 'Net disbursement '.$loan->loan_number,
            ];
        }

        try {
            $memo = $usesCapital
                ? 'Auto-posted on disbursement (capital partner funded). Gross: '.format_money($base)
                : 'Auto-posted on disbursement. Net cash: '.format_money($net);

            $ledger->post(
                $lines,
                'Loan disbursement '.$loan->loan_number,
                $loan,
                $loan->disbursement_date?->toDateString() ?? now()->toDateString(),
                $memo,
            );
        } catch (\Throwable $e) {
            logger()->warning('Disbursement journal not posted: '.$e->getMessage(), [
                'loan_id' => $loan->id,
            ]);
        }
    }

    private function defaultFeeIncomeAccountId(LedgerService $ledger): ?int
    {
        $id = (int) (Setting::get('finance.fee_income_gl_account_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return \App\Models\ChartOfAccount::query()
            ->where('type', 'income')
            ->where(function ($q) {
                $q->where('name', 'like', '%application%fee%')
                    ->orWhere('name', 'like', '%fee%');
            })
            ->orderBy('code')
            ->value('id');
    }

    /**
     * Compute a fee amount for a given basis and base principal.
     */
    public function compute(float $value, string $basis, float $base, ?float $min, ?float $max): float
    {
        $amount = match ($basis) {
            'percentage' => $base * ($value / 100.0),
            'fixed'      => $value,
            default      => 0.0, // per_day / per_installment don't apply at disbursement
        };
        if ($min !== null && $amount < (float) $min) $amount = (float) $min;
        if ($max !== null && $max > 0 && $amount > (float) $max) $amount = (float) $max;
        return round($amount, 2);
    }

    /** Reverse disbursement-time fees and journal entry. */
    public function reverseFees(Loan $loan): void
    {
        DB::transaction(function () use ($loan) {
            $fees = \App\Models\LoanFee::where('loan_id', $loan->id)
                ->where('charge_when', 'disbursement')
                ->get();

            if ($fees->isEmpty()) {
                return;
            }

            $base = (float) ($loan->approved_amount ?? $loan->principal_amount ?? 0);
            $this->postDisbursementReversalJournal($loan, $fees->all(), $base);

            \App\Models\LoanFee::where('loan_id', $loan->id)
                ->where('charge_when', 'disbursement')
                ->delete();

            if ($loan->loan_application_id) {
                $app = LoanApplication::find($loan->loan_application_id);
                if ($app && ($app->application_fee_status ?? '') === 'charged') {
                    $app->update(['application_fee_status' => 'paid']);
                }
            }
        });
    }

    /** Mirror of disbursement journal with debits/credits swapped. */
    protected function postDisbursementReversalJournal(Loan $loan, array $applied, float $base): void
    {
        $ledger = app(LedgerService::class);
        $receivableId = $ledger->loanReceivableAccountId();
        if (! $receivableId) {
            return;
        }

        $loan->loadMissing('capitalAllocations');
        $usesCapital = $loan->capitalAllocations()->exists();
        $capitalPoolId = $ledger->capitalPartnerPoolAccountId();
        $cashId = $ledger->cashAccountId();

        if (! $usesCapital && ! $cashId) {
            return;
        }

        if ($usesCapital && ! $capitalPoolId) {
            return;
        }

        $lines = [];
        $lines[] = ['account_id' => $receivableId, 'debit' => 0, 'credit' => $base, 'description' => 'Reversal receivable '.$loan->loan_number];

        $feeCreditTotal = 0.0;
        foreach ($applied as $fee) {
            if (! $fee->gl_account_id) {
                continue;
            }
            $amt = (float) $fee->computed_amount;
            if ($amt <= 0) {
                continue;
            }
            $lines[] = ['account_id' => $fee->gl_account_id, 'debit' => $amt, 'credit' => 0, 'description' => 'Reversal '.$fee->code.' '.$loan->loan_number];
            $feeCreditTotal += $amt;
        }

        $fundingDebit = round($base - $feeCreditTotal, 2);
        if ($fundingDebit < 0) {
            return;
        }

        if ($usesCapital && $capitalPoolId) {
            $lines[] = [
                'account_id'  => $capitalPoolId,
                'debit'       => $fundingDebit,
                'credit'      => 0,
                'description' => 'Reversal capital partner pool '.$loan->loan_number,
            ];
        } else {
            $lines[] = [
                'account_id'  => $cashId,
                'debit'       => $fundingDebit,
                'credit'      => 0,
                'description' => 'Reversal disbursement '.$loan->loan_number,
            ];
        }

        try {
            $ledger->post(
                $lines,
                'Disbursement reversal '.$loan->loan_number,
                $loan,
                now()->toDateString(),
                'Auto-posted on disbursement reversal.',
            );
        } catch (\Throwable $e) {
            logger()->warning('Disbursement reversal journal not posted: '.$e->getMessage());
        }
    }
}
