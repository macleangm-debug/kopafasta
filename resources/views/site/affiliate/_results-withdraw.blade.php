<div x-show="withdrawing" x-cloak class="relative mt-5 rounded-2xl bg-white/10 ring-1 ring-white/20 p-4">
    <form id="payout-form" method="POST" action="{{ route('site.affiliate.wallet.payout-request') }}" class="space-y-4"
          x-data="{ amount: @js(old('amount', (int) max($minPayout ?? 0, $available ?? 0))) }">
        @csrf
        <div x-show="step === 'form'">
            <h2 class="text-sm font-bold text-white">{{ __('site.affiliate_portal.withdraw') }}</h2>
            <p class="text-sm text-white/80 mt-1">{{ __('site.affiliate_portal.available_balance', ['amount' => format_money($available)]) }}</p>
            <div class="mt-4">
                <p class="text-xs font-medium text-white/70">{{ __('site.affiliate_portal.payout_account') }}</p>
                <p class="mt-1 text-sm font-semibold text-white">{{ $payoutAccountLabel ?? '—' }}</p>
            </div>
            <div class="mt-4 grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-white/70 mb-1">{{ __('site.affiliate_portal.payout_amount') }}</label>
                    <input type="number" name="amount" min="{{ (int) $minPayout }}" max="{{ (int) $available }}" step="1000" required
                           x-model="amount"
                           value="{{ old('amount', (int) max($minPayout, $available)) }}"
                           class="w-full rounded-xl border-0 bg-white text-gray-900 ring-1 ring-white/30 px-3 py-2.5 text-sm focus:ring-brand-gold outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-white/70 mb-1">{{ __('site.affiliate_portal.payout_notes') }}</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500"
                           class="w-full rounded-xl border-0 bg-white text-gray-900 ring-1 ring-white/30 px-3 py-2.5 text-sm focus:ring-brand-gold outline-none">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" @click="step = 'review'"
                        class="bg-brand-gold text-brand font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.review_withdrawal') }}</button>
                <button type="button" @click="withdrawing = false"
                        class="inline-flex justify-center rounded-xl ring-1 ring-white/30 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10">{{ __('site.affiliate_portal.cancel_withdraw') }}</button>
            </div>
        </div>
        <div x-show="step === 'review'" x-cloak>
            <h2 class="text-sm font-bold text-white">{{ __('site.affiliate_portal.review_withdrawal') }}</h2>
            <p class="mt-3 text-sm text-white/85">{{ __('site.affiliate_portal.withdraw_confirm_body') }}</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-white/70">{{ __('site.affiliate_portal.payout_amount') }}</dt>
                    <dd class="font-semibold tabular-nums" x-text="amount"></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-white/70">{{ __('site.affiliate_portal.payout_account') }}</dt>
                    <dd class="font-semibold text-right">{{ $payoutAccountLabel ?? '—' }}</dd>
                </div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" class="bg-brand-gold text-brand font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.confirm_withdrawal') }}</button>
                <button type="button" @click="step = 'form'"
                        class="inline-flex justify-center rounded-xl ring-1 ring-white/30 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10">{{ __('site.affiliate_portal.back_to_amount') }}</button>
            </div>
        </div>
    </form>
</div>
