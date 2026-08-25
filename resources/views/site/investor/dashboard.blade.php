<x-site.investor-layout title="Dashboard — Investor" active="dashboard">
    <x-site.borrower-page-header
        eyebrow="Capital partner"
        :title="'Welcome back, '.Auth::user()->name"
        subtitle="Here's a snapshot of your capital and earnings."
    />

    <x-site.investor-dashboard-quick-actions />

    {{-- Hero card --}}
    <div class="rounded-2xl kf-premium-panel p-6 lg:p-8 mb-6 relative">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand-gold/90 font-semibold">Total invested</p>
                <p class="text-4xl font-extrabold mt-1">TZS {{ $fmt($stats['invested']) }}</p>
                <p class="text-sm text-white/70 mt-2">Active capital deployed across {{ $stats['activeLoans'] }} loans</p>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:col-span-2">
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-xs uppercase text-brand-gold/90 font-semibold">Active investments</p>
                    <p class="text-2xl font-bold mt-1">TZS {{ $fmt($stats['active']) }}</p>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-xs uppercase text-brand-gold/90 font-semibold">Earnings to date</p>
                    <p class="text-2xl font-bold mt-1 text-brand-gold">TZS {{ $fmt($stats['returnsPaid']) }}</p>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-xs uppercase text-brand-gold/90 font-semibold">Available balance</p>
                    <p class="text-2xl font-bold mt-1">TZS {{ $fmt($stats['available']) }}</p>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-xs uppercase text-brand-gold/90 font-semibold">Portfolio performance</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['portfolioPerf'] }}%</p>
                </div>
            </div>
        </div>
        <div class="relative flex flex-wrap gap-3 mt-6">
            <a href="{{ route('site.investor.pools') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-gold hover:brightness-95 text-brand font-bold px-4 py-2.5 text-sm">Browse pools</a>
            <a href="{{ route('site.investor.wallet') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 text-sm">Deposit funds</a>
            <a href="{{ route('site.investor.returns') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 text-sm">View returns</a>
        </div>
    </div>

    {{-- Capital deployment (loan allocations at disbursement) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="rounded-xl border ring-1 ring-brand/20 bg-brand-muted/40 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-brand font-semibold">Deployed exposure</p>
            <p class="text-xl font-bold text-brand mt-1">TZS {{ number_format($capitalMetrics['outstanding_exposure'] ?? 0, 0) }}</p>
        </div>
        <div class="glass-card rounded-xl ring-1 ring-brand/10 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Capital available</p>
            <p class="text-xl font-bold text-gray-900 mt-1">TZS {{ number_format($capitalMetrics['capital_available'] ?? 0, 0) }}</p>
        </div>
        <div class="glass-card rounded-xl ring-1 ring-brand/10 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Partner interest earned</p>
            <p class="text-xl font-bold text-brand mt-1">TZS {{ number_format($capitalMetrics['interest_earned_partner'] ?? 0, 0) }}</p>
        </div>
        <div class="glass-card rounded-xl ring-1 ring-brand/10 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Funded loans</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $capitalMetrics['active_loans'] ?? 0 }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 mb-6">
        <a href="{{ route('site.investor.funded-loans') }}" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold px-4 py-2 text-sm">View funded loans</a>
    </div>

    {{-- Summary tiles --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $tiles = [
                ['Expected returns', 'TZS '.$fmt($stats['returnsExpect']), 'emerald'],
                ['Deposited',        'TZS '.$fmt($stats['deposited']),    'sky'],
                ['Withdrawn',        'TZS '.$fmt($stats['withdrawn']),    'slate'],
                ['Default rate',     $stats['defaultRate'].'%',            $stats['defaultRate'] > 5 ? 'red' : 'emerald'],
            ];
        @endphp
        @foreach ($tiles as $t)
            <div class="glass-card rounded-xl ring-1 ring-brand/10 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">{{ $t[0] }}</p>
                <p class="text-xl font-bold text-{{ $t[2] }}-700 mt-1">{{ $t[1] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 glass-card rounded-2xl ring-1 ring-brand/10 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold">Recent investments</h2>
                <a href="{{ route('site.investor.investments') }}" class="text-sm text-brand hover:underline font-semibold">View all →</a>
            </div>
            @if ($recentInvestments->isEmpty())
                <x-site.empty-state
                    icon="📈"
                    title="No investments yet"
                    description="Browse funding pools and deploy capital when you are ready."
                    action-label="Browse pools"
                    :action-url="route('site.investor.pools')"
                    class="!p-8 border-0 shadow-none bg-brand-muted/30"
                />
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($recentInvestments as $inv)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm">{{ $inv->pool?->name ?? 'Direct investment' }}</p>
                                <p class="text-xs text-gray-500">{{ $inv->reference }} · {{ optional($inv->invested_at)->format('d M Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-sm">TZS {{ $fmt($inv->principal) }}</p>
                                <span class="text-[11px] font-semibold uppercase rounded px-2 py-0.5
                                    {{ $inv->status === 'active' ? 'bg-emerald-100 text-brand' :
                                       ($inv->status === 'defaulted' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700') }}">{{ $inv->status }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold">Notifications</h2>
                <a href="{{ route('site.investor.notifications') }}" class="text-sm text-brand hover:underline font-semibold">All →</a>
            </div>
            @forelse ($notifications as $n)
                <div class="py-2 border-b border-slate-100 last:border-0">
                    <p class="text-sm font-medium">{{ $n->title ?? $n->subject ?? 'Notification' }}</p>
                    <p class="text-xs text-gray-500">{{ $n->created_at?->diffForHumans() }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">No notifications yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Recent transactions --}}
    <div class="mt-6 glass-card rounded-2xl ring-1 ring-brand/10 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold">Recent transactions</h2>
            <a href="{{ route('site.investor.transactions') }}" class="text-sm text-brand hover:underline font-semibold">View all →</a>
        </div>
        @if ($recentTx->isEmpty())
            <p class="text-sm text-gray-500">No transactions yet.</p>
        @else
            <div class="overflow-x-auto scrollbar-none -mx-2 px-2">
            <table class="w-full text-sm min-w-[640px]">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 border-b border-brand/10">
                        <th class="py-2">Reference</th><th>Type</th><th>Channel</th><th>Status</th><th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($recentTx as $t)
                        <tr>
                            <td class="py-2 font-mono text-xs">{{ $t->reference }}</td>
                            <td class="capitalize">{{ $t->type }}</td>
                            <td class="capitalize text-slate-600">{{ str_replace('_',' ',$t->channel) }}</td>
                            <td><span class="text-[11px] font-semibold uppercase rounded px-2 py-0.5 {{ $t->status === 'completed' ? 'bg-emerald-100 text-brand' : ($t->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $t->status }}</span></td>
                            <td class="text-right font-semibold {{ in_array($t->type, ['return','deposit']) ? 'text-brand' : '' }}">TZS {{ $fmt($t->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-site.investor-layout>
