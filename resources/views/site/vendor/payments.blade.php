<x-site.vendor-layout :title="__('site.partner_portal.nav_payments')" active="payments">

    <h1 class="text-2xl font-extrabold mb-1">{{ __('site.partner_portal.payments_title') }}</h1>
    <p class="text-sm text-gray-500 mb-5">{{ __('site.partner_portal.payments_subtitle') }}</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <p class="text-xs text-gray-500 uppercase">{{ __('site.partner_portal.wallet_available') }}</p>
            <p class="text-2xl font-extrabold text-brand mt-1">{{ $fmt($totals['available'] ?? 0) }}</p>
        </div>
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <p class="text-xs text-gray-500 uppercase">{{ __('site.partner_portal.payments_pending') }}</p>
            <p class="text-2xl font-extrabold text-amber-700 mt-1">{{ $fmt($totals['pending']) }}</p>
        </div>
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <p class="text-xs text-gray-500 uppercase">{{ __('site.partner_portal.payments_earned') }}</p>
            <p class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $fmt($totals['paid']) }}</p>
        </div>
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <p class="text-xs text-gray-500 uppercase">{{ __('site.partner_portal.payments_count') }}</p>
            <p class="text-2xl font-extrabold mt-1">{{ $totals['count'] }}</p>
        </div>
    </div>

    @if (($totals['available'] ?? 0) > 0)
        <form method="POST" action="{{ route('site.partner.payments.payout-request') }}"
              class="mb-6 glass-card rounded-2xl ring-1 ring-brand/10 p-5 grid sm:grid-cols-3 gap-3 items-end"
              @submit.prevent="window.confirmForm($el, {
                  title: @js(__('site.partner_portal.confirm.payout_title')),
                  message: @js(__('site.partner_portal.confirm.payout_message')),
                  confirmLabel: @js(__('site.partner_portal.confirm.payout_button')),
                  tone: 'warning',
                  confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900',
              })">
            @csrf
            <div>
                <label class="block text-xs text-gray-600 mb-1">{{ __('site.partner_portal.payout_amount') }}</label>
                <input type="number" name="amount" min="1" step="1" max="{{ (int) ($totals['available'] ?? 0) }}" required
                       class="w-full rounded-lg border-gray-300 text-sm px-3 py-2"
                       placeholder="{{ (int) ($totals['available'] ?? 0) }}">
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <p class="text-[11px] text-gray-500 mt-1">{{ __('site.partner_portal.payout_to_account_hint') }}</p>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs text-gray-600 mb-1">{{ __('site.partner_portal.payout_notes') }}</label>
                <input type="text" name="notes" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2"
                       placeholder="{{ __('site.partner_portal.payout_notes_placeholder') }}">
            </div>
            <button type="submit" class="sm:col-span-3 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm w-full sm:w-auto">
                {{ __('site.partner_portal.payout_submit') }}
            </button>
        </form>
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
