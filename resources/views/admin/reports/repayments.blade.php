<x-admin.layout title="Repayments Report" heading="Repayments Report" subheading="Recent repayment inflows by channel">

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Transactions</p>
            <p class="text-2xl font-bold">{{ $totals['count'] }}</p>
        </div>
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4">
            <p class="text-[10px] uppercase text-emerald-700">Total collected</p>
            <p class="text-2xl font-bold text-emerald-900">{{ format_money($totals['amount']) }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Principal</p>
            <p class="text-2xl font-bold">{{ format_money($totals['principal']) }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Interest</p>
            <p class="text-2xl font-bold">{{ format_money($totals['interest']) }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Penalties</p>
            <p class="text-2xl font-bold">{{ format_money($totals['penalty']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Reference</th>
                    <th class="px-5 py-3">Loan</th>
                    <th class="px-5 py-3">Customer</th>
                    <th class="px-5 py-3">Channel</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-600">{{ $row->paid_at?->format('d M Y') ?? '—' }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $row->reference }}</td>
                        <td class="px-5 py-3 font-mono text-xs">
                            @if ($row->loan)
                                <a href="{{ route('admin.loans.show', $row->loan) }}" class="text-amber-700">{{ $row->loan->loan_number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if ($row->loan?->customer)
                                {{ trim(($row->loan->customer->first_name ?? '').' '.($row->loan->customer->last_name ?? '')) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3">{{ ucfirst(str_replace('_', ' ', $row->channel)) }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ format_money($row->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No repayments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
