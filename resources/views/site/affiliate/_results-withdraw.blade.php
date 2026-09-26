<div x-show="withdrawing" x-cloak class="relative mt-6 rounded-2xl bg-white text-gray-900 p-5 ring-1 ring-brand/15 shadow-sm">
    @if (! ($hasPayoutAccount ?? false))
        <p class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.withdraw_need_account_title') }}</p>
        <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_portal.withdraw_need_account_body') }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('site.affiliate.profile', ['section' => 'payment']) }}"
               class="inline-flex justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                {{ __('site.affiliate_portal.withdraw_need_account_cta') }} →
            </a>
            <button type="button" @click="withdrawing = false"
                    class="inline-flex justify-center rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                {{ __('site.affiliate_portal.cancel_withdraw') }}
            </button>
        </div>
    @elseif ($available >= $minPayout)
        <form id="payout-form" method="POST" action="{{ route('site.affiliate.wallet.payout-request') }}" class="space-y-4"
              x-data="{ amount: @js(old('amount', (int) max($minPayout, $available))) }">
            @csrf
            <div x-show="step === 'form'">
                <h2 class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.withdraw') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_portal.available_balance', ['amount' => format_money($available)]) }}</p>
                <div class="mt-4">
                    <p class="text-xs font-medium text-gray-600">{{ __('site.affiliate_portal.payout_account') }}</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $payoutAccountLabel ?? '—' }}</p>
                </div>
                <div class="mt-4 grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_amount') }}</label>
                        <input type="number" name="amount" min="{{ (int) $minPayout }}" max="{{ (int) $available }}" step="1000" required
                               x-model="amount"
                               value="{{ old('amount', (int) max($minPayout, $available)) }}"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_notes') }}</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" @click="step = 'review'"
                            class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.review_withdrawal') }}</button>
                    <button type="button" @click="withdrawing = false"
                            class="inline-flex justify-center rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">{{ __('site.affiliate_portal.cancel_withdraw') }}</button>
                </div>
            </div>
            <div x-show="step === 'review'" x-cloak>
                <h2 class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.review_withdrawal') }}</h2>
                <p class="mt-3 text-sm text-gray-700">{{ __('site.affiliate_portal.withdraw_confirm_body') }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('site.affiliate_portal.payout_amount') }}</dt>
                        <dd class="font-semibold tabular-nums" x-text="amount"></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('site.affiliate_portal.payout_account') }}</dt>
                        <dd class="font-semibold text-right">{{ $payoutAccountLabel ?? '—' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.confirm_withdrawal') }}</button>
                    <button type="button" @click="step = 'form'"
                            class="inline-flex justify-center rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">{{ __('site.affiliate_portal.back_to_amount') }}</button>
                </div>
            </div>
        </form>
    @else
        <p class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.withdraw') }}</p>
        <p class="text-sm text-gray-700 mt-1">{{ __('site.affiliate_portal.payout_not_ready', ['amount' => format_money($minPayout), 'available' => format_money($available)]) }}</p>
        <button type="button" @click="withdrawing = false"
                class="mt-4 inline-flex justify-center rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">{{ __('site.affiliate_portal.cancel_withdraw') }}</button>
    @endif
</div>
