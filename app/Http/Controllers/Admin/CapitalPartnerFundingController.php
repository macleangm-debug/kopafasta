<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CapitalPartnerAllocationService;
use App\Services\CapitalPartnerMetricsService;

class CapitalPartnerFundingController extends Controller
{
    public function __invoke(CapitalPartnerMetricsService $metrics)
    {
        return view('admin.capital-funding.index', [
            'summary'           => $metrics->platformSummary(),
            'partners'          => $metrics->partnersOverview(),
            'recentAllocations' => $metrics->recentAllocations(),
            'partnerSharePct'   => CapitalPartnerAllocationService::PARTNER_INTEREST_SHARE,
            'companySharePct'   => CapitalPartnerAllocationService::COMPANY_INTEREST_SHARE,
        ]);
    }
}
