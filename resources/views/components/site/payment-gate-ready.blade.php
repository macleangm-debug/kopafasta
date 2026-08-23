@props([
    'payment',
    'bankAccounts' => [],
    'mobileDetails' => [],
    'showPromo' => false,
    'quote' => null,
    'promoValue' => null,
    'formAction' => null,
    'defaultPhone' => null,
])

@php
    $customer = $payment->customer;
    $defaultPhone = $defaultPhone ?? old('mobile_number', $customer->phone ?? '');
    $formAction = $formAction ?? route('site.borrower.payments.pay', $payment);
@endphp

{{-- Thin wrapper: every awaiting CustomerPayment uses the shared PSP gate. --}}
@php
    $groupBreakdown = data_get($payment->provider_meta, 'apply_context.group_fee_breakdown');
@endphp
<x-site.psp-payment-gate
    :label="$payment->typeLabel()"
    :amount="$payment->amount"
    :currency="$payment->currency ?: 'TZS'"
    :reference="$payment->reference"
    :form-action="$formAction"
    method-field="payment_method"
    mobile-field="mobile_number"
    mobile-value="mobile_money"
    bank-value="bank_transfer"
    :default-method="old('payment_method', 'mobile_money')"
    :bank-accounts="$bankAccounts"
    :mobile-details="$mobileDetails"
    :mobile-input-value="$defaultPhone"
    :country-code="$customer->country_code ?? 'TZ'"
    :show-promo="$showPromo"
    :promo-value="$promoValue"
    :quote="$quote"
    :show-bank="true"
    :show-mobile="true"
>
    @if (is_array($groupBreakdown) && (int) ($groupBreakdown['member_count'] ?? 0) > 1)
        <x-slot:amountFooter>
            <div class="px-6 pb-5 text-sm text-white/85 space-y-1.5 border-t border-white/10 pt-4">
                <div class="flex justify-between gap-3">
                    <span>{{ __('borrower.apply.group.fee_breakdown.per_member') }}</span>
                    <span class="font-mono font-semibold tabular-nums">{{ format_money((float) ($groupBreakdown['per_member'] ?? 0)) }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span>{{ __('borrower.apply.group.fee_breakdown.members') }}</span>
                    <span class="font-semibold tabular-nums">{{ (int) ($groupBreakdown['member_count'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between gap-3 pt-1 font-semibold text-brand-gold">
                    <span>{{ __('borrower.apply.group.fee_breakdown.total') }}</span>
                    <span class="font-mono tabular-nums">{{ format_money((float) $payment->amount) }}</span>
                </div>
            </div>
        </x-slot:amountFooter>
    @endif
</x-site.psp-payment-gate>
