@php
    $max = max(1, (float) ($monthly->max('total') ?? 0), 1);
    $pending = max(0, (float) $stats['returnsExpect'] - (float) $stats['returnsPaid']);
@endphp
<x-site.investor-layout title="Earnings — Capital partner" active="returns">
    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mb-1">Earnings</h1>
    <p class="text-gray-500 text-sm mb-6">Partner interest earned from funded loans (60% share of interest by default).</p>

    <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold">Monthly earnings (last 12 months)</h2>
            <a href="{{ route('site.investor.analytics') }}" class="text-sm font-semibold text-brand hover:underline">Portfolio analytics →</a>
        </div>
        <div class="flex items-end gap-2 sm:gap-3 h-48">
            @foreach ($monthly as $m)
                @php $h = max(4, round(((float) $m->total / $max) * 100)); @endphp
                <div class="flex-1 flex flex-col items-center gap-2 min-w-0">
                    <div class="w-full rounded-t {{ (float) $m->total > 0 ? 'bg-emerald-500' : 'bg-gray-200' }}"
                         style="height: {{ $h }}%"
                         title="TZS {{ number_format((float) $m->total, 0) }}"></div>
                    <span class="text-[10px] text-gray-500 font-medium truncate w-full text-center">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m->ym)->format('M') }}</span>
                </div>
            @endforeach
        </div>
        @if ($monthly->sum('total') <= 0)
            <p class="mt-3 text-xs text-gray-500 text-center">No interest credited yet — bars stay flat until repayments earn interest.</p>
        @endif
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $cards = [
                ['Earnings paid',    'TZS '.number_format((float) $stats['returnsPaid'], 0),  'Interest already credited to you'],
                ['Pending returns',  'TZS '.number_format($pending, 0), 'Accrued on active loans, not yet settled as paid'],
                ['Accrued (active)', 'TZS '.number_format((float) $stats['returnsExpect'], 0), 'Interest booked on active investments'],
                ['Yield to date',    $stats['portfolioPerf'].'%', 'Interest earned ÷ capital invested'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div class="glass-card rounded-xl ring-1 ring-brand/10 p-4">
                <p class="text-xs uppercase text-gray-500 font-semibold tracking-wider">{{ $c[0] }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $c[1] }}</p>
                <p class="text-[11px] text-gray-500 mt-1">{{ $c[2] }}</p>
            </div>
        @endforeach
    </div>

    <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold">By pool</h2>
            <a href="{{ route('site.investor.pools') }}" class="text-sm font-semibold text-brand hover:underline">Funding pools →</a>
        </div>
        @if ($byPool->isEmpty())
            <p class="text-sm text-gray-500">No investments yet.</p>
        @else
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr><th class="text-left py-2">Pool</th><th class="text-right py-2">Investments</th><th class="text-right py-2">Principal</th><th class="text-right py-2">Interest earned</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($byPool as $name => $d)
                        <tr>
                            <td class="py-2 font-semibold">{{ $name }}</td>
                            <td class="py-2 text-right">{{ $d['count'] }}</td>
                            <td class="py-2 text-right">TZS {{ number_format($d['principal'], 0) }}</td>
                            <td class="py-2 text-right text-brand font-semibold">TZS {{ number_format($d['returns'], 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-site.investor-layout>
