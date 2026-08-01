<x-site.supplier-layout title="Settlements" active="settlements">
    <x-site.borrower-page-header
        eyebrow="Supplier"
        title="Settlement status"
        subtitle="Track pending, approved, and paid partner payouts. Weekly batches are created every Friday."
    />
    <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                <tr>
                    <th class="px-4 py-3 font-semibold">Invoice</th>
                    <th class="px-4 py-3 font-semibold">Amount</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Batch</th>
                    <th class="px-4 py-3 font-semibold">Paid at</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $payment->invoice_number }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ format_money($payment->amount) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                        <td class="px-4 py-3">{{ $payment->partnerSettlement?->reference ?? '—' }}</td>
                        <td class="px-4 py-3">{{ optional($payment->paid_at)->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No settlement records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if (method_exists($payments, 'links'))
        <div class="mt-4">{{ $payments->links() }}</div>
    @endif
</x-site.supplier-layout>
