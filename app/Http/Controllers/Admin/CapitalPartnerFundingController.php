<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitalWithdrawalRequest;
use App\Services\CapitalPartnerAllocationService;
use App\Services\CapitalPartnerMetricsService;

class CapitalPartnerFundingController extends Controller
{
    public function index(CapitalPartnerMetricsService $metrics)
    {
        return view('admin.capital-funding.index', [
            'summary'           => $metrics->platformSummary(),
            'partners'          => $metrics->partnersOverview(),
            'recentAllocations' => $metrics->recentAllocations(),
            'partnerSharePct'   => CapitalPartnerAllocationService::PARTNER_INTEREST_SHARE,
            'companySharePct'   => CapitalPartnerAllocationService::COMPANY_INTEREST_SHARE,
        ]);
    }

    public function fundedLoans(CapitalPartnerMetricsService $metrics)
    {
        return view('admin.capital-funding.funded-loans', [
            'loans' => $metrics->fundedLoansReport(150),
        ]);
    }

    public function withdrawals()
    {
        $requests = CapitalWithdrawalRequest::query()
            ->with(['lender', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->paginate(30);

        return view('admin.capital-funding.withdrawals', compact('requests'));
    }
}
