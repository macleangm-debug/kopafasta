<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Loan</th>
                <th class="px-4 py-3">Borrower</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($disbursements as $row)
                @php
                    $customer = $row->loan?->customer;
                    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                    $when = $row->released_at ?? $row->created_at;
                @endphp
                <tr>
                    <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                        <p class="font-semibold text-gray-900">{{ format_app_date($when) }}</p>
                        <p class="tabular-nums text-gray-500 mt-0.5">{{ format_app_datetime($when, 'H:i') }}</p>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row->loan?->loan_number ?? '#'.$row->loan_id }}</td>
                    <td class="px-4 py-3">{{ $name ?: '—' }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ format_money((float) $row->amount) }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($row->status ?? '—')) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No disbursements found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $disbursements->links() }}</div>
