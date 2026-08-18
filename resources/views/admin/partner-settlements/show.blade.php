<x-admin.layout :title="'Settlement '.$partnerSettlement->reference" heading="" subheading="">
    <x-admin.letterhead
        kicker="Partner payout"
        :title="'Batch '.$partnerSettlement->reference"
        subtitle="Partner payout batch" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Included payments</h2>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($partnerSettlement->payments as $payment)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $payment->invoice_number }}</td>
                            <td class="px-4 py-3">{{ $payment->description ?? $payment->source_type ?? '—' }}</td>
                            <td class="px-4 py-3">{{ format_money($payment->amount) }}</td>
                            <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 text-sm">
                <dl class="space-y-3">
                    <div><dt class="text-gray-500">Partner</dt><dd class="font-medium">{{ $partnerSettlement->vendor?->name }}</dd></div>
                    <div><dt class="text-gray-500">Total</dt><dd class="font-medium">{{ format_money($partnerSettlement->total_amount) }}</dd></div>
                    <div><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ ucfirst($partnerSettlement->status) }}</dd></div>
                    @if ($partnerSettlement->paid_at)
                        <div><dt class="text-gray-500">Paid at</dt><dd>{{ $partnerSettlement->paid_at->format('d M Y H:i') }}</dd></div>
                    @endif
                </dl>
            </div>

            @if ($partnerSettlement->status === 'pending')
                <form method="post" action="{{ route('admin.partner-settlements.approve', $partnerSettlement) }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-brand-gold px-4 py-2 text-sm font-semibold text-brand hover:brightness-95">Approve batch</button>
                </form>
            @endif

            @if (in_array($partnerSettlement->status, ['pending', 'approved'], true))
                <form method="post" action="{{ route('admin.partner-settlements.mark-paid', $partnerSettlement) }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-5 space-y-3">
                    @csrf
                    <x-admin.input name="channel" label="Payment channel" placeholder="Bank / M-Pesa" />
                    <x-admin.input name="reference" label="Payment reference" />
                    <x-admin.textarea name="notes" label="Notes" rows="2" />
                    <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Mark as paid</button>
                </form>
            @endif
        </div>
    </div>
</x-admin.layout>
