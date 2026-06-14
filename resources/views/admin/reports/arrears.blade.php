<x-admin.layout title="Arrears Report" heading="Arrears Report" subheading="Open collection cases and exposure">

    <div class="grid grid-cols-3 gap-3 mb-4">
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4">
            <p class="text-[10px] uppercase text-red-600">Open cases</p>
            <p class="text-2xl font-bold text-red-900">{{ $totals['cases'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Amount in arrears</p>
            <p class="text-2xl font-bold">{{ format_money($totals['amount']) }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Penalties accrued</p>
            <p class="text-2xl font-bold">{{ format_money($totals['penalty']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Loan</th>
                    <th class="px-5 py-3">Customer</th>
                    <th class="px-5 py-3">Days past due</th>
                    <th class="px-5 py-3 text-right">In arrears</th>
                    <th class="px-5 py-3">Assigned</th>
                    <th class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($cases as $case)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">
                            @if ($case->loan)
                                <a href="{{ route('admin.arrear-cases.show', $case) }}" class="text-amber-700">{{ $case->loan->loan_number }}</a>
                            @else — @endif
                        </td>
                        <td class="px-5 py-3">{{ trim(($case->loan?->customer?->first_name ?? '').' '.($case->loan?->customer?->last_name ?? '')) }}</td>
                        <td class="px-5 py-3 text-red-700 font-semibold">{{ $case->days_past_due }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ format_money($case->amount_in_arrears) }}</td>
                        <td class="px-5 py-3">{{ $case->assignee?->name ?? '—' }}</td>
                        <td class="px-5 py-3">{{ ucfirst($case->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No open arrears cases.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
