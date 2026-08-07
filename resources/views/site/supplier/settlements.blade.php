<x-site.supplier-layout :title="__('site.supplier_portal.nav_settlements')" active="settlements">
    <x-site.borrower-page-header
        :eyebrow="__('site.supplier_portal.settlements_eyebrow')"
        :title="__('site.supplier_portal.settlements_title')"
        :subtitle="__('site.supplier_portal.settlements_subtitle')"
    />

    @if ($payments->isEmpty())
        <x-site.empty-state
            icon="💳"
            :title="__('site.supplier_portal.settlements_empty_title')"
            :description="__('site.supplier_portal.settlements_empty_desc')"
        />
    @else
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.settlements_col_invoice') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.settlements_col_amount') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.settlements_col_status') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.settlements_col_batch') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.settlements_col_paid') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $payment->invoice_number }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money($payment->amount) }}</td>
                            <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                            <td class="px-4 py-3">{{ $payment->partnerSettlement?->reference ?? '—' }}</td>
                            <td class="px-4 py-3">{{ optional($payment->paid_at)->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (method_exists($payments, 'links'))
            <div class="mt-4">{{ $payments->links() }}</div>
        @endif
    @endif
</x-site.supplier-layout>
