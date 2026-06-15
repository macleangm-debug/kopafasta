@props([
    'feeQuote' => null,
    'bankAccounts' => [],
    'currency' => 'TZS',
    'paymentReference' => null,
    'referralWallet' => null,
    'referralSettings' => [],
    'paymentGatewayDummy' => true,
])

<div x-show="stepKey === 'application_fee'" class="p-6 sm:p-8">
    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.application_fee.title') }}</h2>
    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.application_fee.subtitle') }}</p>

    @if ($paymentGatewayDummy)
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900 mb-6">
            <p class="font-semibold">{{ __('borrower.apply.application_fee.dummy_banner_title') }}</p>
            <p class="mt-1 text-amber-800">{{ __('borrower.apply.application_fee.dummy_banner') }}</p>
        </div>
    @endif

    <div x-show="applicationFeeState?.status === 'pending'" x-cloak class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 text-sm text-sky-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.application_fee.bank_submitted', ['ref' => $paymentReference ?? '—']) }}</p>
        <p class="mt-1 text-xs font-mono" x-show="applicationFeeState?.reference" x-text="applicationFeeState.reference"></p>
        <p class="mt-2 text-xs">{{ __('borrower.membership.bank_hint') }}</p>
    </div>

    <div x-show="applicationFeePaid" x-cloak class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4 text-sm text-emerald-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.application_fee.already_paid') }}</p>
        <p class="mt-1 text-xs" x-show="applicationFeeState?.reference">
            {{ __('borrower.apply.application_fee.reference') }}:
            <span class="font-mono font-semibold" x-text="applicationFeeState.reference"></span>
        </p>
    </div>

    <div x-show="!applicationFeePaid && effectiveFeeAmount() <= 0" x-cloak class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 text-sm text-gray-700 mb-6">
        {{ __('borrower.apply.application_fee.waived') }}
    </div>

    <div x-show="showsApplicationFeePayment()" x-cloak>
        <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-6 shadow-lg mb-6">
            <template x-if="feeQuoteData && feeQuoteData.base > 0">
                <div class="mb-3 text-sm space-y-1.5">
                    <p class="text-[10px] uppercase tracking-widest text-white/70">{{ __('borrower.apply.application_fee.amount_label') }}</p>
                    <div class="flex justify-between gap-4"><span class="text-white/80">{{ __('borrower.apply.application_fee.amount_label') }}</span><span class="font-mono" x-text="formatTzs(feeQuoteData.base)"></span></div>
                    <template x-if="feeQuoteData.promo_discount > 0"><div class="flex justify-between gap-4"><span class="text-white/80">Promo discount</span><span class="font-mono text-emerald-200" x-text="'− ' + formatTzs(feeQuoteData.promo_discount)"></span></div></template>
                    <template x-if="feeQuoteData.referral_discount > 0"><div class="flex justify-between gap-4"><span class="text-white/80">Referral discount</span><span class="font-mono text-emerald-200" x-text="'− ' + formatTzs(feeQuoteData.referral_discount)"></span></div></template>
                    <template x-if="feeQuoteData.affiliate_discount > 0"><div class="flex justify-between gap-4"><span class="text-white/80">Affiliate discount</span><span class="font-mono text-emerald-200" x-text="'− ' + formatTzs(feeQuoteData.affiliate_discount)"></span></div></template>
                    <template x-if="feeQuoteData.wallet_applied > 0"><div class="flex justify-between gap-4"><span class="text-white/80">Referral wallet</span><span class="font-mono text-emerald-200" x-text="'− ' + formatTzs(feeQuoteData.wallet_applied)"></span></div></template>
                    <div class="flex justify-between gap-4 font-semibold pt-1 border-t border-white/10"><span>Amount due</span><span class="font-mono" x-text="formatTzs(feeQuoteData.cash_due ?? feeQuoteData.after_discount)"></span></div>
                </div>
            </template>
            <template x-if="!feeQuoteData || feeQuoteData.base <= 0">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/80">{{ __('borrower.apply.application_fee.amount_label') }}</p>
                    <p class="mt-1 text-3xl font-extrabold">{{ $currency }} <span x-text="formatTzs(effectiveFeeAmount())"></span></p>
                </div>
            </template>
            @if ($paymentReference)
                <p class="mt-3 text-xs text-white/90">{{ __('borrower.membership.payment_reference') }}</p>
                <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $paymentReference }}</p>
            @endif
            <p class="mt-3 text-xs text-white/90">{{ __('borrower.apply.application_fee.product_note') }}</p>
        </div>

        <div class="mb-6 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-4 text-sm">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Promo code (optional)</label>
            <input type="text" x-model="feePromoCode" @change="refreshApplicationFeeQuote()" maxlength="40" class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase" placeholder="PROMO2026">
        </div>

        @if ($feeQuote && ($feeQuote['wallet_allowed'] ?? false) && ($referralWallet->balance ?? 0) > 0)
            <div class="mb-6 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-4 py-4 text-sm text-indigo-900">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="feeUseWallet" @change="refreshApplicationFeeQuote()" class="mt-1 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                    <span>
                        {{ __('borrower.apply.application_fee.wallet_label', [
                            'balance' => format_money($referralWallet->balance ?? 0),
                            'percent' => rtrim(rtrim(format_number($referralSettings['wallet_max_fee_percent'] ?? 0, 2), '0'), '.'),
                        ]) }}
                    </span>
                </label>
            </div>
        @endif

        <div class="space-y-5">
            <div>
                <p class="text-xs uppercase tracking-wider text-gray-500 mb-3">{{ __('borrower.membership.payment_method') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" value="mobile_money" x-model="feeChannel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-4">
                            <p class="font-semibold text-sm">{{ __('borrower.membership.mobile_money') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.membership.mobile_hint') }}</p>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" value="bank" x-model="feeChannel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-4">
                            <p class="font-semibold text-sm">{{ __('borrower.membership.bank') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.membership.bank_hint') }}</p>
                        </div>
                    </label>
                </div>
            </div>

            <div x-show="feeChannel === 'mobile_money'" x-transition>
                <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('borrower.membership.mobile_money') }}</label>
                <input type="tel" x-model="feePhone" class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm" placeholder="+255 7XX XXX XXX">
                <p class="mt-2 text-xs text-gray-500">
                    @if ($paymentGatewayDummy)
                        {{ __('borrower.apply.application_fee.dummy_mobile_hint') }}
                    @else
                        {{ __('borrower.apply.application_fee.mobile_hint') }}
                    @endif
                </p>
            </div>

            <div x-show="feeChannel === 'bank'" x-cloak class="rounded-xl bg-sky-50 border border-sky-200 p-4 text-sm">
                @if ($paymentGatewayDummy)
                    <p class="font-semibold text-sky-900 mb-2">{{ __('borrower.apply.application_fee.dummy_banner_title') }}</p>
                    <p class="text-sky-800 text-xs">{{ __('borrower.apply.application_fee.dummy_bank_hint') }}</p>
                @else
                    <p class="font-semibold text-sky-900 mb-2">{{ __('borrower.apply.application_fee.bank_instructions') }}</p>
                    @if ($paymentReference)
                        <p class="text-sky-800 text-xs mb-3">{{ __('borrower.apply.application_fee.bank_reference', ['ref' => $paymentReference]) }}</p>
                    @endif
                    @foreach ($bankAccounts as $acct)
                        <div class="mb-2 last:mb-0 text-xs text-sky-800">
                            <p class="font-medium">{{ $acct['bank'] }}</p>
                            <p>{{ $acct['account_name'] }} · {{ $acct['account_number'] }}</p>
                        </div>
                    @endforeach
                @endif
            </div>

            <button type="button"
                    @click="payApplicationFee()"
                    :disabled="feePaying"
                    class="w-full bg-gray-900 hover:bg-gray-800 disabled:opacity-60 text-white font-semibold px-5 py-3 rounded-full text-sm">
                <span x-text="feePaying ? @js(__('borrower.apply.application_fee.processing')) : (@js($paymentGatewayDummy) ? @js(__('borrower.apply.application_fee.dummy_pay')) : (feeChannel === 'mobile_money' ? @js(__('borrower.membership.pay_now')) : @js(__('borrower.membership.submit_bank'))))"></span>
            </button>
        </div>
    </div>
</div>
