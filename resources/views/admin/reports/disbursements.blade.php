<x-admin.layout title="Disbursements Report" heading="Disbursements Report" subheading="Released disbursements">

    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Disbursements shown</p>
            <p class="text-2xl font-bold">{{ $totals['count'] }}</p>
        </div>
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4">
            <p class="text-[10px] uppercase text-emerald-700">Total disbursed</p>
            <p class="text-2xl font-bold text-emerald-900">{{ format_money($totals['amount']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Released</th>
                    <th class="px-5 py-3">Loan</th>
                    <th class="px-5 py-3">Customer</th>
                    <th class="px-5 py-3">Channel</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-xs">{{ optional($row->released_at)->format('d M Y H:i') }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $row->loan?->loan_number ?? '—' }}</td>
                        <td class="px-5 py-3">{{ trim(($row->loan?->customer?->first_name ?? '').' '.($row->loan?->customer?->last_name ?? '')) }}</td>
                        <td class="px-5 py-3">{{ str_replace('_', ' ', $row->channel) }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ format_money($row->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">No disbursements recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
