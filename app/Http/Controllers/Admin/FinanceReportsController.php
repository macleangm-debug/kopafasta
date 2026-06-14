<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Services\GeneralLedgerService;
use App\Services\LoanBalanceService;
use Illuminate\Support\Carbon;

class FinanceReportsController extends Controller
{
    // -------- Trial balance --------
    public function trialBalance(GeneralLedgerService $ledger)
    {
        $rows = $ledger->trialBalanceRows();
        $totalDebit = (float) $rows->sum('debit');
        $totalCredit = (float) $rows->sum('credit');

        return view('admin.reports.trial-balance', compact('rows', 'totalDebit', 'totalCredit'));
    }

    // -------- Income statement --------
    public function incomeStatement()
    {
        $start = now()->startOfYear();

        $interestIncome = (float) Repayment::where('paid_at', '>=', $start)->sum('interest_component');
        $penaltyIncome  = (float) Repayment::where('paid_at', '>=', $start)->sum('penalty_component');
        $feeIncome      = 0.0;
        $otherIncome    = 0.0;

        $totalIncome = $interestIncome + $penaltyIncome + $feeIncome + $otherIncome;

        $expenses = Expense::where('expense_date', '>=', $start)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $totalExpense = (float) $expenses->sum();
        $net = $totalIncome - $totalExpense;

        return view('admin.reports.income-statement', compact(
            'interestIncome', 'penaltyIncome', 'feeIncome', 'otherIncome', 'totalIncome',
            'expenses', 'totalExpense', 'net'
        ));
    }

    // -------- Balance sheet --------
    public function balanceSheet(GeneralLedgerService $ledger, LoanBalanceService $balances)
    {
        $byType = $ledger->balancesByType();
        $loansOutstanding = (float) Loan::query()
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring', 'defaulted'])
            ->get()
            ->sum(fn (Loan $loan) => $balances->breakdown($loan)['total_outstanding']);

        $loansReceivableGl = $ledger->accountBalanceByCode('1100');

        return view('admin.reports.balance-sheet', [
            'assets'             => (float) ($byType['asset'] ?? 0),
            'liabilities'        => (float) ($byType['liability'] ?? 0),
            'equity'             => (float) ($byType['equity'] ?? 0),
            'loansOutstanding'   => $loansOutstanding,
            'loansReceivableGl'  => $loansReceivableGl,
        ]);
    }

    // -------- Cash flow --------
    public function cashFlow()
    {
        $start = now()->startOfMonth();

        $inflows  = (float) Repayment::where('paid_at', '>=', $start)->sum('amount');
        $outflows = (float) Disbursement::where('released_at', '>=', $start)->sum('amount')
                  + (float) Expense::where('expense_date', '>=', $start)->sum('amount');

        $net = $inflows - $outflows;

        return view('admin.reports.cash-flow', compact('inflows', 'outflows', 'net'));
    }

    // -------- NPL (Non-Performing Loans) report --------
    public function npl()
    {
        $today = Carbon::today();

        $buckets = [
            'current' => ['label' => 'Current',         'min' => -9999, 'max' => 0,    'count' => 0, 'amount' => 0.0],
            '1_30'    => ['label' => '1-30 days',       'min' => 1,     'max' => 30,   'count' => 0, 'amount' => 0.0],
            '31_60'   => ['label' => '31-60 days',      'min' => 31,    'max' => 60,   'count' => 0, 'amount' => 0.0],
            '61_90'   => ['label' => '61-90 days',      'min' => 61,    'max' => 90,   'count' => 0, 'amount' => 0.0],
            '90_plus' => ['label' => '90+ days (NPL)',  'min' => 91,    'max' => 99999,'count' => 0, 'amount' => 0.0],
        ];

        $loans = Loan::whereIn('status', ['active', 'arrears', 'restructured'])
            ->whereNotNull('next_due_date')
            ->get(['id', 'outstanding_balance', 'next_due_date', 'loan_product_id']);

        $totalOutstanding = 0.0;
        $nplOutstanding   = 0.0;
        $byProduct = [];

        foreach ($loans as $l) {
            $days = Carbon::parse($l->next_due_date)->diffInDays($today, false);
            $amt  = (float) $l->outstanding_balance;
            $totalOutstanding += $amt;

            foreach ($buckets as $key => &$b) {
                if ($days >= $b['min'] && $days <= $b['max']) {
                    $b['count']++;
                    $b['amount'] += $amt;
                    if ($key === '90_plus') {
                        $nplOutstanding += $amt;
                        $byProduct[$l->loan_product_id]['count']  = ($byProduct[$l->loan_product_id]['count']  ?? 0) + 1;
                        $byProduct[$l->loan_product_id]['amount'] = ($byProduct[$l->loan_product_id]['amount'] ?? 0) + $amt;
                    }
                    break;
                }
            }
            unset($b);
        }

        $productNames = LoanProduct::pluck('name', 'id');
        $productBreakdown = collect($byProduct)->map(fn ($row, $id) => (object) [
            'name'   => $productNames[$id] ?? "Product #$id",
            'count'  => $row['count'],
            'amount' => $row['amount'],
        ])->values();

        $nplRatio = $totalOutstanding > 0 ? ($nplOutstanding / $totalOutstanding) * 100 : 0.0;
        $writtenOff = (float) Loan::where('status', 'written_off')->sum('outstanding_balance');

        return view('admin.reports.npl', compact(
            'buckets', 'totalOutstanding', 'nplOutstanding', 'nplRatio', 'writtenOff', 'productBreakdown'
        ));
    }

