<x-admin.layout title="Borrower Refunds" heading="Borrower Refunds" subheading="Auction surpluses and other refunds owed to borrowers">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['awaiting_payout' => 'Awaiting payout', 'pending' => 'Needs details', 'paid' => 'Paid', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.borrower-refunds.index', $key === 'all' ? [] : ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium {{ $status === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
                @if ($key !== 'all' && isset($counts[$key]))
                    <span class="ml-1 text-xs opacity-70">({{ $counts[$key] }})</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Reference</th>
                    <th class="px-5 py-3">Borrower</th>
                    <th class="px-5 py-3">Loan</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($refunds as $refund)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $refund->reference }}</td>
                        <td class="px-5 py-3">{{ $refund->customer?->first_name }} {{ $refund->customer?->last_name }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $refund->loan?->loan_number ?? '—' }}</td>
                        <td class="px-5 py-3 text-right font-mono font-semibold">{{ format_money($refund->amount) }}</td>
                        <td class="px-5 py-3 capitalize">{{ str_replace('_', ' ', $refund->status) }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.borrower-refunds.show', $refund) }}" class="text-amber-700 font-semibold hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No refunds in this queue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $refunds->links() }}</div>
</x-admin.layout>
