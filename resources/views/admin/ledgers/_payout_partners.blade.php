<div class="mb-4 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('admin.payments.ledger', array_filter(['direction' => 'out', 'tab' => 'partners', 'q' => $q ?? null])) }}" @class(['font-medium', 'text-amber-700' => $status === '', 'text-gray-500' => $status !== ''])>All</a>
    @foreach ($statuses as $item)
        <a href="{{ route('admin.payments.ledger', array_filter(['direction' => 'out', 'tab' => 'partners', 'status' => $item, 'q' => $q ?? null])) }}" @class(['font-medium capitalize', 'text-amber-700' => $status === $item, 'text-gray-500' => $status !== $item])>{{ $item }}</a>
    @endforeach
    <a href="{{ route('admin.partner-payments.index') }}" class="ml-auto text-xs font-semibold text-gray-500 hover:text-brand">Classic queue →</a>
</div>

<form method="GET" action="{{ route('admin.payments.ledger') }}" class="mb-4 flex flex-wrap items-end gap-3">
    <input type="hidden" name="direction" value="out">
    <input type="hidden" name="tab" value="partners">
    @if ($status !== '')
        <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <div class="flex-1 min-w-[16rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Search</label>
        <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Invoice, partner, phone, amount, application, source…"
               class="w-full rounded-lg border-gray-300 text-sm">
    </div>
    <button class="text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-xl">Search</button>
</form>

<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Date</th>
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
                @php $when = $payment->paid_at ?? $payment->approved_at ?? $payment->created_at; @endphp
                <tr>
                    <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                        <p class="font-semibold text-gray-900">{{ format_app_date($when) }}</p>
                        <p class="tabular-nums text-gray-500 mt-0.5">{{ format_app_datetime($when, 'H:i') }}</p>
                    </td>
                    <td class="px-4 py-3 font-medium">{{ $payment->invoice_number }}</td>
                    <td class="px-4 py-3">{{ $payment->partner?->name ?? $payment->vendor?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $payment->description ?? str_replace('_', ' ', $payment->source_type ?? '—') }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ format_money((float) $payment->amount) }}</td>
                    <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <a href="{{ route('admin.partner-payments.show', $payment) }}" class="text-amber-700 hover:text-amber-900 font-semibold">Open</a>
                        @if ($payment->partnerSettlement)
                            <a href="{{ route('admin.partner-settlements.show', $payment->partnerSettlement) }}" class="text-gray-500">Batch</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No partner payouts found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $partnerPayments->links() }}</div>
