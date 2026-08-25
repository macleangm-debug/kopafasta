@props([
    'paymentLabel' => '',
    'amount' => 0,
    'currency' => 'TZS',
    'reference' => '',
    'formId' => null,
    'methodField' => 'payment_method',
    'mobileField' => 'mobile_number',
    'defaultMethod' => 'mobile_money',
    'mobileThreshold' => null,
    'mobileDetails' => [],
    'bankDetails' => [],
    'showMobile' => true,
    'showBank' => true,
    'countryCode' => null,
    'mobileInputValue' => null,
])

<div class="space-y-5">
    <div class="rounded-2xl kf-premium-panel p-6">
        <p class="text-[10px] uppercase tracking-widest text-white/80">{{ $paymentLabel }}</p>
        <p class="mt-1 text-3xl font-extrabold tabular-nums">{{ $currency }} {{ format_number($amount) }}</p>
        @if ($reference)
            <p class="mt-3 text-xs text-white/90">{{ __('borrower.membership.payment_reference_label') }}</p>
            <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $reference }}</p>
        @endif
    </div>

    <x-site.payment-method-picker
        :amount="$amount"
        :form-id="$formId"
        :method-field="$methodField"
        :mobile-field="$mobileField"
        :default-method="$defaultMethod"
        :mobile-threshold="$mobileThreshold"
        :mobile-details="$mobileDetails"
        :bank-details="$bankDetails"
        :show-mobile="$showMobile"
        :show-bank="$showBank"
        :country-code="$countryCode"
        :mobile-input-value="$mobileInputValue"
    />

    {{ $slot }}
</div>
