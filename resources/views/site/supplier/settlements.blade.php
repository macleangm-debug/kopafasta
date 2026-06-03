<x-site.supplier-layout title="Settlements" active="settlements">
    <h1 class="text-2xl font-bold mb-2">Settlement status</h1>
    <p class="text-sm text-gray-500 mb-6">Track pending, approved, and paid partner payouts. Weekly batches are created every Friday.</p>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Batch</th><th class="px-4 py-3">Paid at</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $payment->invoice_number }}</td>
                        <td class="px-4 py-3">{{ format_money($payment->amount) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                        <td class="px-4 py-3">{{ $payment->partnerSettlement?->reference ?? '—' }}</td>
                        <td class="px-4 py-3">{{ optional($payment->paid_at)->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No settlement records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-site.supplier-layout>
