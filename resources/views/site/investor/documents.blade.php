@php $fmt = fn ($n) => number_format((float) $n, 0); @endphp
<x-site.investor-layout title="Documents — Investor" active="documents">
    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mb-1">Documents & statements</h1>
    <p class="text-slate-500 text-sm mb-6">Contracts, monthly statements and tax reports.</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs uppercase text-slate-500 font-semibold">Investor agreement</p>
            <p class="font-semibold mt-1">Master capital partner agreement</p>
            <button class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Download agreement</button>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs uppercase text-slate-500 font-semibold">Year-to-date</p>
            <p class="font-semibold mt-1">{{ now()->year }} statement</p>
            <button class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Download statement</button>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs uppercase text-slate-500 font-semibold">Tax report</p>
            <p class="font-semibold mt-1">{{ now()->subYear()->year }} tax summary</p>
            <button class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Download report</button>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="font-bold">Statement history</h2>
        </div>
        @if ($statements->isEmpty())
            <div class="p-10 text-center text-slate-500 text-sm">No statements generated yet. Statements appear monthly.</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="text-left px-4 py-3">Period</th>
                        <th class="text-right px-4 py-3">Opening</th>
                        <th class="text-right px-4 py-3">Investments</th>
                        <th class="text-right px-4 py-3">Returns</th>
                        <th class="text-right px-4 py-3">Withdrawals</th>
                        <th class="text-right px-4 py-3">Closing</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($statements as $s)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $s->period_start->format('d M') }} – {{ $s->period_end->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">TZS {{ $fmt($s->opening_balance) }}</td>
                            <td class="px-4 py-3 text-right">TZS {{ $fmt($s->investments_total) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-700">TZS {{ $fmt($s->returns_total) }}</td>
                            <td class="px-4 py-3 text-right">TZS {{ $fmt($s->withdrawals_total) }}</td>
                            <td class="px-4 py-3 text-right font-bold">TZS {{ $fmt($s->closing_balance) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($s->file_path)
                                    <a href="{{ asset('storage/'.$s->file_path) }}" class="text-emerald-700 hover:underline text-xs font-semibold">Download</a>
                                @else
                                    <span class="text-slate-400 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-6">{{ $statements->links() }}</div>
</x-site.investor-layout>
