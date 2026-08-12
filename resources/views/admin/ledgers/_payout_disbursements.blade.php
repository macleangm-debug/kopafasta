<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Loan</th>
                <th class="px-4 py-3">Borrower</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Released</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($disbursements as $row)
                @php
                    $customer = $row->loan?->customer;
                    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                @endphp
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row->loan?->loan_number ?? '#'.$row->loan_id }}</td>
                    <td class="px-4 py-3">{{ $name ?: '—' }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ format_money((float) $row->amount) }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($row->status ?? '—')) }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $row->released_at?->format('d M Y') ?? $row->created_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No disbursements found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $disbursements->links() }}</div>
