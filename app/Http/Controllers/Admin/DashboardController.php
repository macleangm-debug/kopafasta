<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $stats = [
            'customers'      => Customer::query()->count(),
            'applications'   => LoanApplication::query()->count(),
            'active_loans'   => Loan::query()->where('status', 'active')->count(),
            'portfolio_tzs'  => (float) Loan::query()->where('status', 'active')->sum('principal_amount'),
        ];

        $recentApplications = LoanApplication::query()
            ->with('customer')
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentApplications'));
    }
}
