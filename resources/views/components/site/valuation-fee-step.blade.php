{{--
    Standalone valuation fee step — asset-backed products collect this fee via the
    application_fee step (quoted_origination_fee). Kept for admin/marketplace flows
    that may include it separately in future step plans.
--}}
@props([
    'feeQuote' => null,
    'bankAccounts' => [],
    'currency' => 'TZS',
    'paymentReference' => null,
    'referralWallet' => null,
    'referralSettings' => [],
    'paymentGatewayDummy' => true,
])

<div x-show="stepKey === 'valuation_fee'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.valuation_fee')"
        :title="__('borrower.apply.valuation_fee.title')"
        :subtitle="__('borrower.apply.valuation_fee.subtitle')"
    />

    @if ($paymentGatewayDummy)
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900 mb-6">
            <p class="font-semibold">{{ __('borrower.apply.application_fee.dummy_banner_title') }}</p>
            <p class="mt-1 text-amber-800">{{ __('borrower.apply.application_fee.dummy_banner') }}</p>
        </div>
    @endif

    <div x-show="valuationFeeState?.status === 'pending'" x-cloak class="rounded-2xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 text-sm text-sky-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.valuation_fee.bank_submitted', ['ref' => $paymentReference ?? '—']) }}</p>
        <p class="mt-1 text-xs font-mono" x-show="valuationFeeState?.reference" x-text="valuationFeeState.reference"></p>
    </div>

    <div x-show="valuationFeePaid" x-cloak class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4 text-sm text-emerald-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.valuation_fee.already_paid') }}</p>
        <p class="mt-1 text-xs" x-show="valuationFeeState?.reference">
            {{ __('borrower.apply.valuation_fee.reference') }}:
            <span class="font-mono font-semibold" x-text="valuationFeeState.reference"></span>
        </p>
    </div>

    <div x-show="!valuationFeePaid && effectiveValuationFeeAmount() <= 0" x-cloak class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 text-sm text-gray-700 mb-6">
        {{ __('borrower.apply.valuation_fee.waived') }}
    </div>

    <div x-show="showsValuationFeePayment()" x-cloak>
        <div class="glass-card overflow-hidden ring-1 ring-brand/15 mb-6">
            <div class="bg-gradient-to-br from-brand to-brand-light text-white px-6 py-5">
                <p class="text-[10px] uppercase tracking-widest text-white/80">{{ __('borrower.apply.valuation_fee.amount_label') }}</p>
                @if ($feeQuote && ($feeQuote['base'] ?? 0) > ($feeQuote['after_discount'] ?? 0))
                    <p class="mt-1 text-sm text-white/80 line-through tabular-nums">{{ $currency }} {{ format_number($feeQuote['base']) }}</p>
                @endif
                <p class="mt-2 text-3xl font-extrabold tabular-nums">{{ $currency }} <span x-text="formatTzs(effectiveValuationFeeAmount())"></span></p>
                @if ($paymentReference)
                    <p class="mt-3 text-xs text-white/90">{{ __('borrower.membership.payment_reference') }}</p>
                    <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $paymentReference }}</p>
                @endif
                <p class="mt-3 text-xs text-white/90">{{ __('borrower.apply.valuation_fee.product_note') }}</p>
            </div>
        </div>

        <div class="space-y-5">
            <div>
                <p class="text-xs uppercase tracking-wider text-gray-500 mb-3">{{ __('borrower.membership.payment_method') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" value="mobile_money" x-model="valuationFeeChannel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/30 p-4">
                            <p class="font-semibold text-sm">{{ __('borrower.membership.mobile_money') }}</p>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" value="bank" x-model="valuationFeeChannel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/30 p-4">
                            <p class="font-semibold text-sm">{{ __('borrower.membership.bank') }}</p>
                        </div>
                    </label>
                </div>
            </div>

            <div x-show="valuationFeeChannel === 'mobile_money'" x-cloak>
                <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('borrower.membership.mobile_money') }}</label>
                <input type="tel" x-model="valuationFeePhone" class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm focus:ring-brand" placeholder="+255 7XX XXX XXX">
            </div>

            <button type="button"
                    @click="payValuationFee()"
                    :disabled="valuationFeePaying"
                    class="w-full bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-3 rounded-xl text-sm shadow-sm">
                <span x-text="valuationFeePaying ? @js(__('borrower.apply.valuation_fee.processing')) : (@js($paymentGatewayDummy) ? @js(__('borrower.apply.valuation_fee.dummy_pay')) : @js(__('borrower.membership.pay_now')))"></span>
            </button>
        </div>
    </div>
</div>
