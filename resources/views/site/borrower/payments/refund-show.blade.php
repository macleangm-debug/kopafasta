<x-site.borrower-layout :title="brand_title(__('borrower.payments_page.refund.title'))" active="payments" content-width="wide">

    <div class="mb-4">
        <a href="{{ route('site.borrower.payments') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('borrower.payments_page.back_payments') }}</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="glass-card overflow-hidden ring-1 ring-brand/15">
        <div class="kf-premium-panel px-6 py-5 relative">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative">
                <p class="text-[10px] uppercase tracking-widest text-white/70">{{ __('borrower.payments_page.refund.title') }}</p>
                <p class="text-3xl font-extrabold mt-1 tabular-nums">{{ format_money($refund->amount) }}</p>
                <p class="text-sm text-white/80 mt-1 font-mono">{{ $refund->reference }}</p>
                @php
                    $refundStatuses = __('borrower.payments_page.refund.statuses');
                    $badge = match ($refund->status) {
                        'pending', 'awaiting_payout' => 'bg-amber-500/20 text-amber-100 ring-amber-400/40',
                        'paid' => 'bg-emerald-500/20 text-emerald-100 ring-emerald-400/40',
                        default => 'bg-white/15 text-white ring-white/30',
                    };
                @endphp
                <p class="mt-3">
                    <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badge }}">
                        {{ $refundStatuses[$refund->status] ?? ucfirst(str_replace('_', ' ', $refund->status)) }}
                    </span>
                </p>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            <p class="text-sm text-gray-600">{{ __('borrower.payments_page.refund.loan_label', ['number' => $refund->loan?->loan_number ?: __('borrower.kyc_tab.not_provided')]) }}</p>

            @if ($refund->status === 'paid')
                <div class="mt-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
                    {{ __('borrower.payments_page.refund.paid_on', [
                        'date' => $refund->paid_at?->format('d M Y'),
                        'reference' => $refund->payment_reference,
                    ]) }}
                </div>
            @elseif ($refund->needsPayoutDetails())
                <form method="POST" action="{{ route('site.borrower.refunds.details', $refund) }}" class="space-y-4 border-t border-gray-100 pt-6 mt-6" x-data="{ channel: '{{ old('payout_channel', $refund->payout_channel ?? 'mobile_money') }}' }"
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
                            <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/50 p-3 text-sm font-medium transition">{{ __('borrower.payments_page.refund.mobile_money') }}</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="payout_channel" value="bank" x-model="channel" class="sr-only peer">
                            <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/50 p-3 text-sm font-medium transition">{{ __('borrower.payments_page.refund.bank') }}</div>
                        </label>
                    </div>
                    <div x-show="channel === 'mobile_money'" class="space-y-2">
                        <input type="text" name="payout_phone" value="{{ old('payout_phone', $refund->payout_phone ?? $customer->phone) }}" required placeholder="{{ __('borrower.payments_page.refund.phone_placeholder') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-brand text-sm px-3 py-2.5">
                        <input type="text" name="payout_provider" value="{{ old('payout_provider', $refund->payout_provider) }}" placeholder="{{ __('borrower.payments_page.refund.provider_placeholder') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-brand text-sm px-3 py-2.5">
                    </div>
                    <div x-show="channel === 'bank'" x-cloak class="space-y-2">
                        <input type="text" name="payout_account_name" value="{{ old('payout_account_name', $refund->payout_account_name) }}" placeholder="{{ __('borrower.payments_page.refund.account_name_placeholder') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-brand text-sm px-3 py-2.5">
                        <input type="text" name="payout_account_number" value="{{ old('payout_account_number', $refund->payout_account_number) }}" placeholder="{{ __('borrower.payments_page.refund.account_number_placeholder') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-brand text-sm px-3 py-2.5">
                    </div>
                    <button class="bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.payments_page.refund.submit_payout') }}</button>
                </form>
            @elseif ($refund->status === 'awaiting_payout')
                <div class="mt-4 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
                    {{ __('borrower.payments_page.refund.processing') }}
                </div>
            @endif
        </div>
    </div>

</x-site.borrower-layout>
