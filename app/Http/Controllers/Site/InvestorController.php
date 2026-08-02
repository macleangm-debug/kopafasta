<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\LenderInvestment;
use App\Models\LenderStatement;
use App\Models\LenderTransaction;
use App\Models\NotificationLog;
use App\Services\PartnerProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InvestorController extends Controller
{
    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    protected function lender(): Lender
    {
        $user = Auth::user();
        $lender = Lender::where('user_id', $user->id)->first();
        if (! $lender) {
            $lender = Lender::create([
                'user_id'           => $user->id,
                'code'              => 'INV-'.strtoupper(Str::random(6)),
                'name'              => $user->name,
                'type'              => 'individual',
                'contact_person'    => $user->name,
                'email'             => $user->email,
                'phone'             => $user->phone,
                'credit_limit'      => 0,
                'available_balance' => 0,
                'risk_preference'   => 'medium',
                'status'            => 'active',
            ]);
        }
        return $lender;
    }

    protected function stats(Lender $lender): array
    {
        $invested      = (float) $lender->investments()->whereIn('status', ['active', 'matured'])->sum('principal');
        $active        = (float) $lender->investments()->where('status', 'active')->sum('principal');
        $returnsPaid   = (float) $lender->transactions()->where('type', 'return')->where('status', 'completed')->sum('amount');
        $returnsExpect = (float) $lender->investments()->where('status', 'active')->sum('return_amount');
        $activeLoans   = $lender->investments()->where('status', 'active')->count();
        $available     = (float) $lender->available_balance;
        $deposited     = (float) $lender->transactions()->where('type', 'deposit')->where('status', 'completed')->sum('amount');
        $withdrawn     = (float) $lender->transactions()->where('type', 'withdrawal')->where('status', 'completed')->sum('amount');
        $portfolioPerf = $invested > 0 ? round($returnsPaid / $invested * 100, 2) : 0.0;
        $defaultRate   = $lender->investments()->count() > 0
            ? round($lender->investments()->where('status', 'defaulted')->count() / $lender->investments()->count() * 100, 2)
            : 0.0;

        return compact(
            'invested', 'active', 'returnsPaid', 'returnsExpect',
            'activeLoans', 'available', 'deposited', 'withdrawn',
            'portfolioPerf', 'defaultRate'
        );
    }

    protected function txReference(string $prefix): string
    {
        return $prefix.'-'.strtoupper(Str::random(8));
    }

    /* ------------------------------------------------------------------ */
    /* Dashboard                                                           */
    /* ------------------------------------------------------------------ */

    public function dashboard()
    {
        $lender = $this->lender();
        $stats  = $this->stats($lender);
        $capitalMetrics = app(\App\Services\CapitalPartnerMetricsService::class)->forLender($lender);

        $recentInvestments = $lender->investments()
            ->with('pool')->latest()->limit(5)->get();

        $recentTx = $lender->transactions()->latest()->limit(6)->get();

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthlyEarnings = LenderTransaction::where('lender_id', $lender->id)
            ->where('type', 'return')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("{$monthExpr} as ym, SUM(amount) as total")
            ->groupBy('ym')->orderBy('ym')->get();

        $notifications = NotificationLog::query()
            ->when(
                Schema::hasColumn('notification_logs', 'user_id'),
                fn ($q) => $q->where('user_id', Auth::id()),
                fn ($q) => $q->where('recipient', Auth::user()?->email)->orWhere('recipient', Auth::user()?->phone)
            )
            ->latest()
            ->limit(4)
            ->get();

        return view('site.investor.dashboard', compact(
            'lender', 'stats', 'capitalMetrics', 'recentInvestments', 'recentTx', 'monthlyEarnings', 'notifications'
        ));
    }

    public function fundedLoans()
    {
        $lender = $this->lender();
        $metrics = app(\App\Services\CapitalPartnerMetricsService::class);
        $capitalMetrics = $metrics->forLender($lender);
        $allocations = $metrics->allocationsForLender($lender, 100)
            ->map(function ($row) {
                $loan = $row->loan;

                return [
                    'loan_number'           => $loan?->loan_number ?? '—',
                    'borrower'              => trim(($loan?->customer?->first_name ?? '').' '.($loan?->customer?->last_name ?? '')) ?: '—',
                    'allocated_principal'   => (float) $row->allocated_principal,
                    'outstanding_exposure'  => (float) $row->outstanding_exposure,
                    'interest_earned_partner' => (float) $row->interest_earned_partner,
                    'interest_earned_company' => (float) $row->interest_earned_company,
                    'partner_share_pct'     => (float) $row->partner_interest_share_percent,
                    'status'                => $loan?->status ?? '—',
                    'disbursement_date'     => $loan?->disbursement_date,
                ];
            });

        return view('site.investor.funded-loans', compact('lender', 'capitalMetrics', 'allocations'));
    }

    /* ------------------------------------------------------------------ */
    /* Funding pools                                                       */
    /* ------------------------------------------------------------------ */

    public function pools(Request $request)
    {
        $risk = $request->string('risk')->toString();
        $type = $request->string('type')->toString();

        $pools = FundingPool::query()
            ->where('is_public', true)
            ->where('status', 'open')
            ->when(in_array($risk, ['low', 'medium', 'high'], true), fn ($q) => $q->where('risk_level', $risk))
            ->when($type, fn ($q) => $q->where('pool_type', $type))
            ->orderByDesc('expected_yield')
            ->paginate(12);

        return view('site.investor.pools', compact('pools', 'risk', 'type'));
    }

    public function pool(FundingPool $pool)
    {
        abort_unless($pool->is_public, 404);
        $lender = $this->lender();
        $stats  = $this->stats($lender);
        return view('site.investor.pool', compact('pool', 'lender', 'stats'));
    }

    public function invest(Request $request, FundingPool $pool)
    {
        abort_unless($pool->is_public && $pool->status === 'open', 422);

        $request->merge(['amount' => \App\Support\MoneyFormat::toNumber($request->input('amount'))]);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $lender = $this->lender();
        $amount = (float) $data['amount'];

        if ($amount < (float) $pool->min_investment) {
            return back()->withErrors(['amount' => 'Minimum investment is '.format_number($pool->min_investment).' TZS.']);
        }
        if ($amount > (float) $lender->available_balance) {
            return back()->withErrors(['amount' => 'Insufficient available balance. Deposit funds first.']);
        }

        DB::transaction(function () use ($lender, $pool, $amount) {
            $matures = $pool->end_date ?: now()->addMonths(6)->toDateString();
            $rate    = (float) $pool->expected_yield;
            $return  = round($amount * ($rate / 100), 2);

            $inv = LenderInvestment::create([
                'lender_id'       => $lender->id,
                'funding_pool_id' => $pool->id,
                'reference'       => $this->txReference('INV'),
                'principal'       => $amount,
                'return_amount'   => $return,
                'return_rate'     => $rate,
                'invested_at'     => now()->toDateString(),
                'matures_at'      => $matures,
                'status'          => 'active',
            ]);

            LenderTransaction::create([
                'lender_id'            => $lender->id,
                'funding_pool_id'      => $pool->id,
                'lender_investment_id' => $inv->id,
                'reference'            => $this->txReference('TXN'),
                'type'                 => 'investment',
                'amount'               => $amount,
                'status'               => 'completed',
                'channel'              => 'system',
                'notes'                => 'Invested in pool '.$pool->name,
                'processed_at'         => now(),
            ]);

            $lender->decrement('available_balance', $amount);
            $pool->increment('amount_deployed', $amount);
        });

        return redirect()->route('site.investor.investments')
            ->with('status', 'Investment placed successfully.');
    }

    /* ------------------------------------------------------------------ */
    /* My investments                                                      */
    /* ------------------------------------------------------------------ */

    public function investments(Request $request)
    {
        $lender = $this->lender();
        $status = $request->string('status')->toString();

        $investments = $lender->investments()
            ->with('pool')
            ->when(in_array($status, ['active', 'matured', 'closed', 'defaulted', 'pending'], true),
                   fn ($q) => $q->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return view('site.investor.investments', compact('investments', 'status'));
    }

    public function investment(LenderInvestment $investment)
    {
        $lender = $this->lender();
        abort_unless($investment->lender_id === $lender->id, 404);
        $investment->load('pool', 'loan');
        $payouts = LenderTransaction::where('lender_investment_id', $investment->id)->latest()->get();
        return view('site.investor.investment', compact('investment', 'payouts'));
    }

    /* ------------------------------------------------------------------ */
    /* Returns & analytics                                                 */
    /* ------------------------------------------------------------------ */

    public function returns()
    {
        $lender = $this->lender();
        $stats  = $this->stats($lender);

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthly = LenderTransaction::where('lender_id', $lender->id)
            ->where('type', 'return')->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("{$monthExpr} as ym, SUM(amount) as total")
            ->groupBy('ym')->orderBy('ym')->get();

        $byPool = $lender->investments()
            ->with('pool')
            ->get()
            ->groupBy(fn ($i) => $i->pool->name ?? 'Unassigned')
            ->map(fn ($items) => [
                'principal' => $items->sum('principal'),
                'returns'   => $items->sum('return_amount'),
                'count'     => $items->count(),
            ]);

        return view('site.investor.returns', compact('stats', 'monthly', 'byPool'));
    }

    public function analytics()
    {
        $lender = $this->lender();
        $stats  = $this->stats($lender);

        $diversification = $lender->investments()
            ->with('pool')
            ->where('status', 'active')
            ->get()
            ->groupBy(fn ($i) => $i->pool->pool_type ?? 'other')
            ->map(fn ($g) => (float) $g->sum('principal'));

        $riskExposure = $lender->investments()
            ->with('pool')
            ->where('status', 'active')
            ->get()
            ->groupBy(fn ($i) => $i->pool->risk_level ?? 'medium')
            ->map(fn ($g) => (float) $g->sum('principal'));

        $totalActive = max(1, $diversification->sum());
        $diversificationPct = $diversification->map(fn ($v) => round($v / $totalActive * 100, 1));
        $riskExposurePct    = $riskExposure->map(fn ($v) => round($v / $totalActive * 100, 1));

        return view('site.investor.analytics', compact(
            'stats', 'diversification', 'diversificationPct', 'riskExposure', 'riskExposurePct'
        ));
    }

    /* ------------------------------------------------------------------ */
    /* Transactions & Wallet                                               */
    /* ------------------------------------------------------------------ */

    public function transactions(Request $request)
    {
        $lender = $this->lender();
        $type = $request->string('type')->toString();

        $transactions = $lender->transactions()
            ->when(in_array($type, ['deposit','withdrawal','investment','return','fee'], true),
                   fn ($q) => $q->where('type', $type))
            ->latest()->paginate(20)->withQueryString();

        return view('site.investor.transactions', compact('transactions', 'type'));
    }

    public function wallet()
    {
        $lender = $this->lender();
        $stats  = $this->stats($lender);
        $recent = $lender->transactions()->latest()->limit(10)->get();
        return view('site.investor.wallet', compact('lender', 'stats', 'recent'));
    }

    public function deposit(Request $request)
    {
        $request->merge(['amount' => \App\Support\MoneyFormat::toNumber($request->input('amount'))]);

        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:1'],
            'channel' => ['required', 'in:bank,mobile_money,stablecoin'],
            'payment_reference' => ['nullable', 'string', 'max:80'],
        ]);

        $lender = $this->lender();

        DB::transaction(function () use ($lender, $data) {
            LenderTransaction::create([
                'lender_id'         => $lender->id,
                'reference'         => $this->txReference('DEP'),
                'type'              => 'deposit',
                'amount'            => $data['amount'],
                'status'            => 'completed', // simulate auto-confirmation
                'channel'           => $data['channel'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'processed_at'      => now(),
            ]);
            $lender->increment('available_balance', $data['amount']);
        });

        return back()->with('status', 'Deposit recorded. Funds available immediately.');
    }

    public function withdraw(Request $request)
    {
        $request->merge(['amount' => \App\Support\MoneyFormat::toNumber($request->input('amount'))]);

        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:1'],
            'channel' => ['required', 'in:bank,mobile_money,stablecoin'],
            'payment_reference' => ['nullable', 'string', 'max:80'],
        ]);

        $lender = $this->lender();
        if ((float) $data['amount'] > (float) $lender->available_balance) {
            return back()->withErrors(['amount' => 'Withdrawal exceeds available balance.']);
        }

        DB::transaction(function () use ($lender, $data) {
            LenderTransaction::create([
                'lender_id'         => $lender->id,
                'reference'         => $this->txReference('WDR'),
                'type'              => 'withdrawal',
                'amount'            => $data['amount'],
                'status'            => 'pending',
                'channel'           => $data['channel'],
                'payment_reference' => $data['payment_reference'] ?? null,
            ]);
            $lender->decrement('available_balance', $data['amount']);
        });

        return back()->with('status', 'Withdrawal request submitted. Processing within 24 hours.');
    }

    /* ------------------------------------------------------------------ */
    /* Documents & statements                                              */
    /* ------------------------------------------------------------------ */

    public function documents()
    {
        $lender = $this->lender();
        $statements = LenderStatement::where('lender_id', $lender->id)
            ->latest('period_end')->paginate(15);
        return view('site.investor.documents', compact('lender', 'statements'));
    }

    /* ------------------------------------------------------------------ */
    /* Notifications                                                       */
    /* ------------------------------------------------------------------ */

    public function notifications()
    {
        $notifications = NotificationLog::query()
            ->when(
                Schema::hasColumn('notification_logs', 'user_id'),
                fn ($q) => $q->where('user_id', Auth::id()),
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('recipient', Auth::user()?->email)
                        ->orWhere('recipient', Auth::user()?->phone);
                })
            )
            ->latest()
            ->paginate(20);

        return view('site.investor.notifications', compact('notifications'));
    }

    /* ------------------------------------------------------------------ */
    /* Profile & risk preferences                                          */
    /* ------------------------------------------------------------------ */

    public function profile(Request $request, ?string $section = null)
    {
        $lender = $this->lender();

        $section = $section ?: 'hub';

        if (! in_array($section, array_merge(['hub'], PartnerProfileService::SECTIONS), true)) {
            return redirect()->route('site.investor.profile');
        }

        $common = [
            'partner'         => $lender,
            'portal'          => 'investor',
            'profileRoute'    => 'site.investor.profile',
            'updateRoute'     => 'site.investor.profile.update',
            'layoutComponent' => 'site.investor-layout',
            'eyebrow'         => 'Capital partner',
            'accountTabs'     => [
                ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.investor.profile')],
                ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.investor.documents')],
                ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.investor.settings')],
            ],
        ];

        if ($section === 'hub') {
            return view('site.partner-account.hub', $common + [
                'title'    => __('site.partner_account.hub_title'),
                'subtitle' => __('site.partner_account.hub_subtitle'),
            ]);
        }

        return view('site.partner-account.'.$section, $common + [
            'title' => __('site.partner_account.'.$section.'_section'),
        ]);
    }

    public function settings()
    {
        $lender = $this->lender();

        return view('site.investor.settings', compact('lender'));
    }

    public function updateProfile(Request $request, string $section = 'personal')
    {
        $lender = $this->lender();

        if (! in_array($section, PartnerProfileService::SECTIONS, true)) {
            abort(404);
        }

        app(PartnerProfileService::class)->updateSection($lender, $section, $request);

        // Keep the underlying user account in sync when contact details change.
        if ($section === 'personal' && in_array($request->input('focus', 'contact'), ['contact', null], true)) {
            $user = Auth::user();
            $user->update(array_filter([
                'name'  => $request->filled('name') ? $request->input('name') : null,
                'phone' => $request->filled('phone') ? $request->input('phone') : null,
                'email' => $request->filled('email') ? $request->input('email') : null,
            ], fn ($value) => $value !== null));
        }

        return back()->with('status', 'Profile updated.');
    }

    public function support()
    {
        return view('site.investor.support');
    }
}
