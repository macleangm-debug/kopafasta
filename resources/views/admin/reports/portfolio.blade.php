<x-admin.layout title="Portfolio Report" heading="Portfolio Report" subheading="Outstanding portfolio by active loan">

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Active loans</p>
            <p class="text-2xl font-bold">{{ $totals['count'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Principal outstanding</p>
            <p class="text-2xl font-bold">{{ format_money($totals['principal']) }}</p>
        </div>
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
            <p class="text-[10px] uppercase text-amber-700">Total outstanding</p>
            <p class="text-2xl font-bold text-amber-900">{{ format_money($totals['outstanding']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Loan</th>
                    <th class="px-5 py-3">Customer</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Principal</th>
                    <th class="px-5 py-3 text-right">Interest</th>
                    <th class="px-5 py-3 text-right">Penalties</th>
                    <th class="px-5 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">
                            <a href="{{ route('admin.loans.show', $row['loan']) }}" class="text-amber-700">{{ $row['loan']->loan_number }}</a>
                        </td>
                        <td class="px-5 py-3">{{ trim(($row['customer']->first_name ?? '').' '.($row['customer']->last_name ?? '')) }}</td>
                        <td class="px-5 py-3">{{ ucfirst($row['loan']->status) }}</td>
                        <td class="px-5 py-3 text-right">{{ format_money($row['breakdown']['principal_outstanding']) }}</td>
                        <td class="px-5 py-3 text-right">{{ format_money($row['breakdown']['interest_outstanding']) }}</td>
                        <td class="px-5 py-3 text-right">{{ format_money($row['breakdown']['penalty_outstanding']) }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ format_money($row['breakdown']['total_outstanding']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">No active loans in portfolio.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
