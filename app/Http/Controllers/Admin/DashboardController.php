<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanTopUpRequest;
use App\Models\RestructureRequest;
use App\Services\CapitalPartnerMetricsService;
use App\Services\LoanApplicationDraftService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $capital = app(CapitalPartnerMetricsService::class)->platformSummary();

        $stageKeys = ['submitted', 'screening', 'credit_appraisal', 'pre_approval', 'approval', 'disbursement'];
        $stageCountsRaw = LoanApplication::query()
            ->selectRaw('current_stage, COUNT(*) as aggregate')
            ->whereIn('current_stage', $stageKeys)
            ->groupBy('current_stage')
            ->pluck('aggregate', 'current_stage');

        $stageCounts = collect($stageKeys)->mapWithKeys(
            fn (string $stage) => [$stage => (int) ($stageCountsRaw[$stage] ?? 0)]
        )->all();

        $stats = [
            'customers'              => Customer::query()->count(),
            'applications'           => LoanApplication::query()->count(),
            'incomplete_applications'=> app(LoanApplicationDraftService::class)->countIncomplete(),
            'active_loans'           => Loan::query()->where('status', 'active')->count(),
            'portfolio_tzs'          => (float) Loan::query()->where('status', 'active')->sum('principal_amount'),
            'credit_review_queue'    => (int) ($stageCounts['screening'] ?? 0) + (int) ($stageCounts['credit_appraisal'] ?? 0),
            'committee_queue'        => (int) ($stageCounts['pre_approval'] ?? 0),
            'my_assigned_queue'      => LoanApplication::query()
                ->where('assigned_analyst_id', auth()->id())
                ->whereNotIn('status', ['rejected', 'withdrawn', 'cancelled'])
                ->whereNotIn('current_stage', ['disbursement', 'rejected'])
                ->count(),
            'capital_available'      => $capital['capital_available'],
            'capital_utilized'       => $capital['capital_utilized'],
            'capital_invested'       => $capital['capital_invested'],
            'loans_funded'           => $capital['loans_funded'],
            'interest_total'         => $capital['interest_earned_total'],
            'company_share'          => $capital['interest_earned_company'],
            'partner_share'          => $capital['interest_earned_partner'],
            'outstanding_exposure'   => $capital['outstanding_exposure'],
            'pending_restructures'   => RestructureRequest::where('status', 'pending')->count(),
            'pending_top_ups'        => LoanTopUpRequest::where('status', 'pending')->count(),
            'approved_top_ups'       => LoanTopUpRequest::where('status', 'approved')->whereNull('disbursed_at')->count(),
            'stage_counts'           => $stageCounts,
            'decisions_30d'          => $this->decisionCounts(30),
            'submissions_14d'        => $this->dailySubmissionSeries(14),
            'disbursements_14d'      => $this->dailyDisbursementSeries(14),
            'portfolio_status'       => $this->portfolioStatusCounts(),
        ];

        $recentApplications = LoanApplication::query()
            ->with(['customer', 'product', 'assignedAnalyst'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentApplications', 'capital'));
    }

    /** @return array{approved: int, rejected: int, withdrawn: int} */
    private function decisionCounts(int $days): array
    {
        $since = now()->subDays($days)->startOfDay();

        $rows = LoanApplication::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->whereIn('status', ['approved', 'rejected', 'withdrawn'])
            ->where('updated_at', '>=', $since)
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'approved'  => (int) ($rows['approved'] ?? 0),
            'rejected'  => (int) ($rows['rejected'] ?? 0),
            'withdrawn' => (int) ($rows['withdrawn'] ?? 0),
        ];
    }

    /**
     * @return list<array{date: string, label: string, count: int}>
     */
    private function dailySubmissionSeries(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $raw = LoanApplication::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->where('created_at', '>=', $start)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('aggregate', 'day');

        return $this->fillDailySeries($days, $raw);
    }

    /**
     * @return list<array{date: string, label: string, count: int}>
     */
    private function dailyDisbursementSeries(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $raw = Loan::query()
            ->selectRaw('DATE(disbursement_date) as day, COUNT(*) as aggregate')
            ->whereNotNull('disbursement_date')
            ->where('disbursement_date', '>=', $start->toDateString())
            ->groupBy(DB::raw('DATE(disbursement_date)'))
            ->pluck('aggregate', 'day');

        return $this->fillDailySeries($days, $raw);
    }

    /** @return array{active: int, arrears: int, closed: int} */
    private function portfolioStatusCounts(): array
    {
        $rows = Loan::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->whereIn('status', ['active', 'arrears', 'closed', 'disbursed'])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'active'  => (int) ($rows['active'] ?? 0) + (int) ($rows['disbursed'] ?? 0),
            'arrears' => (int) ($rows['arrears'] ?? 0),
            'closed'  => (int) ($rows['closed'] ?? 0),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int|string>  $raw
     * @return list<array{date: string, label: string, count: int}>
     */
    private function fillDailySeries(int $days, $raw): array
    {
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $key = $day->toDateString();
            $series[] = [
                'date'  => $key,
                'label' => $day->format('d M'),
                'count' => (int) ($raw[$key] ?? 0),
            ];
        }

        return $series;
    }
}
