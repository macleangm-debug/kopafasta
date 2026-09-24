<x-site.supplier-layout :title="__('site.supplier_portal.money_title')" active="settlements">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        @foreach ([
            [__('site.supplier_portal.money_available'), format_money($money['available'] ?? 0)],
            [__('site.supplier_portal.money_pending'), format_money($money['pending'] ?? 0)],
            [__('site.supplier_portal.money_paid'), format_money($money['paid'] ?? 0)],
        ] as [$label, $value])
            <div class="glass-card rounded-2xl ring-1 ring-brand/15 p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-brand tabular-nums mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <h2 class="font-bold text-gray-900 mb-3">{{ __('site.supplier_portal.settlements_title') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ __('site.supplier_portal.settlements_subtitle') }}</p>

    @if ($payments->isEmpty())
        <x-site.empty-state
            icon="💳"
            :title="__('site.supplier_portal.settlements_empty_title')"
            :description="__('site.supplier_portal.settlements_empty_desc')"
        />
    @else
        <div class="hidden sm:block glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
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
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <p>{{ $payment->invoice_number }}</p>
                                @if (filled($payment->description))
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $payment->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money($payment->amount) }}</td>
                            <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                            <td class="px-4 py-3">{{ $payment->partnerSettlement?->reference ?? '—' }}</td>
                            <td class="px-4 py-3">{{ optional($payment->paid_at)->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="sm:hidden space-y-2">
            @foreach ($payments as $payment)
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3">
                    <p class="font-semibold text-gray-900">{{ format_money($payment->amount) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $payment->invoice_number }} · {{ ucfirst($payment->status) }}</p>
                    @if (filled($payment->description))
                        <p class="text-xs text-gray-600 mt-1">{{ $payment->description }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if (method_exists($payments, 'links'))
            <div class="mt-4">{{ $payments->links() }}</div>
        @endif
    @endif
</x-site.supplier-layout>
