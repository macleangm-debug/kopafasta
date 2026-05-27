<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates a repayment schedule for a loan.
 *
 * Methods supported:
 *  - 'reducing' (default): equal monthly payment (EMI) with reducing-balance interest
 *  - 'flat'              : equal principal + (principal * monthly rate) interest each period
 *
 * Idempotent: if a schedule already exists for the loan it is left untouched
 * unless $force = true, in which case existing rows are deleted and rebuilt.
 *
 * Interest rate on the loan is stored as a MONTHLY decimal rate, e.g. 0.015 = 1.5% per month.
 */
class RepaymentScheduleGenerator
{
    public function generate(Loan $loan, bool $force = false, string $method = 'reducing'): int
    {
        return DB::transaction(function () use ($loan, $force, $method) {
            $existing = RepaymentSchedule::where('loan_id', $loan->id)->count();
            if ($existing > 0 && ! $force) {
                return $existing;
            }
            if ($force) {
                RepaymentSchedule::where('loan_id', $loan->id)->delete();
            }

            // Principal that the borrower will repay = approved/principal amount (NOT net disbursed).
            $principal = (float) ($loan->approved_amount ?? $loan->principal_amount ?? 0);
            $tenure    = (int)   ($loan->tenure_months ?? 0);
            $rate      = (float) ($loan->interest_rate ?? 0); // monthly decimal
            $start     = $loan->disbursement_date
                ? Carbon::parse($loan->disbursement_date)
                : Carbon::now();

            if ($principal <= 0 || $tenure <= 0) {
                return 0;
            }

            $rows = $method === 'flat'
                ? $this->flat($principal, $rate, $tenure, $start)
                : $this->reducing($principal, $rate, $tenure, $start);

            foreach ($rows as $row) {
                RepaymentSchedule::create([
                    'loan_id'        => $loan->id,
                    'installment_no' => $row['installment_no'],
                    'due_date'       => $row['due_date'],
                    'principal_due'  => $row['principal_due'],
                    'interest_due'   => $row['interest_due'],
                    'total_due'      => $row['total_due'],
                    'amount_paid'    => 0,
                    'status'         => 'pending',
                ]);
            }

            // Maintain summary fields on the loan
            $first = $rows[0] ?? null;
            $last  = $rows[array_key_last($rows)] ?? null;
            $loan->update([
                'next_due_date'        => $first ? $first['due_date'] : $loan->next_due_date,
                'maturity_date'        => $last  ? $last['due_date']  : $loan->maturity_date,
                'outstanding_balance'  => $principal,
            ]);

            return count($rows);
        });
    }

    /**
     * Reducing balance EMI:
     *   EMI = P * r * (1+r)^n / ((1+r)^n − 1)
     * If r = 0, fall back to straight-line principal.
     */
    private function reducing(float $principal, float $rate, int $tenure, Carbon $start): array
    {
        $rows = [];
        if ($rate <= 0) {
            return $this->flat($principal, 0.0, $tenure, $start);
        }

        $pow = pow(1 + $rate, $tenure);
        $emi = $principal * $rate * $pow / ($pow - 1);
        $emi = round($emi, 2);

        $balance = $principal;
        for ($i = 1; $i <= $tenure; $i++) {
            $interest  = round($balance * $rate, 2);
            $principalPart = round($emi - $interest, 2);

            // Last installment absorbs rounding so balance hits zero exactly.
            if ($i === $tenure) {
                $principalPart = round($balance, 2);
                $emi           = round($principalPart + $interest, 2);
            }

            $balance = round($balance - $principalPart, 2);

            $rows[] = [
                'installment_no' => $i,
                'due_date'       => $start->copy()->addMonthsNoOverflow($i)->toDateString(),
                'principal_due'  => $principalPart,
                'interest_due'   => $interest,
                'total_due'      => $emi,
            ];
        }

        return $rows;
    }

    /**
     * Flat rate: equal principal each month, interest = principal * monthly rate each month.
     */
    private function flat(float $principal, float $rate, int $tenure, Carbon $start): array
    {
        $rows = [];
        $monthlyPrincipal = round($principal / $tenure, 2);
        $monthlyInterest  = round($principal * $rate, 2);

        $accPrincipal = 0;
        for ($i = 1; $i <= $tenure; $i++) {
            $p = $monthlyPrincipal;
            if ($i === $tenure) {
                $p = round($principal - $accPrincipal, 2); // absorb rounding
            }
            $accPrincipal += $p;
            $rows[] = [
                'installment_no' => $i,
                'due_date'       => $start->copy()->addMonthsNoOverflow($i)->toDateString(),
                'principal_due'  => $p,
                'interest_due'   => $monthlyInterest,
                'total_due'      => round($p + $monthlyInterest, 2),
            ];
        }

        return $rows;
    }
}
