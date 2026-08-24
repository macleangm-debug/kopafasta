<?php

namespace App\Services\Grades;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Carbon;

class GradeFactCollector
{
    public function collect(Customer $customer): array
    {
        $loans = Loan::query()->where('customer_id', $customer->id)->get();
        $loanIds = $loans->pluck('id')->filter()->all() ?: [0];
        $schedules = RepaymentSchedule::query()->whereIn('loan_id', $loanIds)->get();
        $repayments = Repayment::query()->whereIn('loan_id', $loanIds)->get();
        $rules = app(GradeSettings::class)->rules();
        $minPrincipal = (float) ($rules['integrity']['min_qualifying_principal'] ?? 0);
        $minAgeDays = (int) ($rules['integrity']['min_facility_age_days'] ?? 0);
        $lookback = (int) ($rules['lookback_months'] ?? 12);
        $recentFrom = now()->subMonths($lookback);

        $completed = $loans->filter(fn (Loan $loan) => in_array((string) $loan->status, ['closed', 'settled', 'paid'], true));
        $active = $loans->filter(fn (Loan $loan) => in_array((string) $loan->status, ['active', 'disbursed', 'arrears', 'restructuring'], true));
        $defaulted = $loans->filter(fn (Loan $loan) => in_array((string) $loan->status, ['defaulted', 'written_off'], true));

        $qualifying = $completed->filter(function (Loan $loan) use ($minPrincipal, $minAgeDays) {
            if ((float) ($loan->principal_amount ?? 0) < $minPrincipal) {
                return false;
            }
            $start = $loan->disbursement_date ?? $loan->created_at;
            $end = $loan->closed_at ?? now();

            return ! ($start && $minAgeDays > 0 && Carbon::parse($start)->diffInDays(Carbon::parse($end)) < $minAgeDays);
        });

        $onTime = $late = $onTimeRecent = $lateRecent = $maxDpd = $maxDpdRecent = $currentDpd = $openOverdue = $streak = $early = $cured = 0;
        $overdueAmount = 0.0;

        foreach ($schedules as $row) {
            $due = Carbon::parse($row->due_date);
            $paid = (float) ($row->amount_paid ?? 0);
            $owed = (float) ($row->total_due ?? 0);
            $outstanding = max(0, $owed - $paid);
            $paidAt = $row->paid_at ? Carbon::parse($row->paid_at) : null;
            $recent = $due->gte($recentFrom);

            if ($outstanding > 0.009 && $due->lt(now()->startOfDay())) {
                $dpd = $due->diffInDays(now());
                $openOverdue++;
                $overdueAmount += $outstanding;
                $currentDpd = max($currentDpd, $dpd);
                $maxDpd = max($maxDpd, $dpd);
                if ($recent) {
                    $maxDpdRecent = max($maxDpdRecent, $dpd);
                    $lateRecent++;
                }
                $late++;
                $streak = 0;
                continue;
            }

            if ($paidAt) {
                $dpd = $paidAt->startOfDay()->gt($due->startOfDay()) ? $due->diffInDays($paidAt) : 0;
                $maxDpd = max($maxDpd, $dpd);
                if ($recent) {
                    $maxDpdRecent = max($maxDpdRecent, $dpd);
                }
                if ($dpd > 0) {
                    $late++;
                    $cured++;
                    $streak = 0;
                    if ($recent) {
                        $lateRecent++;
                    }
                } else {
                    $onTime++;
                    $streak++;
                    if ($recent) {
                        $onTimeRecent++;
                    }
                    if ($paidAt->lt($due)) {
                        $early++;
                    }
                }
            }
        }

        $all = $onTime + $late;
        $recentAll = $onTimeRecent + $lateRecent;
        $lifetimeRatio = $all > 0 ? round(($onTime / $all) * 100, 2) : 100.0;
        $recentRatio = $recentAll > 0 ? round(($onTimeRecent / $recentAll) * 100, 2) : $lifetimeRatio;
        $effective = round(($recentRatio * (float) ($rules['recent_weight'] ?? 0.6)) + ($lifetimeRatio * (float) ($rules['lifetime_weight'] ?? 0.4)), 2);
        $created = $customer->onboarded_at ?? $customer->created_at;
        $reversals = $repayments->filter(fn (Repayment $r) => in_array((string) $r->status, ['reversed', 'refunded'], true))->count();
        $repaid = (float) $repayments->reject(fn (Repayment $r) => in_array((string) $r->status, ['reversed', 'failed', 'cancelled'], true))->sum('amount');
        $abandoned = class_exists(LoanApplication::class)
            ? LoanApplication::query()->where('customer_id', $customer->id)->whereIn('status', ['withdrawn', 'cancelled', 'expired'])->count()
            : 0;
        $tiny = $completed->filter(fn (Loan $loan) => (float) $loan->principal_amount < $minPrincipal)->count();
        $rapid = $completed->filter(function (Loan $loan) use ($minAgeDays) {
            $start = $loan->disbursement_date ?? $loan->created_at;
            $end = $loan->closed_at ?? now();

            return $start && Carbon::parse($start)->diffInDays(Carbon::parse($end)) < max(7, (int) ($minAgeDays / 3));
        })->count();
        $activeMonths = $loans->pluck('disbursement_date')->filter()
            ->merge($repayments->pluck('paid_at')->filter())
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m'))
            ->unique()
            ->count();

        return [
            'completed_facilities' => $completed->count(),
            'qualifying_completed_facilities' => $qualifying->count(),
            'lifetime_principal_borrowed' => (float) $qualifying->sum('principal_amount'),
            'largest_completed_facility' => (float) ($qualifying->max('principal_amount') ?: 0),
            'lifetime_amount_repaid' => $repaid,
            'on_time_payment_ratio' => $lifetimeRatio,
            'recent_on_time_ratio' => $recentRatio,
            'effective_on_time_ratio' => $effective,
            'late_payment_ratio' => $all > 0 ? round(($late / $all) * 100, 2) : 0.0,
            'max_days_past_due' => $maxDpd,
            'max_days_past_due_recent' => $maxDpdRecent,
            'current_days_past_due' => $currentDpd,
            'open_overdue_count' => $openOverdue,
            'current_overdue_amount' => $overdueAmount,
            'overdues_cured_count' => $cured,
            'relationship_days' => $created ? Carbon::parse($created)->diffInDays(now()) : 0,
            'active_relationship_months' => $activeMonths,
            'on_time_payment_streak' => $streak,
            'facilities_repaid_early_count' => $early,
            'restructured_facilities_count' => $loans->where('status', 'restructuring')->count(),
            'defaulted_facilities_count' => $defaulted->count(),
            'current_outstanding_principal' => (float) $active->sum(fn (Loan $loan) => (float) ($loan->outstanding_balance ?? 0)),
            'concurrent_facility_count' => $active->count(),
            'facilities_opened_recently' => $loans->filter(fn (Loan $loan) => ($loan->disbursement_date ?? $loan->created_at) && Carbon::parse($loan->disbursement_date ?? $loan->created_at)->gte(now()->subDays(30)))->count(),
            'verified_profile_score' => $this->profileScore($customer),
            'tiny_completed_facilities' => $tiny,
            'rapid_cycle_count' => $rapid,
            'reversed_payments_count' => $reversals,
            'abandoned_applications_count' => $abandoned,
            'active_relationship_months' => $activeMonths,
            'tiny_completed_facilities' => $tiny,
            'rapid_cycle_count' => $rapid,
            'reversed_payments_count' => $reversals,
        ];
    }

    private function profileScore(Customer $customer): int
    {
        $score = 0;
        if (($customer->nida_verification_status ?? null) === 'verified') {
            $score += 40;
        } elseif (filled($customer->national_id)) {
            $score += 20;
        }
        if (filled($customer->phone)) {
            $score += 20;
        }
        if (in_array((string) ($customer->face_verification_status ?? ''), ['verified', 'approved'], true)) {
            $score += 20;
        }
        if (filled($customer->region) || filled($customer->district)) {
            $score += 20;
        }

        return min(100, $score);
    }
}
