@props([
    'title',
    'feeLabel',
    'currency' => 'TZS',
    'amount' => null,
    'quote' => null,
    'reference' => null,
    'promoFieldName' => 'promo_code',
    'promoValue' => null,
    'showPromo' => true,
    'formId' => null,
    'applyUrl' => null,
])

@php
    $formId = $formId ?? 'payment-gate-form';
    $applyUrl = $applyUrl ?? url()->current();
@endphp

<div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-6 shadow-lg mb-6">
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
    <form method="GET" action="{{ $applyUrl }}" class="mb-6 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-4 text-sm">
        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.membership.promo_code_label') }}</label>
        <div class="flex gap-2">
            <input type="text" name="{{ $promoFieldName }}" value="{{ $promoValue ?? old($promoFieldName) }}" maxlength="40"
                   class="flex-1 rounded-lg border-gray-300 text-sm font-mono uppercase"
                   placeholder="{{ __('borrower.membership.promo_code_placeholder') }}">
            <button type="submit"
                    class="shrink-0 inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-lg text-sm">
                {{ __('borrower.membership.apply_promo') }}
            </button>
        </div>
        @if ($quote && filled($quote['promo_code'] ?? null))
            <p @class([
                'mt-2 text-xs',
                ($quote['promo_valid'] ?? false) ? 'text-emerald-700' : 'text-red-700',
            ])>
                @if ($quote['promo_valid'] ?? false)
                    {{ __('borrower.membership.promo_applied', ['code' => $quote['promo_code']]) }}
                @else
                    {{ __('borrower.membership.promo_invalid') }}
                @endif
            </p>
        @endif
    </form>
@endif
