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
        ];

        $recentApplications = LoanApplication::query()
            ->with(['customer', 'product', 'assignedAnalyst'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentApplications', 'capital'));
    }
}
