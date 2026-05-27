<x-admin.layout title="Large Transactions" heading="Large-transaction Monitoring" subheading="Cash-equivalent payments at or above AML threshold">

    <form method="GET" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
        <div>
            <label class="block text-xs uppercase text-gray-500 mb-1">Threshold (TZS)</label>
            <input type="number" name="threshold" value="{{ $threshold }}" step="100000" min="0"
                   class="w-full rounded-lg border-gray-300">
        </div>
        <div>
            <label class="block text-xs uppercase text-gray-500 mb-1">From</label>
            <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300">
        </div>
        <div>
            <label class="block text-xs uppercase text-gray-500 mb-1">To</label>
            <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300">
        </div>
        <div class="flex items-end">
            <button class="w-full inline-flex justify-center items-center gap-1.5 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-4 py-2 rounded-lg">
                Filter
            </button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold uppercase text-gray-600">
                    <th class="px-4 py-2">Reference</th>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Customer</th>
                    <th class="px-4 py-2">Loan</th>
                    <th class="px-4 py-2">Channel</th>
                    <th class="px-4 py-2 text-right">Amount (TZS)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs">{{ $r->reference }}</td>
                        <td class="px-4 py-2">{{ optional($r->paid_at)->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @php $c = $r->loan?->customer; @endphp
                            {{ $c ? trim(($c->first_name ?? '').' '.($c->last_name ?? '')) : '—' }}
                            @if ($c?->phone)<div class="text-xs text-gray-500">{{ $c->phone }}</div>@endif
                        </td>
                        <td class="px-4 py-2 font-mono text-xs">{{ optional($r->loan)->loan_number ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $r->channel }}</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ number_format((float) $r->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No transactions at or above threshold in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rows->links() }}
    </div>
</x-admin.layout>
