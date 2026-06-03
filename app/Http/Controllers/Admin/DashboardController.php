<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Services\CapitalPartnerMetricsService;
use App\Services\LoanApplicationDraftService;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $capital = app(CapitalPartnerMetricsService::class)->platformSummary();

        $stats = [
            'customers'              => Customer::query()->count(),
            'applications'           => LoanApplication::query()->count(),
            'incomplete_applications'=> app(LoanApplicationDraftService::class)->countIncomplete(),
            'active_loans'           => Loan::query()->where('status', 'active')->count(),
            'portfolio_tzs'          => (float) Loan::query()->where('status', 'active')->sum('principal_amount'),
            'capital_available'      => $capital['capital_available'],
            'capital_utilized'       => $capital['capital_utilized'],
            'capital_invested'       => $capital['capital_invested'],
            'loans_funded'           => $capital['loans_funded'],
            'interest_total'         => $capital['interest_earned_total'],
            'company_share'          => $capital['interest_earned_company'],
            'partner_share'          => $capital['interest_earned_partner'],
            'outstanding_exposure'   => $capital['outstanding_exposure'],
        ];

        $recentApplications = LoanApplication::query()
            ->with('customer')
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentApplications', 'capital'));
    }
}
