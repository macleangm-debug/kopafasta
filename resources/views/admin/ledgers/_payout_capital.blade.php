<div class="mb-4">
    <a href="{{ route('admin.capital-funding.withdrawals') }}" class="text-xs font-semibold text-gray-500 hover:text-brand">Capital withdrawal workspace →</a>
</div>
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Capital partner</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($capitalWithdrawals as $row)
                @php $when = $row->reviewed_at ?? $row->created_at; @endphp
                <tr>
                    <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                        <p class="font-semibold text-gray-900">{{ format_app_date($when) }}</p>
                        <p class="tabular-nums text-gray-500 mt-0.5">{{ format_app_datetime($when, 'H:i') }}</p>
                    </td>
                    <td class="px-4 py-3 font-medium">{{ $row->lender?->name ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ format_money((float) $row->amount) }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) $row->status) }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.capital-funding.withdrawals') }}" class="text-xs font-semibold text-brand">Review →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No capital withdrawal requests.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $capitalWithdrawals->links() }}</div>
