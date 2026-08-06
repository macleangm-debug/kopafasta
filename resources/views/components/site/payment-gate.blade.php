@props([
    'title',
    'feeLabel',
    'currency' => 'TZS',
    'amount' => null,
    'quote' => null,
    'reference' => null,
    'promoFieldName' => 'promo_code',
    'promoValue' => null,
    'showPromo' => null,
    'paymentType' => null,
    'formId' => null,
    'applyUrl' => null,
])

@php
    $formId = $formId ?? 'payment-gate-form';
    $applyUrl = $applyUrl ?? url()->current();
    if ($showPromo === null) {
        $showPromo = $paymentType
            ? \App\Services\CustomerPaymentService::supportsCodeDiscounts((string) $paymentType)
            : true;
    }
@endphp

<div class="rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white p-6 shadow-lg mb-6 ring-1 ring-brand/20">
    @if ($quote && ($quote['base'] ?? 0) > 0)
        <x-site.payment-gate-breakdown :label="$feeLabel" :currency="$currency" :quote="$quote" class="mb-0" />
    @elseif ($amount !== null)
        <p class="text-[10px] uppercase tracking-widest text-white/80">{{ $feeLabel }}</p>
        <p class="mt-1 text-3xl font-extrabold">{{ $currency }} {{ format_number($amount) }}</p>
    @endif

    @if ($reference)
        <p class="mt-3 text-xs text-white/90">{{ __('borrower.membership.payment_reference_label') }}</p>
        <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $reference }}</p>
    @endif
</div>

@if ($showPromo)
    <x-site.promo-code-toggle
        :name="$promoFieldName"
        :value="$promoValue ?? old($promoFieldName)"
        :action="$applyUrl"
        :quote="$quote"
    />
@endif
