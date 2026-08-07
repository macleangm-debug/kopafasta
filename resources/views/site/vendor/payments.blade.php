<x-site.vendor-layout :title="__('site.partner_portal.nav_payments')" active="payments">

    <h1 class="text-2xl font-extrabold mb-1">{{ __('site.partner_portal.payments_title') }}</h1>
    <p class="text-sm text-gray-500 mb-5">{{ __('site.partner_portal.payments_subtitle') }}</p>

    @if (! $payments->isEmpty() || (float) ($totals['paid'] ?? 0) > 0 || (float) ($totals['pending'] ?? 0) > 0 || (int) ($totals['count'] ?? 0) > 0)
        <div class="grid sm:grid-cols-3 gap-3 mb-6">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <p class="text-xs text-gray-500 uppercase">{{ __('site.partner_portal.payments_earned') }}</p>
                <p class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $fmt($totals['paid']) }}</p>
            </div>
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <p class="text-xs text-gray-500 uppercase">{{ __('site.partner_portal.payments_pending') }}</p>
                <p class="text-2xl font-extrabold text-amber-700 mt-1">{{ $fmt($totals['pending']) }}</p>
            </div>
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <p class="text-xs text-gray-500 uppercase">{{ __('site.partner_portal.payments_count') }}</p>
                <p class="text-2xl font-extrabold mt-1">{{ $totals['count'] }}</p>
            </div>
        </div>
    @endif

    @if ($payments->isEmpty())
        <x-site.empty-state
            icon="💳"
            :title="__('site.partner_portal.payments_empty_title')"
            :description="__('site.partner_portal.payments_empty_desc')"
        />
    @else
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-3">{{ __('site.partner_portal.payments_col_invoice') }}</th>
                        <th class="text-left px-4 py-3">{{ __('site.partner_portal.payments_col_task') }}</th>
                        <th class="text-left px-4 py-3">{{ __('site.partner_portal.payments_col_amount') }}</th>
                        <th class="text-left px-4 py-3">{{ __('site.partner_portal.payments_col_status') }}</th>
                        <th class="text-left px-4 py-3">{{ __('site.partner_portal.payments_col_paid') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($payments as $p)
                        @php $pc = $p->status === 'paid' ? 'emerald' : ($p->status === 'cancelled' ? 'gray' : 'amber'); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $p->invoice_number }}</td>
                            <td class="px-4 py-3">{{ $p->task ? ucfirst(str_replace('_',' ', $p->task->task_type)) : '—' }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $fmt($p->amount) }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ $p->status }}</span></td>
                            <td class="px-4 py-3 text-gray-600">{{ $p->paid_at?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('site.partner.invoice', $p) }}" class="text-brand hover:underline text-sm font-semibold">{{ __('site.partner_portal.payments_col_invoice') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $payments->links() }}</div>
    @endif
</x-site.vendor-layout>
