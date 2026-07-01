<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\SuspiciousActivity;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ComplianceController extends Controller
{
    // -------- Bank of Tanzania reports --------
    public function botReports()
    {
        $now = now();
        $startMonth = $now->copy()->startOfMonth();

        $portfolio = [
            'total_outstanding'   => (float) Loan::whereIn('status', ['active','arrears','restructured'])->sum('outstanding_balance'),
            'total_disbursed_ytd' => (float) Loan::where('disbursement_date', '>=', $now->copy()->startOfYear())->sum('approved_amount'),
            'active_loans'        => (int) Loan::whereIn('status', ['active','arrears','restructured'])->count(),
            'closed_loans_ytd'    => (int) Loan::where('status', 'closed')->where('closed_at', '>=', $now->copy()->startOfYear())->count(),
            'written_off_ytd'     => (float) Loan::where('status', 'written_off')->where('updated_at', '>=', $now->copy()->startOfYear())->sum('outstanding_balance'),
        ];

        $par = [
            '1_30'   => $this->parBucket(1, 30),
            '31_60'  => $this->parBucket(31, 60),
            '61_90'  => $this->parBucket(61, 90),
            '90_plus'=> $this->parBucket(91, 99999),
        ];
        $par['total_par'] = array_sum($par);
        $par['par_pct']   = $portfolio['total_outstanding'] > 0 ? round($par['total_par'] * 100 / $portfolio['total_outstanding'], 2) : 0;

        $monthly = [
            'disbursements'  => (float) Loan::where('disbursement_date', '>=', $startMonth)->sum('approved_amount'),
            'repayments'     => (float) Repayment::where('paid_at', '>=', $startMonth)->sum('amount'),
            'interest_income'=> (float) Repayment::where('paid_at', '>=', $startMonth)->sum('interest_component'),
            'penalty_income' => (float) Repayment::where('paid_at', '>=', $startMonth)->sum('penalty_component'),
        ];

        return view('admin.compliance.bot-reports', compact('portfolio', 'par', 'monthly'));
    }

    protected function parBucket(int $minDays, int $maxDays): float
    {
        return (float) Loan::whereIn('status', ['active','arrears'])
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->subDays($minDays)->endOfDay())
            ->where('next_due_date', '>=', now()->subDays($maxDays + 1)->startOfDay())
            ->sum('outstanding_balance');
    }

    // -------- AML reports --------
    public function amlReports()
    {
        $stats = [
            'open'        => SuspiciousActivity::where('status', 'open')->count(),
            'investigating' => SuspiciousActivity::where('status', 'investigating')->count(),
            'reported'    => SuspiciousActivity::where('status', 'reported')->count(),
            'closed'      => SuspiciousActivity::where('status', 'closed')->count(),
        ];
        $bySeverity = SuspiciousActivity::select('severity', DB::raw('count(*) as c'))->groupBy('severity')->pluck('c', 'severity');
        $recent = SuspiciousActivity::with(['customer', 'rule'])->latest()->limit(50)->get();

        return view('admin.compliance.aml-reports', compact('stats', 'bySeverity', 'recent'));
    }

    // -------- KYC reports --------
    public function kycReports()
    {
        $stats = [
            'total_customers' => Customer::count(),
            'verified'        => CustomerKyc::where('status', 'approved')->count(),
            'pending'         => CustomerKyc::where('status', 'pending')->count(),
            'rejected'        => CustomerKyc::where('status', 'rejected')->count(),
            'high_risk'       => Customer::where('risk_band', 'high')->orWhere('risk_band', 'extreme')->count(),
            'pep_flagged'     => Customer::where('is_pep', true)->count(),
            'blacklisted'     => Customer::where('is_blacklisted', true)->count(),
            'dormant_90d'     => Customer::whereDoesntHave('loans', function ($q) {
                $q->where('updated_at', '>=', now()->subDays(90));
            })->count(),
        ];

        return view('admin.compliance.kyc-reports', compact('stats'));
    }

    // -------- Regulatory exports --------
    public function exports()
    {
        return view('admin.compliance.exports', [
            'months' => collect(range(0, 11))->map(fn($i)=>now()->subMonths($i)->format('Y-m')),
        ]);
    }

    // -------- Suspicious activity detail / investigation --------
    public function suspiciousShow(SuspiciousActivity $activity)
    {
        $activity->load(['customer', 'loan', 'rule', 'assignee']);
        return view('admin.compliance.suspicious-show', compact('activity'));
    }

    public function suspiciousUpdate(Request $request, SuspiciousActivity $activity)
    {
        $data = $request->validate([
            'investigator_notes' => ['nullable', 'string'],
            'status'             => ['required', 'in:open,investigating,cleared,reported,closed'],
        ]);

        $activity->update($data + [
            'resolved_at' => in_array($data['status'], ['cleared', 'reported', 'closed'], true)
                ? ($activity->resolved_at ?? now())
                : null,
        ]);

        return back()->with('status', 'Activity updated.');
    }

    // -------- File a SAR (Suspicious Activity Report) PDF --------
    public function fileSar(Request $request, SuspiciousActivity $activity)
    {
        $activity->load(['customer', 'loan', 'rule']);

        $pdf = Pdf::loadView('pdf.sar-report', [
            'activity'  => $activity,
            'generated' => now(),
            'generator' => $request->user(),
        ])->setPaper('a4');

        $activity->update([
            'status'      => 'reported',
            'resolved_at' => $activity->resolved_at ?? now(),
        ]);

        return $pdf->download('SAR-'.$activity->id.'-'.now()->format('Ymd').'.pdf');
    }

    // -------- Large-transaction monitoring (cash-equivalent over threshold) --------
    public function largeTransactions(Request $request)
    {
        $threshold = (float) ($request->query('threshold') ?: $this->largeTxnThreshold());
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $rows = Repayment::query()
            ->with(['loan.customer'])
            ->where('amount', '>=', $threshold)
            ->whereBetween('paid_at', [$from, $to])
            ->orderByDesc('amount')
            ->paginate(50)
            ->withQueryString();

        return view('admin.compliance.large-transactions', compact('rows', 'threshold', 'from', 'to'));
    }

    // -------- BOT portfolio Excel export --------
    public function botPortfolioExport(Request $request)
    {
        $month = $request->query('month')
            ? Carbon::parse($request->query('month').'-01')->startOfMonth()
            : Carbon::now()->startOfMonth();

        $filename = 'BOT-portfolio-'.$month->format('Y-m').'.xlsx';
        return Excel::download(new \App\Exports\BotPortfolioExport($month), $filename);
    }

    private function largeTxnThreshold(): float
    {
        $v = optional(SystemSetting::where('key', 'aml.large_txn_threshold')->first())->value;
        return is_numeric($v) ? (float) $v : 10_000_000.0;
    }

    public function crbAudit(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $billing = app(\App\Services\CrbBillingService::class);
        $report = $billing->auditReport(CarbonImmutable::parse($from), CarbonImmutable::parse($to));

        return view('admin.compliance.crb-audit', [
            'report' => $report,
            'from'   => $from->toDateString(),
            'to'     => $to->toDateString(),
            'cost'   => $billing->costPerRequest(),
        ]);
    }

    public function crbAuditExport(Request $request)
    {
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->input('from'))->startOfDay()
            : CarbonImmutable::now()->startOfMonth();
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->input('to'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $rows = app(\App\Services\CrbBillingService::class)->auditReport($from, $to)['rows'];

        $filename = 'crb-audit-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = static function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Request Date',
                'Request Time',
                'Customer Name',
                'NIDA',
                'Application ID',
                'Group/Individual',
                'Provider',
                'Request Status',
                'Response Status',
                'Cost',
                'Invoice Status',
                'Requested By',
                'Reference Number',
            ]);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['request_date'],
                    $row['request_time'],
                    $row['customer_name'],
                    $row['national_id'],
                    $row['application_id'],
                    $row['application_type'],
                    $row['provider'],
                    $row['request_status'],
                    $row['response_status'],
                    $row['cost'],
                    $row['invoice_status'],
                    $row['requested_by'],
                    $row['reference_number'],
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
