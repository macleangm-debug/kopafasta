<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArrearCase;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanFee;
use App\Models\Repayment;
use App\Services\ActiveLoanServicingService;
use App\Services\CapitalPartnerAllocationService;
use App\Services\LoanBalanceService;
use Illuminate\View\View;

class LoanReportsController extends Controller
{
    public function portfolio(LoanBalanceService $balances): View
    {
        $loans = Loan::query()
            ->with(['customer', 'product'])
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring', 'defaulted'])
            ->orderByDesc('disbursement_date')
            ->get();

        $rows = $loans->map(function (Loan $loan) use ($balances) {
            $breakdown = $balances->breakdown($loan);

            return [
                'loan'        => $loan,
                'customer'    => $loan->customer,
                'breakdown'   => $breakdown,
            ];
        });

        $totals = [
            'count'       => $rows->count(),
            'principal'   => (float) $rows->sum(fn ($r) => $r['breakdown']['principal_outstanding']),
            'outstanding' => (float) $rows->sum(fn ($r) => $r['breakdown']['total_outstanding']),
        ];

        return view('admin.reports.portfolio', compact('rows', 'totals'));
    }

    public function arrears(): View
    {
        $cases = ArrearCase::query()
            ->with(['loan.customer', 'loan.product', 'assignee'])
            ->whereIn('status', ['open', 'escalated'])
            ->orderByDesc('days_past_due')
            ->get();

        $totals = [
            'cases'   => $cases->count(),
            'amount'  => (float) $cases->sum('amount_in_arrears'),
            'penalty' => (float) $cases->sum('penalty_amount'),
        ];

        return view('admin.reports.arrears', compact('cases', 'totals'));
    }

    public function disbursements(): View
    {
        $rows = Disbursement::query()
            ->with(['loan.customer', 'loan.product'])
            ->where('status', 'released')
            ->latest('released_at')
            ->limit(200)
            ->get();

        $totals = [
            'count'  => $rows->count(),
            'amount' => (float) $rows->sum('amount'),
        ];

        return view('admin.reports.disbursements', compact('rows', 'totals'));
    }

    public function applications(): View
    {
        $rows = LoanApplication::query()
            ->with(['customer', 'product'])
            ->latest()
            ->limit(200)
            ->get();

        $counts = [
            'total'    => LoanApplication::count(),
            'approved' => LoanApplication::where('status', 'approved')->count(),
            'pending'  => LoanApplication::whereIn('status', ['submitted', 'under_review', 'pending'])->count(),
            'rejected' => LoanApplication::where('status', 'rejected')->count(),
        ];

        return view('admin.reports.applications', compact('rows', 'counts'));
    }

    public function financeSummary(CapitalPartnerAllocationService $capital): View
    {
        $feesCollected = (float) LoanFee::query()->whereNotNull('paid_at')->sum('computed_amount');
        $interestIncome = (float) Repayment::query()->whereIn('status', ['received', 'allocated'])->sum('interest_component');
        $outstanding = (float) Loan::query()
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring', 'defaulted'])
            ->sum('outstanding_balance');

        $partnerExposure = (float) \App\Models\LoanCapitalAllocation::query()->sum('outstanding_exposure');

        return view('admin.reports.finance-summary', [
            'feesCollected'     => $feesCollected,
            'interestIncome'    => $interestIncome,
            'outstanding'       => $outstanding,
            'partnerExposure'   => $partnerExposure,
            'partnerSharePct'   => $capital->partnerInterestSharePercent(),
        ]);
    }

    public function collectionsPerformance(): View
    {
        $arrears = ArrearCase::query()->whereIn('status', ['open', 'escalated'])->get();
        $defaulted = Loan::query()->where('status', 'defaulted')->count();
        $writtenOff = Loan::query()->where('status', 'written_off')->count();

        $aging = [
            '1_30'   => $arrears->whereBetween('days_past_due', [1, 30])->count(),
            '31_60'  => $arrears->whereBetween('days_past_due', [31, 60])->count(),
            '61_90'  => $arrears->whereBetween('days_past_due', [61, 90])->count(),
            '90plus' => $arrears->where('days_past_due', '>', 90)->count(),
        ];

        return view('admin.reports.collections-performance', compact('arrears', 'defaulted', 'writtenOff', 'aging'));
    }

    public function repayments(): View
    {
        $rows = Repayment::query()
            ->with(['loan.customer', 'loan.product'])
            ->whereIn('status', ['received', 'allocated'])
            ->latest('paid_at')
            ->limit(200)
            ->get();

        $totals = [
            'count'     => $rows->count(),
            'amount'    => (float) $rows->sum('amount'),
            'principal' => (float) $rows->sum('principal_component'),
            'interest'  => (float) $rows->sum('interest_component'),
            'penalty'   => (float) $rows->sum('penalty_component'),
        ];

        return view('admin.reports.repayments', compact('rows', 'totals'));
    }

    public function par(ActiveLoanServicingService $servicing): View
    {
        $loans = Loan::query()
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring', 'defaulted'])
            ->get();

        $portfolio = 0.0;
        $atRisk = [
            1   => 0.0,
            7   => 0.0,
            30  => 0.0,
            60  => 0.0,
            90  => 0.0,
            180 => 0.0,
        ];

        foreach ($loans as $loan) {
            $metrics = $servicing->forLoan($loan);
            $outstanding = (float) ($metrics['outstanding_balance'] ?? 0);
            $portfolio += $outstanding;
            $dpd = (int) ($metrics['days_past_due'] ?? 0);

            foreach (array_keys($atRisk) as $bucket) {
                if ($dpd >= $bucket) {
                    $atRisk[$bucket] += $outstanding;
                }
            }
        }

        $rates = [];
        foreach ($atRisk as $days => $amount) {
            $rates[$days] = $portfolio > 0 ? round(($amount / $portfolio) * 100, 2) : 0.0;
        }

        return view('admin.reports.par', [
            'portfolio' => $portfolio,
            'loanCount' => $loans->count(),
            'atRisk'    => $atRisk,
            'rates'     => $rates,
        ]);
    }
}
