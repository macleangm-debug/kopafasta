<x-site.investor-layout title="My investments — Investor" active="investments">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold tracking-tight">My investments</h1>
            <p class="text-slate-500 text-sm mt-1">All your capital placements across pools and direct loans.</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach (['' => 'All', 'active' => 'Active', 'matured' => 'Matured', 'closed' => 'Closed', 'defaulted' => 'Defaulted', 'pending' => 'Pending'] as $k => $label)
            <a href="?status={{ $k }}" class="rounded-full px-3 py-1.5 text-xs font-semibold border
                {{ $status === $k ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
        @if ($investments->isEmpty())
            <div class="p-10 text-center text-slate-500 text-sm">
                You haven't made any investments yet.
                <a href="{{ route('site.investor.pools') }}" class="text-emerald-700 font-semibold hover:underline">Browse pools</a>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="text-left px-4 py-3">Reference</th>
                        <th class="text-left px-4 py-3">Pool</th>
                        <th class="text-right px-4 py-3">Amount</th>
                        <th class="text-right px-4 py-3">Return %</th>
                        <th class="text-left px-4 py-3">Invested</th>
                        <th class="text-left px-4 py-3">Matures</th>
                        <th class="text-left px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($investments as $inv)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs"><a href="{{ route('site.investor.investment', $inv) }}" class="text-emerald-700 hover:underline">{{ $inv->reference }}</a></td>
                            <td class="px-4 py-3">{{ $inv->pool?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">TZS {{ $fmt($inv->principal) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-700 font-semibold">{{ rtrim(rtrim(format_number($inv->return_rate, 2),'0'),'.') }}%</td>
                            <td class="px-4 py-3 text-slate-600">{{ optional($inv->invested_at)->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ optional($inv->matures_at)->format('d M Y') ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold uppercase rounded px-2 py-0.5
                                    {{ $inv->status === 'active' ? 'bg-emerald-100 text-emerald-700' :
                                       ($inv->status === 'defaulted' ? 'bg-red-100 text-red-700' :
                                       ($inv->status === 'matured' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-700')) }}">{{ $inv->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-6">{{ $investments->links() }}</div>
</x-site.investor-layout>
