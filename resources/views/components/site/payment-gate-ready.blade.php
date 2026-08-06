@props([
    'payment',
    'bankAccounts' => [],
    'mobileDetails' => [],
    'showPromo' => false,
    'quote' => null,
    'promoValue' => null,
])

@php
    $currency = $payment->currency ?: 'TZS';
    $customer = $payment->customer;
    $showBank = ! empty($bankAccounts);
@endphp

{{-- Same payment-gate shell as membership / affiliate fee screens --}}
<div class="rounded-3xl overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-light text-white shadow-lg shadow-brand/20">
    <div class="px-6 py-7">
        <p class="text-[10px] uppercase tracking-[0.2em] text-white/70 font-semibold">{{ $payment->typeLabel() }}</p>
        <div class="mt-3 flex flex-wrap items-end gap-3">
            <p class="text-4xl font-extrabold tabular-nums tracking-tight">{{ $currency }} {{ format_number((float) $payment->amount) }}</p>
        </div>
        <p class="mt-4 text-xs text-white/70">{{ __('borrower.membership.payment_reference_label') }}</p>
        <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1.5 rounded-lg">{{ $payment->reference }}</p>
    </div>
</div>

@if ($showPromo)
    <x-site.promo-code-toggle
        name="promo_code"
        :value="$promoValue ?? old('promo_code')"
        :action="url()->current()"
        :quote="$quote"
    />
@endif

<div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5 sm:p-6 space-y-5">
    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-2 text-sm text-rose-900">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('site.borrower.payments.pay', $payment) }}" class="space-y-5"
          x-data="{ paying: false }" @submit="paying = true">
        @csrf

        <x-site.payment-method-picker
            :amount="$payment->amount"
            method-field="payment_method"
            mobile-field="mobile_number"
            mobile-value="mobile_money"
            bank-value="bank_transfer"
            :default-method="old('payment_method', 'mobile_money')"
            :mobile-details="$mobileDetails"
            :bank-accounts="$bankAccounts"
            :bank-reference="$payment->reference"
            :show-bank="$showBank"
            :mobile-input-value="old('mobile_number', $payment->mobile_number ?: ($customer->phone ?? ''))"
            :country-code="$customer->country_code ?? 'TZ'"
        />

        <button type="submit" :disabled="paying"
                class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3.5 rounded-xl text-sm shadow-sm disabled:opacity-70">
            <span x-show="!paying">{{ __('borrower.membership.pay_now') }}</span>
            <span x-cloak x-show="paying">{{ __('borrower.membership.paying') }}</span>
        </button>
    </form>
</div>
