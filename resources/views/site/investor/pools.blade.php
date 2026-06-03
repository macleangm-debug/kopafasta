<x-site.investor-layout title="Funding pools — Investor" active="pools">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold tracking-tight">Funding pools</h1>
            <p class="text-slate-500 text-sm mt-1">Pick a pool that matches your risk appetite and target return.</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-2 mb-6">
        @foreach (['' => 'All', 'low' => 'Low risk', 'medium' => 'Medium risk', 'high' => 'High return'] as $k => $label)
            <a href="?risk={{ $k }}&type={{ $type }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold border
                      {{ $risk === $k ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
        <span class="mx-2 text-slate-300">|</span>
        @foreach (['' => 'All types', 'salary' => 'Salary', 'business' => 'Business', 'car' => 'Car', 'emergency' => 'Emergency'] as $k => $label)
            <a href="?risk={{ $risk }}&type={{ $k }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold border
                      {{ $type === $k ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </form>

    @if ($pools->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-slate-500">No funding pools match your filters yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach ($pools as $pool)
                @php
                    $riskColor = ['low' => 'emerald', 'medium' => 'amber', 'high' => 'red'][$pool->risk_level] ?? 'slate';
                    $remaining = max(0, (float) $pool->amount_committed - (float) $pool->amount_deployed);
                    $pct = $pool->amount_committed > 0 ? min(100, round(($pool->amount_deployed / $pool->amount_committed) * 100)) : 0;
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">{{ ucfirst($pool->pool_type) }} loans</p>
                            <h3 class="font-bold text-lg mt-0.5">{{ $pool->name }}</h3>
                        </div>
                        <span class="text-[11px] font-semibold uppercase rounded-full px-2 py-1 bg-{{ $riskColor }}-100 text-{{ $riskColor }}-700">{{ $pool->risk_level }} risk</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-4 text-center">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-[11px] uppercase text-slate-500 font-semibold">Expected return</p>
                            <p class="text-xl font-extrabold text-emerald-700">{{ rtrim(rtrim(number_format($pool->expected_yield, 2),'0'),'.') }}%</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-[11px] uppercase text-slate-500 font-semibold">Min invest</p>
                            <p class="text-xl font-extrabold">TZS {{ $fmt($pool->min_investment) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-4 text-xs">
                        <div><span class="text-slate-500">Borrowers</span><br><span class="font-semibold">{{ $pool->active_borrowers }}</span></div>
                        <div><span class="text-slate-500">Repayment</span><br><span class="font-semibold text-emerald-700">{{ rtrim(rtrim(number_format($pool->repayment_rate, 1),'0'),'.') }}%</span></div>
                        <div><span class="text-slate-500">Defaults</span><br><span class="font-semibold {{ $pool->default_rate > 5 ? 'text-red-600' : 'text-slate-700' }}">{{ rtrim(rtrim(number_format($pool->default_rate, 1),'0'),'.') }}%</span></div>
                    </div>

                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>Funded {{ $pct }}%</span>
                            <span>TZS {{ $fmt($remaining) }} left</span>
                        </div>
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <a href="{{ route('site.investor.pool', $pool) }}" class="flex-1 inline-flex items-center justify-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-3 py-2 text-sm">Invest now</a>
                        <a href="{{ route('site.investor.pool', $pool) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-3 py-2 text-sm">Details</a>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $pools->links() }}</div>
    @endif
</x-site.investor-layout>
