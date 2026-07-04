@props(['pool'])

@php
    $riskBadge = match ($pool->risk_level) {
        'low' => 'bg-emerald-100 text-emerald-700',
        'medium' => 'bg-amber-100 text-amber-700',
        'high' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-700',
    };
    $remaining = max(0, (float) $pool->amount_committed - (float) $pool->amount_deployed);
    $pct = $pool->amount_committed > 0 ? min(100, round(($pool->amount_deployed / $pool->amount_committed) * 100)) : 0;
@endphp

<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
    <div class="flex items-start justify-between gap-2">
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">{{ ucfirst($pool->pool_type) }} loans</p>
            <h3 class="font-bold text-lg mt-0.5 leading-tight">{{ $pool->name }}</h3>
        </div>
        <span class="text-[11px] font-semibold uppercase rounded-full px-2 py-1 shrink-0 {{ $riskBadge }}">{{ $pool->risk_level }} risk</span>
    </div>

    <div class="grid grid-cols-2 gap-3 mt-4 text-center">
        <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-[11px] uppercase text-slate-500 font-semibold">Expected return</p>
            <p class="text-xl font-extrabold text-emerald-700 tabular-nums">{{ rtrim(rtrim(format_number($pool->expected_yield, 2),'0'),'.') }}%</p>
        </div>
        <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-[11px] uppercase text-slate-500 font-semibold">Min. investment</p>
            <p class="text-lg font-bold text-slate-900 tabular-nums">{{ format_money($pool->min_investment ?? 0, false, 0) }}</p>
        </div>
    </div>

    <div class="mt-4">
        <div class="flex justify-between text-xs text-slate-500 mb-1">
            <span>Deployed</span>
            <span class="tabular-nums">{{ $pct }}%</span>
        </div>
        <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $pct }}%"></div>
        </div>
        <p class="text-xs text-slate-500 mt-2 tabular-nums">{{ format_money($remaining, false, 0) }} remaining capacity</p>
    </div>

    <a href="{{ route('site.investor.pool', $pool) }}"
       class="mt-auto pt-5 inline-flex justify-center bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
        View pool
    </a>
</article>
