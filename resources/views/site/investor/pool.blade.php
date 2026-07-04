<x-site.investor-layout title="{{ $pool->name }} — Pool" active="pools">
    <a href="{{ route('site.investor.pools') }}" class="text-sm text-emerald-700 hover:underline font-semibold">← All pools</a>

    @php
        $riskColor = ['low' => 'emerald', 'medium' => 'amber', 'high' => 'red'][$pool->risk_level] ?? 'slate';
        $remaining = max(0, (float) $pool->amount_committed - (float) $pool->amount_deployed);
        $pct = $pool->amount_committed > 0 ? min(100, round(($pool->amount_deployed / $pool->amount_committed) * 100)) : 0;
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">{{ ucfirst($pool->pool_type) }} loans</p>
                    <h1 class="text-2xl font-bold mt-1">{{ $pool->name }}</h1>
                </div>
                <span class="text-xs font-semibold uppercase rounded-full px-3 py-1 bg-{{ $riskColor }}-100 text-{{ $riskColor }}-700">{{ $pool->risk_level }} risk</span>
            </div>

            @if ($pool->description)
                <p class="text-slate-600 leading-relaxed">{{ $pool->description }}</p>
            @endif

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] uppercase text-slate-500 font-semibold">Expected yield</p>
                    <p class="text-xl font-extrabold text-emerald-700">{{ rtrim(rtrim(format_number($pool->expected_yield, 2),'0'),'.') }}%</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] uppercase text-slate-500 font-semibold">Repayment rate</p>
                    <p class="text-xl font-extrabold">{{ rtrim(rtrim(format_number($pool->repayment_rate, 1),'0'),'.') }}%</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] uppercase text-slate-500 font-semibold">Default rate</p>
                    <p class="text-xl font-extrabold {{ $pool->default_rate > 5 ? 'text-red-600' : '' }}">{{ rtrim(rtrim(format_number($pool->default_rate, 1),'0'),'.') }}%</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] uppercase text-slate-500 font-semibold">Active borrowers</p>
                    <p class="text-xl font-extrabold">{{ $pool->active_borrowers }}</p>
                </div>
            </div>

            <div class="mt-6">
                <div class="flex justify-between text-xs text-slate-500 mb-1">
                    <span>Funded {{ $pct }}% (TZS {{ $fmt($pool->amount_deployed) }} of {{ $fmt($pool->amount_committed) }})</span>
                    <span>{{ $pct == 100 ? 'Closed to new capital' : 'TZS '.$fmt($remaining).' available' }}</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pct }}%"></div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3 text-sm">
                <div><span class="text-slate-500">Currency:</span> <span class="font-semibold">{{ $pool->currency }}</span></div>
                <div><span class="text-slate-500">Status:</span> <span class="font-semibold capitalize">{{ $pool->status }}</span></div>
                <div><span class="text-slate-500">Opens:</span> <span class="font-semibold">{{ optional($pool->start_date)->format('d M Y') ?: '—' }}</span></div>
                <div><span class="text-slate-500">Matures:</span> <span class="font-semibold">{{ optional($pool->end_date)->format('d M Y') ?: '—' }}</span></div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 h-fit sticky top-20">
            <h2 class="font-bold mb-1">Invest in this pool</h2>
            <p class="text-xs text-slate-500 mb-4">Your available balance: <span class="font-semibold">TZS {{ $fmt($stats['available']) }}</span></p>

            <form method="POST" action="{{ route('site.investor.pool.invest', $pool) }}" class="space-y-3">
                @csrf
                <div>
                    <x-site.numeric-input
                        name="amount"
                        label="Amount (TZS)"
                        :min="$pool->min_investment"
                        step="1000"
                        :required="true"
                        :money="true"
                        placeholder="Min {{ $fmt($pool->min_investment) }}"
                    />
                </div>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-xs text-emerald-800">
                    Expected return at {{ rtrim(rtrim(format_number($pool->expected_yield, 2),'0'),'.') }}% — for every TZS 1,000,000 you commit, you can expect ≈ {{ format_money(1000000 * ($pool->expected_yield / 100), 0) }} in returns.
                </div>
                <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 text-sm shadow-lg shadow-emerald-500/20">Invest now</button>
                @if ($stats['available'] <= 0)
                    <a href="{{ route('site.investor.wallet') }}" class="block text-center text-xs text-emerald-700 hover:underline font-semibold">Deposit funds first →</a>
                @endif
            </form>
        </div>
    </div>
</x-site.investor-layout>
