@props([
    'feeQuote' => null,
    'bankAccounts' => [],
    'currency' => 'TZS',
    'paymentReference' => null,
    'referralWallet' => null,
    'referralSettings' => [],
])

<div x-show="currentStepKey === 'application_fee'" class="p-6 sm:p-8" x-effect="onApplicationFeeStep()">
    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.application_fee.title') }}</h2>
    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.application_fee.subtitle') }}</p>

    <template x-if="applicationFeeState?.status === 'pending'">
        <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 text-sm text-sky-900 mb-6">
            <p class="font-semibold">{{ __('borrower.apply.application_fee.bank_submitted', ['ref' => $paymentReference ?? '—']) }}</p>
            <p class="mt-1 text-xs font-mono" x-show="applicationFeeState?.reference" x-text="applicationFeeState.reference"></p>
            <p class="mt-2 text-xs">{{ __('borrower.membership.bank_hint') }}</p>
        </div>
    </template>

    <template x-if="applicationFeePaid">
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4 text-sm text-emerald-900 mb-6">
            <p class="font-semibold">{{ __('borrower.apply.application_fee.already_paid') }}</p>
            <p class="mt-1 text-xs" x-show="applicationFeeState?.reference">
                {{ __('borrower.apply.application_fee.reference') }}:
                <span class="font-mono font-semibold" x-text="applicationFeeState.reference"></span>
            </p>
        </div>
    </template>

    <template x-if="!applicationFeePaid && applicationFee <= 0">
        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 text-sm text-gray-700 mb-6">
            {{ __('borrower.apply.application_fee.waived') }}
        </div>
    </template>

    <template x-if="!applicationFeePaid && applicationFee > 0">
        <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-6 shadow-lg mb-6">
            <p class="text-[10px] uppercase tracking-widest text-white/80">{{ __('borrower.apply.application_fee.amount_label') }}</p>
            @if ($feeQuote && ($feeQuote['base'] ?? 0) > ($feeQuote['after_discount'] ?? 0))
                <p class="mt-1 text-sm text-white/80 line-through">{{ $currency }} {{ format_number($feeQuote['base']) }}</p>
            @endif
            <p class="mt-1 text-3xl font-extrabold">{{ $currency }} <span x-text="window.formatNumber ? window.formatNumber(applicationFee) : applicationFee"></span></p>
            @if ($paymentReference)
                <p class="mt-3 text-xs text-white/90">{{ __('borrower.membership.payment_reference') }}</p>
                <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $paymentReference }}</p>
            @endif
            <p class="mt-3 text-xs text-white/90">{{ __('borrower.apply.application_fee.product_note') }}</p>
        </div>

        @if ($feeQuote && ($feeQuote['wallet_usable'] ?? false) && ($referralWallet->balance ?? 0) > 0)
            <div class="mb-6 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-4 py-4 text-sm text-indigo-900">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="feeUseWallet" class="mt-1 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                    <span>
                        {{ __('borrower.apply.application_fee.wallet_label', [
                            'balance' => format_money($referralWallet->balance ?? 0),
                            'percent' => rtrim(rtrim(number_format($referralSettings['wallet_max_fee_percent'] ?? 0, 2), '0'), '.'),
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
                <p class="mt-2 text-xs text-gray-500">{{ __('borrower.apply.application_fee.mobile_hint') }}</p>
            </div>

            <div x-show="feeChannel === 'bank'" x-cloak class="rounded-xl bg-sky-50 border border-sky-200 p-4 text-sm">
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
            </div>

            <button type="button"
                    @click="payApplicationFee()"
                    :disabled="feePaying"
                    class="w-full bg-gray-900 hover:bg-gray-800 disabled:opacity-60 text-white font-semibold px-5 py-3 rounded-full text-sm">
                <span x-text="feePaying ? @js(__('borrower.apply.application_fee.processing')) : (feeChannel === 'mobile_money' ? @js(__('borrower.membership.pay_now')) : @js(__('borrower.membership.submit_bank')))"></span>
            </button>
        </div>
    </template>
</div>
