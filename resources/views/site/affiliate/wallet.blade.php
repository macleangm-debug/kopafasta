<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.wallet_title'))" active="wallet">

    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.nav_wallet')"
        :title="__('site.affiliate_portal.wallet_title')"
        :subtitle="__('site.affiliate_portal.wallet_subtitle')"
    />

    <section class="mb-6 kf-premium-panel rounded-2xl p-6 sm:p-8 relative" x-data="{ withdrawing: {{ $errors->has('amount') || $errors->has('notes') ? 'true' : 'false' }} }">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.hero_available') }}</p>
                <p class="text-3xl sm:text-4xl font-bold mt-1 tabular-nums">{{ format_money($available) }}</p>
                <p class="text-sm text-white/70 mt-2">{{ __('site.affiliate_portal.hero_pending', ['amount' => format_money($pending ?? $totals['pending'] ?? 0)]) }}</p>
                <p class="text-sm text-white/70 mt-1">{{ __('site.affiliate_portal.min_payout_note', ['amount' => format_money($minPayout)]) }}</p>
            </div>
            <button type="button" @click="withdrawing = true"
                    class="inline-flex justify-center bg-white text-brand font-semibold px-6 py-3 rounded-xl text-sm shrink-0 hover:bg-brand-gold transition">
                {{ __('site.affiliate_portal.withdraw') }}
            </button>
        </div>

        <div x-show="withdrawing" x-cloak class="relative mt-6 rounded-2xl bg-white/95 text-gray-900 p-5 ring-1 ring-white/40">
            @if ($available >= $minPayout)
                <form id="payout-form" method="POST" action="{{ route('site.affiliate.wallet.payout-request') }}" class="space-y-4"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('site.affiliate_portal.withdraw_confirm_title')),
                          message: @js(__('site.affiliate_portal.withdraw_confirm_body')),
                          confirmLabel: @js(__('site.affiliate_portal.submit_payout')),
                          tone: 'confirm',
                      })">
                    @csrf
                    <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.withdraw') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.available_balance', ['amount' => format_money($available)]) }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_amount') }}</label>
                            <input type="number" name="amount" min="{{ (int) $minPayout }}" max="{{ (int) $available }}" step="1000" required
                                   value="{{ old('amount', (int) max($minPayout, $available)) }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_notes') }}</label>
                            <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.submit_payout') }}</button>
                        <button type="button" @click="withdrawing = false" class="text-sm font-semibold text-gray-600">{{ __('site.affiliate_portal.cancel_withdraw') }}</button>
                    </div>
                </form>
            @else
                <p class="text-sm text-gray-700">{{ __('site.affiliate_portal.payout_not_ready', ['amount' => format_money($minPayout), 'available' => format_money($available)]) }}</p>
                <button type="button" @click="withdrawing = false" class="mt-3 text-sm font-semibold text-brand">{{ __('site.affiliate_portal.cancel_withdraw') }}</button>
            @endif
        </div>
    </section>

    <div class="glass-card overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">{{ __('site.affiliate_portal.payment_history') }}</h2>
        </div>
        @if ($payments->isEmpty())
            <x-site.empty-state
                icon="💰"
                :title="__('site.affiliate_portal.no_payments')"
                :description="__('site.affiliate_portal.no_payments_hint')"
                :action-label="__('site.affiliate_portal.nav_share')"
                :action-url="route('site.affiliate.share')"
            />
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($payments as $payment)
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-gray-500">{{ $payment->invoice_number ?? '#'.$payment->id }}</p>
                            <p class="font-semibold tabular-nums mt-1">{{ format_money($payment->amount) }}</p>
                        </div>
                        <span class="inline-flex self-start text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1
                            {{ match($payment->status) {
                                'approved' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                                'paid' => 'bg-sky-100 text-sky-800 ring-sky-200',
                                'disputed' => 'bg-red-100 text-red-800 ring-red-200',
                                default => 'bg-amber-100 text-amber-900 ring-amber-200',
                            } }}">
                            {{ __('site.affiliate_portal.'.$payment->status) }}
                        </span>
                    </div>
                @endforeach
            </div>
            @if ($payments->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $payments->links() }}</div>
            @endif
        @endif
    </div>

</x-site.affiliate-layout>
