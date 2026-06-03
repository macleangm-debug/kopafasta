@php
@endphp
<x-site.investor-layout title="Dashboard — Investor" active="dashboard">
    <div class="mb-6">
        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight">Welcome back, {{ Auth::user()->name }}</h1>
        <p class="text-slate-500 text-sm mt-1">Here's a snapshot of your capital and earnings.</p>
    </div>

    {{-- Hero card --}}
    <div class="rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-900 text-white p-6 lg:p-8 shadow-xl mb-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div>
                <p class="text-xs uppercase tracking-widest text-emerald-300/80 font-semibold">Total invested</p>
                <p class="text-4xl font-extrabold mt-1">TZS {{ $fmt($stats['invested']) }}</p>
                <p class="text-sm text-slate-300 mt-2">Active capital deployed across {{ $stats['activeLoans'] }} loans</p>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:col-span-2">
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-xs uppercase text-emerald-300/80 font-semibold">Active investments</p>
                    <p class="text-2xl font-bold mt-1">TZS {{ $fmt($stats['active']) }}</p>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-xs uppercase text-emerald-300/80 font-semibold">Earnings to date</p>
                    <p class="text-2xl font-bold mt-1 text-emerald-400">TZS {{ $fmt($stats['returnsPaid']) }}</p>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-xs uppercase text-emerald-300/80 font-semibold">Available balance</p>
                    <p class="text-2xl font-bold mt-1">TZS {{ $fmt($stats['available']) }}</p>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-xs uppercase text-emerald-300/80 font-semibold">Portfolio performance</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['portfolioPerf'] }}%</p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-3 mt-6">
            <a href="{{ route('site.investor.pools') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold px-4 py-2 text-sm">Browse pools</a>
            <a href="{{ route('site.investor.wallet') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 text-white font-semibold px-4 py-2 text-sm">Deposit funds</a>
            <a href="{{ route('site.investor.returns') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 text-white font-semibold px-4 py-2 text-sm">View returns</a>
        </div>
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
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">{{ $t[0] }}</p>
                <p class="text-xl font-bold text-{{ $t[2] }}-700 mt-1">{{ $t[1] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold">Recent investments</h2>
                <a href="{{ route('site.investor.investments') }}" class="text-sm text-emerald-700 hover:underline font-semibold">View all →</a>
            </div>
            @if ($recentInvestments->isEmpty())
                <p class="text-sm text-slate-500">No investments yet. <a href="{{ route('site.investor.pools') }}" class="text-emerald-700 font-semibold hover:underline">Browse pools</a> to get started.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($recentInvestments as $inv)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm">{{ $inv->pool?->name ?? 'Direct investment' }}</p>
                                <p class="text-xs text-slate-500">{{ $inv->reference }} · {{ optional($inv->invested_at)->format('d M Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-sm">TZS {{ $fmt($inv->principal) }}</p>
                                <span class="text-[11px] font-semibold uppercase rounded px-2 py-0.5
                                    {{ $inv->status === 'active' ? 'bg-emerald-100 text-emerald-700' :
                                       ($inv->status === 'defaulted' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700') }}">{{ $inv->status }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold">Notifications</h2>
                <a href="{{ route('site.investor.notifications') }}" class="text-sm text-emerald-700 hover:underline font-semibold">All →</a>
            </div>
            @forelse ($notifications as $n)
                <div class="py-2 border-b border-slate-100 last:border-0">
                    <p class="text-sm font-medium">{{ $n->title ?? $n->subject ?? 'Notification' }}</p>
                    <p class="text-xs text-slate-500">{{ $n->created_at?->diffForHumans() }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No notifications yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Recent transactions --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold">Recent transactions</h2>
            <a href="{{ route('site.investor.transactions') }}" class="text-sm text-emerald-700 hover:underline font-semibold">View all →</a>
        </div>
        @if ($recentTx->isEmpty())
            <p class="text-sm text-slate-500">No transactions yet.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                        <th class="py-2">Reference</th><th>Type</th><th>Channel</th><th>Status</th><th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($recentTx as $t)
                        <tr>
                            <td class="py-2 font-mono text-xs">{{ $t->reference }}</td>
                            <td class="capitalize">{{ $t->type }}</td>
                            <td class="capitalize text-slate-600">{{ str_replace('_',' ',$t->channel) }}</td>
                            <td><span class="text-[11px] font-semibold uppercase rounded px-2 py-0.5 {{ $t->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($t->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $t->status }}</span></td>
                            <td class="text-right font-semibold {{ in_array($t->type, ['return','deposit']) ? 'text-emerald-700' : '' }}">TZS {{ $fmt($t->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-site.investor-layout>
