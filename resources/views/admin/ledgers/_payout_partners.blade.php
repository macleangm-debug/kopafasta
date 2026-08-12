<div class="mb-4 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('admin.payments.ledger', ['direction' => 'out', 'tab' => 'partners']) }}" @class(['font-medium', 'text-amber-700' => $status === '', 'text-gray-500' => $status !== ''])>All</a>
    @foreach ($statuses as $item)
        <a href="{{ route('admin.payments.ledger', ['direction' => 'out', 'tab' => 'partners', 'status' => $item]) }}" @class(['font-medium capitalize', 'text-amber-700' => $status === $item, 'text-gray-500' => $status !== $item])>{{ $item }}</a>
    @endforeach
    <a href="{{ route('admin.partner-payments.index') }}" class="ml-auto text-xs font-semibold text-gray-500 hover:text-brand">Classic queue →</a>
</div>

<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Invoice</th>
                <th class="px-4 py-3">Partner</th>
                <th class="px-4 py-3">Source</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($partnerPayments as $payment)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $payment->invoice_number }}</td>
                    <td class="px-4 py-3">{{ $payment->partner?->name ?? $payment->vendor?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $payment->description ?? str_replace('_', ' ', $payment->source_type ?? '—') }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ format_money((float) $payment->amount) }}</td>
                    <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        @if ($payment->status === 'pending')
                            <form method="post" action="{{ route('admin.partner-payments.approve', $payment) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-emerald-700 hover:text-emerald-900">Approve</button>
                            </form>
                            <form method="post" action="{{ route('admin.partner-payments.cancel', $payment) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-red-700 hover:text-red-900">Cancel</button>
                            </form>
                        @elseif ($payment->partnerSettlement)
                            <a href="{{ route('admin.partner-settlements.show', $payment->partnerSettlement) }}" class="text-amber-700">Batch</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No partner payouts found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $partnerPayments->links() }}</div>
