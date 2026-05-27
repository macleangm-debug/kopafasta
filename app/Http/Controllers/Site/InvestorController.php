<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\LenderInvestment;
use App\Models\LenderStatement;
use App\Models\LenderTransaction;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $recentInvestments = $lender->investments()
            ->with('pool')->latest()->limit(5)->get();

        $recentTx = $lender->transactions()->latest()->limit(6)->get();

        $monthlyEarnings = LenderTransaction::where('lender_id', $lender->id)
            ->where('type', 'return')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', created_at) as ym, SUM(amount) as total")
            ->groupBy('ym')->orderBy('ym')->get();

        $notifications = NotificationLog::where('user_id', Auth::id())
            ->latest()->limit(4)->get();

        return view('site.investor.dashboard', compact(
            'lender', 'stats', 'recentInvestments', 'recentTx', 'monthlyEarnings', 'notifications'
        ));
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

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $lender = $this->lender();
        $amount = (float) $data['amount'];

        if ($amount < (float) $pool->min_investment) {
            return back()->withErrors(['amount' => 'Minimum investment is '.number_format($pool->min_investment, 0).' TZS.']);
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

        $monthly = LenderTransaction::where('lender_id', $lender->id)
            ->where('type', 'return')->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', created_at) as ym, SUM(amount) as total")
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
        $notifications = NotificationLog::where('user_id', Auth::id())
            ->latest()->paginate(20);
        return view('site.investor.notifications', compact('notifications'));
    }

    /* ------------------------------------------------------------------ */
    /* Profile & risk preferences                                          */
    /* ------------------------------------------------------------------ */

    public function profile()
    {
        $lender = $this->lender();
        return view('site.investor.profile', compact('lender'));
    }

    public function updateProfile(Request $request)
    {
        $lender = $this->lender();

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'email'           => ['required', 'email', 'max:120'],
            'address'         => ['nullable', 'string', 'max:255'],
            'risk_preference' => ['required', 'in:low,medium,high'],
            'auto_invest'     => ['nullable', 'boolean'],
        ]);

        $lender->update([
            'name'            => $data['name'],
            'phone'           => $data['phone'] ?? null,
            'email'           => $data['email'],
            'address'         => $data['address'] ?? null,
            'risk_preference' => $data['risk_preference'],
            'auto_invest'     => $request->boolean('auto_invest'),
        ]);

        $user = Auth::user();
        $user->update([
            'name'  => $data['name'],
            'phone' => $data['phone'] ?? $user->phone,
            'email' => $data['email'],
        ]);

        return back()->with('status', 'Profile updated.');
    }

    public function support()
    {
        return view('site.investor.support');
    }
}
