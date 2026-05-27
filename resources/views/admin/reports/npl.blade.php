<x-admin.layout title="NPL Report" heading="Non-Performing Loans" subheading="Loans 90+ days past due">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Total outstanding</div>
            <div class="mt-2 text-2xl font-bold font-mono">{{ number_format($totalOutstanding, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">NPL outstanding (90+ days)</div>
            <div class="mt-2 text-2xl font-bold text-rose-600 font-mono">{{ number_format($nplOutstanding, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">NPL ratio</div>
            <div class="mt-2 text-2xl font-bold {{ $nplRatio > 5 ? 'text-rose-600' : 'text-amber-600' }} font-mono">{{ number_format($nplRatio, 2) }}%</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Written off (cumulative)</div>
            <div class="mt-2 text-2xl font-bold text-gray-700 font-mono">{{ number_format($writtenOff, 2) }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 font-semibold">Aging buckets</div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-6 py-3 text-left">Bucket</th>
                    <th class="px-6 py-3 text-right">Loans</th>
                    <th class="px-6 py-3 text-right">Outstanding</th>
                    <th class="px-6 py-3 text-right">% of portfolio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($buckets as $b)
                    <tr>
                        <td class="px-6 py-3">{{ $b['label'] }}</td>
                        <td class="px-6 py-3 text-right font-mono">{{ number_format($b['count']) }}</td>
                        <td class="px-6 py-3 text-right font-mono">{{ number_format($b['amount'], 2) }}</td>
                        <td class="px-6 py-3 text-right font-mono">
                            {{ $totalOutstanding > 0 ? number_format(($b['amount']/$totalOutstanding)*100, 2) : '0.00' }}%
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 font-semibold">NPL by product</div>
        @if ($productBreakdown->isEmpty())
            <div class="px-6 py-8 text-center text-gray-500 text-sm">No non-performing loans.</div>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 text-left">Product</th>
                        <th class="px-6 py-3 text-right">NPL count</th>
                        <th class="px-6 py-3 text-right">NPL outstanding</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($productBreakdown as $p)
                        <tr>
                            <td class="px-6 py-3">{{ $p->name }}</td>
                            <td class="px-6 py-3 text-right font-mono">{{ number_format($p->count) }}</td>
                            <td class="px-6 py-3 text-right font-mono">{{ number_format($p->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-admin.layout>