    // -------- Customer report --------
    public function customers()
    {
        $total       = Customer::count();
        $pep         = Customer::where('is_pep', true)->count();
        $blacklisted = Customer::where('is_blacklisted', true)->count();
        $active      = Customer::whereHas('loans', fn ($q) => $q->whereIn('status', ['active','arrears','restructured']))->count();
        $dormant     = max(0, $total - $active);

        $byRisk = Customer::selectRaw('COALESCE(risk_band, "unknown") as band, COUNT(*) as total')
            ->groupBy('band')->pluck('total', 'band');

        $top = Customer::withSum(['loans as exposure' => function ($q) {
                $q->whereIn('status', ['active','arrears','restructured']);
            }], 'outstanding_balance')
            ->orderByDesc('exposure')
            ->limit(20)
            ->get();

        $thisMonth = Customer::where('created_at', '>=', now()->startOfMonth())->count();
        $thisYear  = Customer::where('created_at', '>=', now()->startOfYear())->count();

        return view('admin.reports.customers', compact(
            'total', 'pep', 'blacklisted', 'active', 'dormant', 'byRisk', 'top', 'thisMonth', 'thisYear'
        ));
    }

    // -------- Financial overview --------
    public function financialOverview()
    {
        $start = now()->startOfYear();
        $monthStart = now()->startOfMonth();

        $portfolioOutstanding = (float) Loan::whereIn('status', ['active','arrears','restructured'])->sum('outstanding_balance');
        $activeLoanCount      = Loan::whereIn('status', ['active','arrears','restructured'])->count();
        $disbursedYtd         = (float) Disbursement::where('released_at', '>=', $start)->sum('amount');
        $repaidYtd            = (float) Repayment::where('paid_at', '>=', $start)->sum('amount');
        $interestYtd          = (float) Repayment::where('paid_at', '>=', $start)->sum('interest_component');
        $penaltyYtd           = (float) Repayment::where('paid_at', '>=', $start)->sum('penalty_component');
        $expensesYtd          = (float) Expense::where('expense_date', '>=', $start)->sum('amount');
        $netIncomeYtd         = ($interestYtd + $penaltyYtd) - $expensesYtd;

        $disbursedMonth       = (float) Disbursement::where('released_at', '>=', $monthStart)->sum('amount');
        $repaidMonth          = (float) Repayment::where('paid_at', '>=', $monthStart)->sum('amount');

        $writtenOffYtd        = (float) Loan::where('status', 'written_off')
                                          ->where('updated_at', '>=', $start)->sum('outstanding_balance');

        $totalCustomers       = Customer::count();
        $activeCustomers      = Customer::whereHas('loans', fn ($q) => $q->whereIn('status', ['active','arrears','restructured']))->count();

        return view('admin.reports.financial-overview', compact(
            'portfolioOutstanding', 'activeLoanCount', 'disbursedYtd', 'repaidYtd',
            'interestYtd', 'penaltyYtd', 'expensesYtd', 'netIncomeYtd',
            'disbursedMonth', 'repaidMonth', 'writtenOffYtd',
            'totalCustomers', 'activeCustomers'
        ));
    }
}
