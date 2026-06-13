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
 *  - 'reducing' (default): equal instalment with reducing-balance interest
 *  - 'flat'              : equal principal + (principal * period rate) interest each period
 *
 * Cadence:
 *  - 'weekly'  (default): tenure_months × 4 weekly instalments
 *  - 'monthly': one instalment per month
 *
 * Interest rate on the loan is stored as a MONTHLY decimal rate, e.g. 0.015 = 1.5% per month.
 */
class RepaymentScheduleGenerator
{
    /**
     * @return array{shifted: int, interest_accrued: float}
     */
    public function applyPaymentHoliday(Loan $loan, int $holidayMonths, bool $accrueInterest = true): array
    {
        if ($holidayMonths <= 0) {
            return ['shifted' => 0, 'interest_accrued' => 0.0];
        }

        return DB::transaction(function () use ($loan, $holidayMonths, $accrueInterest) {
            $loan->loadMissing('product');
            $cadence = $loan->product->repayment_cadence ?? 'weekly';

            $interestAccrued = 0.0;
            $balance = (float) $loan->outstanding_balance;
            $monthlyRate = (float) ($loan->interest_rate ?? 0);

            if ($accrueInterest && $monthlyRate > 0 && $balance > 0) {
                for ($m = 0; $m < $holidayMonths; $m++) {
                    $monthInterest = round($balance * $monthlyRate, 2);
                    $interestAccrued += $monthInterest;
                    $balance = round($balance + $monthInterest, 2);
                }
            }

            $schedules = RepaymentSchedule::query()
                ->where('loan_id', $loan->id)
                ->whereNotIn('status', ['paid'])
                ->orderBy('due_date')
                ->get();

            foreach ($schedules as $schedule) {
                $due = Carbon::parse($schedule->due_date);
                $shifted = $cadence === 'monthly'
                    ? $due->addMonthsNoOverflow($holidayMonths)
                    : $due->addWeeks($holidayMonths * 4);

                $schedule->update(['due_date' => $shifted->toDateString()]);
            }

            if ($interestAccrued > 0 && $schedules->isNotEmpty()) {
                $first = $schedules->first();
                $first->update([
                    'interest_due' => round((float) $first->interest_due + $interestAccrued, 2),
                    'total_due'    => round((float) $first->total_due + $interestAccrued, 2),
                ]);
            }

            $first = $schedules->first();
            $last = $schedules->last();
            $loan->update([
                'outstanding_balance' => $balance,
                'next_due_date'       => $first?->due_date ?? $loan->next_due_date,
                'maturity_date'       => $last?->due_date ?? $loan->maturity_date,
                'status'              => 'active',
            ]);

            return [
                'shifted'          => $schedules->count(),
                'interest_accrued' => $interestAccrued,
            ];
        });
    }

    public function regenerateRemaining(Loan $loan, string $method = 'reducing'): int
    {
        return DB::transaction(function () use ($loan, $method) {
            $loan->loadMissing('product');

            $principal = (float) $loan->outstanding_balance;
            $tenureMonths = (int) ($loan->tenure_months ?? 0);
            $monthlyRate = (float) ($loan->interest_rate ?? 0);
            $cadence = $loan->product->repayment_cadence ?? 'weekly';

            if ($principal <= 0 || $tenureMonths <= 0) {
                return 0;
            }

            RepaymentSchedule::query()
                ->where('loan_id', $loan->id)
                ->whereNotIn('status', ['paid'])
                ->delete();

            $lastPaid = RepaymentSchedule::query()
                ->where('loan_id', $loan->id)
                ->where('status', 'paid')
                ->orderByDesc('due_date')
                ->first();

            $start = $lastPaid?->due_date
                ? Carbon::parse($lastPaid->due_date)->add($cadence === 'monthly' ? '1 month' : '1 week')
                : ($loan->next_due_date ? Carbon::parse($loan->next_due_date) : Carbon::now());

            $offset = (int) RepaymentSchedule::query()
                ->where('loan_id', $loan->id)
                ->max('installment_no');

            $rows = $this->buildSchedule($principal, $monthlyRate, $tenureMonths, $cadence, $start, $method);

            foreach ($rows as $index => $row) {
                RepaymentSchedule::create([
                    'loan_id'        => $loan->id,
                    'installment_no' => $offset + $index + 1,
                    'due_date'       => $row['due_date'],
                    'principal_due'  => $row['principal_due'],
                    'interest_due'   => $row['interest_due'],
                    'total_due'      => $row['total_due'],
                    'amount_paid'    => 0,
                    'status'         => 'pending',
                ]);
            }

            $first = $rows[0] ?? null;
            $last = $rows[array_key_last($rows)] ?? null;
            $loan->update([
                'next_due_date' => $first ? $first['due_date'] : $loan->next_due_date,
                'maturity_date' => $last ? $last['due_date'] : $loan->maturity_date,
                'status'        => $loan->status === 'restructuring' ? 'active' : $loan->status,
            ]);

            return count($rows);
        });
    }

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

