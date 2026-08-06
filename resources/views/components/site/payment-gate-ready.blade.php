@props([
    'payment',
    'bankAccounts' => [],
    'mobileDetails' => [],
    'showPromo' => false,
    'quote' => null,
    'promoValue' => null,
])

@php
    $customer = $payment->customer;
@endphp

{{-- Thin wrapper: every awaiting CustomerPayment uses the shared PSP gate. --}}
<x-site.psp-payment-gate
    :label="$payment->typeLabel()"
    :amount="$payment->amount"
    :currency="$payment->currency ?: 'TZS'"
    :reference="$payment->reference"
    :form-action="route('site.borrower.payments.pay', $payment)"
    method-field="payment_method"
    mobile-field="mobile_number"
    mobile-value="mobile_money"
    bank-value="bank_transfer"
    :default-method="old('payment_method', 'mobile_money')"
    :bank-accounts="$bankAccounts"
    :mobile-details="$mobileDetails"
    :mobile-input-value="old('mobile_number', $payment->mobile_number ?: ($customer->phone ?? ''))"
    :country-code="$customer->country_code ?? 'TZ'"
    :show-promo="$showPromo"
    :promo-value="$promoValue"
    :quote="$quote"
    :show-bank="true"
    :show-mobile="true"
/>
