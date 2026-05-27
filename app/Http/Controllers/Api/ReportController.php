<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArrearCase;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function portfolio()
    {
        return response()->json([
            'total_loans' => Loan::count(),
            'active_loans' => Loan::where('status', 'active')->count(),
            'portfolio_value' => Loan::sum('approved_amount'),
            'outstanding_balance' => Loan::sum('outstanding_balance'),
        ]);
    }

    public function disbursement()
    {
        return response()->json([
            'total_disbursements' => Disbursement::count(),
            'released_count' => Disbursement::where('status', 'released')->count(),
            'released_amount' => Disbursement::where('status', 'released')->sum('amount'),
        ]);
    }

    public function repayment()
    {
        return response()->json([
            'repayment_count' => Repayment::count(),
            'collected_amount' => Repayment::sum('amount'),
            'today_collections' => Repayment::whereDate('paid_at', now()->toDateString())->sum('amount'),
        ]);
    }

    public function arrears()
    {
        return response()->json([
            'open_cases' => ArrearCase::where('status', 'open')->count(),
            'arrears_value' => ArrearCase::sum('amount_in_arrears'),
            'penalties' => ArrearCase::sum('penalty_amount'),
        ]);
    }

    public function par()
    {
        $portfolio = (float) Loan::sum('outstanding_balance');
        $arrears = (float) ArrearCase::sum('amount_in_arrears');

        return response()->json([
            'portfolio_at_risk' => $portfolio > 0 ? round(($arrears / $portfolio) * 100, 2) : 0,
            'portfolio_outstanding' => $portfolio,
            'arrears_balance' => $arrears,
        ]);
    }

    public function products()
    {
        $rows = LoanProduct::query()
            ->leftJoin('loans', 'loan_products.id', '=', 'loans.loan_product_id')
            ->groupBy('loan_products.id', 'loan_products.name')
            ->select([
                'loan_products.id',
                'loan_products.name',
                DB::raw('COUNT(loans.id) as loans_count'),
                DB::raw('COALESCE(SUM(loans.approved_amount),0) as approved_total'),
            ])
            ->get();

        return response()->json($rows);
    }

    public function officers()
    {
        $rows = DB::table('users')
            ->select([
                'id',
                'name',
                'role',
                'approval_limit',
                'branch_id',
            ])
            ->whereIn('role', ['officer', 'manager', 'credit_analyst', 'collector'])
            ->orderBy('name')
            ->get();

        return response()->json($rows);
    }

    public function vendors()
    {
        $rows = Vendor::query()
            ->leftJoin('vendor_tasks', 'vendors.id', '=', 'vendor_tasks.vendor_id')
            ->groupBy('vendors.id', 'vendors.name', 'vendors.category')
            ->select([
                'vendors.id',
                'vendors.name',
                'vendors.category',
                DB::raw('COUNT(vendor_tasks.id) as tasks_count'),
                DB::raw("SUM(CASE WHEN vendor_tasks.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks"),
            ])
            ->get();

        return response()->json($rows);
    }

    public function customerRisk()
    {
        $rows = DB::table('customers')
            ->leftJoin('credit_histories', 'customers.id', '=', 'credit_histories.customer_id')
            ->leftJoin('loans', 'customers.id', '=', 'loans.customer_id')
            ->select([
                'customers.id',
                'customers.customer_number',
                'customers.first_name',
                'customers.last_name',
                DB::raw('MAX(credit_histories.score) as latest_score'),
                DB::raw('SUM(loans.outstanding_balance) as outstanding_balance'),
            ])
            ->groupBy('customers.id', 'customers.customer_number', 'customers.first_name', 'customers.last_name')
            ->get();

        return response()->json($rows);
    }
}