            $loan->loadMissing('product');

            $principal = (float) ($loan->approved_amount ?? $loan->principal_amount ?? 0);
            $tenureMonths = (int) ($loan->tenure_months ?? 0);
            $monthlyRate = (float) ($loan->interest_rate ?? 0);
            $cadence = $loan->product->repayment_cadence ?? 'weekly';
            $disbursement = $loan->disbursement_date
                ? Carbon::parse($loan->disbursement_date)
                : Carbon::now();
            $commencementDays = app(OfferSettingsService::class)->repaymentCommencementDays();

            if ($principal <= 0 || $tenureMonths <= 0) {
                return 0;
            }

            $rows = $this->buildSchedule($principal, $monthlyRate, $tenureMonths, $cadence, $disbursement, $method, $commencementDays);

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

            $first = $rows[0] ?? null;
            $last = $rows[array_key_last($rows)] ?? null;
            $loan->update([
                'next_due_date'       => $first ? $first['due_date'] : $loan->next_due_date,
                'maturity_date'       => $last ? $last['due_date'] : $loan->maturity_date,
                'outstanding_balance' => $principal,
            ]);

            return count($rows);
        });
    }

    /**
     * Estimated schedule for offers and pre-disbursement contracts — no calendar dates.
     *
     * @return list<array{installment_no: int, label: string, principal_due: float, interest_due: float, total_due: float}>
     */
    public function previewEstimate(
        float $principal,
        float $monthlyRate,
        int $tenureMonths,
        string $cadence = 'weekly',
        string $method = 'reducing',
    ): array {
        $periods = $this->periodCount($tenureMonths, $cadence);
        $periodRate = $cadence === 'weekly' ? ($monthlyRate / 4) : $monthlyRate;
        $start = Carbon::today();

        $rows = $method === 'flat'
            ? $this->flat($principal, $periodRate, $periods, $start, $cadence, 0)
            : $this->reducing($principal, $periodRate, $periods, $start, $cadence, 0);

        return array_map(function (array $row) use ($cadence) {
            return [
                'installment_no' => $row['installment_no'],
                'label'          => $cadence === 'weekly'
                    ? 'Week '.$row['installment_no']
                    : 'Month '.$row['installment_no'],
                'principal_due'  => $row['principal_due'],
                'interest_due'   => $row['interest_due'],
                'total_due'      => $row['total_due'],
            ];
        }, $rows);
    }

    /**
     * Build a preview schedule for offer letters and contracts.
     *
     * @return list<array{installment_no: int, due_date: string, principal_due: float, interest_due: float, total_due: float, label: string}>
     */
    public function preview(
        float $principal,
        float $monthlyRate,
        int $tenureMonths,
        string $cadence = 'weekly',
        ?Carbon $start = null,
        string $method = 'reducing',
    ): array {
        return $this->previewEstimate($principal, $monthlyRate, $tenureMonths, $cadence, $method);
    }

    /**
     * @return list<array{installment_no: int, due_date: string, principal_due: float, interest_due: float, total_due: float, label: string}>
     */
    private function buildSchedule(
        float $principal,
        float $monthlyRate,
        int $tenureMonths,
        string $cadence,
        Carbon $disbursementDate,
        string $method,
        int $commencementDays = 0,
    ): array {
        $periods = $this->periodCount($tenureMonths, $cadence);
        $periodRate = $cadence === 'weekly' ? ($monthlyRate / 4) : $monthlyRate;

        $rows = $method === 'flat'
            ? $this->flat($principal, $periodRate, $periods, $disbursementDate, $cadence, $commencementDays)
            : $this->reducing($principal, $periodRate, $periods, $disbursementDate, $cadence, $commencementDays);

        return array_map(function (array $row) use ($cadence) {
            $row['label'] = $cadence === 'weekly'
                ? 'Week '.$row['installment_no']
                : 'Month '.$row['installment_no'];

            return $row;
        }, $rows);
    }

    public function periodCount(int $tenureMonths, string $cadence): int
    {
        return $cadence === 'monthly' ? $tenureMonths : max(1, $tenureMonths * 4);
    }

    public function installmentLabel(string $cadence): string
    {
        return $cadence === 'monthly' ? 'Monthly instalment' : 'Weekly instalment';
    }

    private function reducing(float $principal, float $rate, int $tenure, Carbon $disbursementDate, string $cadence, int $commencementDays = 0): array
    {
        $rows = [];
        if ($rate <= 0) {
            return $this->flat($principal, 0.0, $tenure, $disbursementDate, $cadence, $commencementDays);
        }

        $pow = pow(1 + $rate, $tenure);
        $instalment = $principal * $rate * $pow / ($pow - 1);
        $instalment = round($instalment, 2);

        $balance = $principal;
        for ($i = 1; $i <= $tenure; $i++) {
            $interest = round($balance * $rate, 2);
            $principalPart = round($instalment - $interest, 2);

            if ($i === $tenure) {
                $principalPart = round($balance, 2);
                $instalment = round($principalPart + $interest, 2);
            }

            $balance = round($balance - $principalPart, 2);

            $rows[] = [
                'installment_no' => $i,
                'due_date'       => $this->dueDate($disbursementDate, $i, $cadence, $commencementDays)->toDateString(),
                'principal_due'  => $principalPart,
                'interest_due'   => $interest,
                'total_due'      => $instalment,
            ];
        }

        return $rows;
    }

    private function flat(float $principal, float $rate, int $tenure, Carbon $disbursementDate, string $cadence, int $commencementDays = 0): array
    {
        $rows = [];
        $periodPrincipal = round($principal / $tenure, 2);
        $periodInterest = round($principal * $rate, 2);

        $accPrincipal = 0;
        for ($i = 1; $i <= $tenure; $i++) {
            $p = $periodPrincipal;
            if ($i === $tenure) {
                $p = round($principal - $accPrincipal, 2);
            }
            $accPrincipal += $p;
            $rows[] = [
                'installment_no' => $i,
                'due_date'       => $this->dueDate($disbursementDate, $i, $cadence, $commencementDays)->toDateString(),
                'principal_due'  => $p,
                'interest_due'   => $periodInterest,
                'total_due'      => round($p + $periodInterest, 2),
            ];
        }

        return $rows;
    }

    private function dueDate(Carbon $disbursementDate, int $installmentNo, string $cadence, int $commencementDays = 0): Carbon
    {
        $firstDue = $disbursementDate->copy()->addDays($commencementDays);

        if ($installmentNo <= 1) {
            return $firstDue;
        }

        return $cadence === 'monthly'
            ? $firstDue->copy()->addMonthsNoOverflow($installmentNo - 1)
            : $firstDue->copy()->addWeeks($installmentNo - 1);
    }
}
