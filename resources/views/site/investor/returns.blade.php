@php
    $fmt = fn ($n) => number_format((float) $n, 0);
    $max = max(1, $monthly->max('total') ?? 1);
@endphp
<x-site.investor-layout title="Returns & earnings — Investor" active="returns">
    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mb-1">Returns & earnings</h1>
    <p class="text-slate-500 text-sm mb-6">Track every shilling earned and what's still expected.</p>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $cards = [
                ['Earnings paid',    'TZS '.$fmt($stats['returnsPaid']),  'emerald'],
                ['Pending returns',  'TZS '.$fmt(max(0, $stats['returnsExpect'] - $stats['returnsPaid'])), 'amber'],
                ['Projected (active)','TZS '.$fmt($stats['returnsExpect']), 'sky'],
                ['Yield to date',     $stats['portfolioPerf'].'%',           'slate'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase text-slate-500 font-semibold tracking-wider">{{ $c[0] }}</p>
                <p class="text-2xl font-bold text-{{ $c[2] }}-700 mt-1">{{ $c[1] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 mb-6">
        <h2 class="font-bold mb-4">Monthly earnings (last 12 months)</h2>
        @if ($monthly->isEmpty())
            <p class="text-sm text-slate-500">No payout history yet.</p>
        @else
            <div class="flex items-end gap-3 h-48">
                @foreach ($monthly as $m)
                    @php $h = max(4, round(($m->total / $max) * 100)); @endphp
                    <div class="flex-1 flex flex-col items-center gap-2">
                        <div class="w-full bg-emerald-500 rounded-t" style="height: {{ $h }}%" title="TZS {{ $fmt($m->total) }}"></div>
                        <span class="text-[10px] text-slate-500 font-medium">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m->ym)->format('M') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="font-bold mb-4">By pool</h2>
        @if ($byPool->isEmpty())
            <p class="text-sm text-slate-500">No investments yet.</p>
        @else
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-slate-500">
                    <tr><th class="text-left py-2">Pool</th><th class="text-right py-2">Investments</th><th class="text-right py-2">Principal</th><th class="text-right py-2">Expected returns</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($byPool as $name => $d)
                        <tr>
                            <td class="py-2 font-semibold">{{ $name }}</td>
                            <td class="py-2 text-right">{{ $d['count'] }}</td>
                            <td class="py-2 text-right">TZS {{ $fmt($d['principal']) }}</td>
                            <td class="py-2 text-right text-emerald-700 font-semibold">TZS {{ $fmt($d['returns']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-site.investor-layout>
