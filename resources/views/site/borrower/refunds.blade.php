<x-site.borrower-layout :title="brand_title(__('borrower.refunds_page.title'))" active="refunds" content-width="wide">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <x-site.borrower-page-header
        :title="__('borrower.refunds_page.title')"
        :subtitle="__('borrower.refunds_page.subtitle')"
    />

    @php $refundStatuses = __('borrower.payments_page.refund.statuses'); @endphp

    @if ($refunds->isEmpty())
        <x-site.empty-state
            icon="💰"
            :title="__('borrower.refunds_page.empty')"
        />
    @else
        <div class="space-y-4">
            @foreach ($refunds as $refund)
                <div class="glass-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-500">{{ $refund->reference }}</p>
                            <p class="text-2xl font-bold mt-1 tabular-nums">{{ format_money($refund->amount) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.payments_page.refund.loan_label', ['number' => $refund->loan?->loan_number ?? '—']) }}</p>
                        </div>
                        <span @class([
                            'px-3 py-1 rounded-full text-xs font-semibold',
                            'bg-amber-100 text-amber-800' => in_array($refund->status, ['pending', 'awaiting_payout']),
                            'bg-emerald-100 text-emerald-800' => $refund->status === 'paid',
                            'bg-gray-100 text-gray-600' => $refund->status === 'cancelled',
                        ])>{{ $refundStatuses[$refund->status] ?? ucfirst(str_replace('_', ' ', $refund->status)) }}</span>
                    </div>

                    @if ($refund->status === 'paid')
                        <p class="text-sm text-gray-600">{{ __('borrower.payments_page.refund.paid_on', [
                            'date' => $refund->paid_at?->format('d M Y'),
                            'reference' => $refund->payment_reference,
                        ]) }}</p>
                    @elseif ($refund->needsPayoutDetails())
                        <form method="POST" action="{{ route('site.borrower.refunds.details', $refund) }}" class="space-y-4 border-t border-gray-100/80 pt-4" x-data="{ channel: '{{ old('payout_channel', $refund->payout_channel ?? 'mobile_money') }}' }"
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('borrower.payments_page.refund.confirm_title')),
                                  message: @js(__('borrower.payments_page.refund.confirm_message')),
                                  confirmLabel: @js(__('borrower.payments_page.refund.submit_payout')),
                                  tone: 'confirm'
                              })">
                            @csrf
                            <p class="text-sm text-gray-700">{{ __('borrower.payments_page.refund.payout_prompt') }}</p>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="payout_channel" value="mobile_money" x-model="channel" class="sr-only peer">
                                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/40 p-4 text-sm font-semibold transition">{{ __('borrower.payments_page.refund.mobile_money') }}</div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="payout_channel" value="bank" x-model="channel" class="sr-only peer">
                                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/40 p-4 text-sm font-semibold transition">{{ __('borrower.payments_page.refund.bank') }}</div>
                                </label>
                            </div>
                            <div x-show="channel === 'mobile_money'" class="space-y-3">
                                <input type="text" name="payout_phone" value="{{ old('payout_phone', $refund->payout_phone ?? $customer->phone) }}" required placeholder="{{ __('borrower.payments_page.refund.phone_placeholder') }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-brand">
                                <input type="text" name="payout_provider" value="{{ old('payout_provider', $refund->payout_provider) }}" placeholder="{{ __('borrower.payments_page.refund.provider_placeholder') }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-brand">
                            </div>
                            <div x-show="channel === 'bank'" x-cloak class="space-y-3">
                                <input type="text" name="payout_account_name" value="{{ old('payout_account_name', $refund->payout_account_name) }}" placeholder="{{ __('borrower.payments_page.refund.account_name_placeholder') }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-brand">
                                <input type="text" name="payout_account_number" value="{{ old('payout_account_number', $refund->payout_account_number) }}" placeholder="{{ __('borrower.payments_page.refund.account_number_placeholder') }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-brand">
                            </div>
                            <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.payments_page.refund.submit_payout') }}
                            </button>
                        </form>
                    @elseif ($refund->status === 'awaiting_payout')
                        <p class="text-sm text-gray-600">{{ __('borrower.payments_page.refund.processing') }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-site.borrower-layout>
