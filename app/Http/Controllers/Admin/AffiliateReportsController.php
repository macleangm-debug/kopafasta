<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AffiliateCapitalAttributionReportService;
use App\Services\AffiliateFraudDetectionService;
use App\Services\AffiliateMarketingAttributionReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateReportsController extends Controller
{
    public function marketingAttribution(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $report = app(AffiliateMarketingAttributionReportService::class)->report($from, $to);

        return view('admin.reports.affiliate-marketing-attribution', compact('report', 'from', 'to'));
    }

    public function capitalAttribution(Request $request): View
    {
        [$from, $to] = $this->dateRange($request, defaultDays: 365);

        $service = app(AffiliateCapitalAttributionReportService::class);
        $report = $service->report($from, $to);
        $summary = $service->summaryByAffiliate($from, $to);

        return view('admin.reports.affiliate-capital-attribution', compact('report', 'summary', 'from', 'to'));
    }

    public function fraudOverview(): View
    {
        $fraud = app(AffiliateFraudDetectionService::class);

        return view('admin.reports.affiliate-fraud', [
            'flagged' => $fraud->flaggedAffiliates(limit: 100),
            'counts'  => [
                'medium'  => $fraud->flaggedAffiliates(AffiliateFraudDetectionService::FLAG_MEDIUM, 1000)->count(),
                'high'    => $fraud->flaggedAffiliates(AffiliateFraudDetectionService::FLAG_HIGH, 1000)->count(),
                'blocked' => $fraud->flaggedAffiliates(AffiliateFraudDetectionService::FLAG_BLOCKED, 1000)->count(),
            ],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function dateRange(Request $request, int $defaultDays = 30): array
    {
        $from = filled($request->query('from'))
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->subDays($defaultDays)->startOfDay();

        $to = filled($request->query('to'))
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
