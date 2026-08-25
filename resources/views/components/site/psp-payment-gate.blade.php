{{--
  Canonical borrower PSP payment gate.
  Green amount card + white method card (Mobile Money | Bank Transfer) + Pay now.
  Reuse for membership, insurance, repayments, fees — only labels/amounts/actions change.
--}}
@props([
    'label',
    'amount' => 0,
    'currency' => 'TZS',
    'reference' => null,
    'formAction',
    'methodField' => 'payment_method',
    'mobileField' => 'mobile_number',
    'mobileValue' => 'mobile_money',
    'bankValue' => 'bank_transfer',
    'defaultMethod' => null,
    'bankAccounts' => [],
    'mobileDetails' => [],
    'mobileInputValue' => null,
    'countryCode' => 'TZ',
    'showPromo' => false,
    'promoName' => 'promo_code',
    'promoValue' => null,
    'promoAction' => null,
    'quote' => null,
    'showMobile' => true,
    'showBank' => true,
    'payLabel' => null,
    'payingLabel' => null,
    'hidden' => [],
    'confirmTitle' => null,
    'confirmMessage' => null,
])

@php
    $payLabel = $payLabel ?? __('borrower.membership.pay_now');
    $payingLabel = $payingLabel ?? __('borrower.membership.paying');
    $defaultMethod = $defaultMethod ?? old($methodField, $mobileValue);
    $promoAction = $promoAction ?? url()->current();
    $confirmTitle = $confirmTitle ?? __('borrower.membership.pay_confirm_title');
    $confirmMessage = $confirmMessage ?? __('borrower.membership.pay_confirm_message', ['label' => $label]);
@endphp

<div {{ $attributes->class('space-y-5') }}>
    <div class="rounded-3xl kf-premium-panel">
        <div class="px-6 py-7">
            <p class="text-[10px] uppercase tracking-[0.2em] text-white/70 font-semibold">{{ $label }}</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <p class="text-4xl font-extrabold tabular-nums tracking-tight">{{ $currency }} {{ format_number((float) $amount) }}</p>
            </div>
            @if ($reference)
                <p class="mt-4 text-xs text-white/70">{{ __('borrower.membership.payment_reference_label') }}</p>
                <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1.5 rounded-lg">{{ $reference }}</p>
            @endif
        </div>
        {{ $amountFooter ?? '' }}
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5 sm:p-6 space-y-5">
        @if (isset($promo))
            {{ $promo }}
        @elseif ($showPromo)
            <x-site.promo-code-toggle
                :name="$promoName"
                :value="$promoValue ?? old($promoName)"
                :action="$promoAction"
                :quote="$quote"
            />
            <div class="border-t border-gray-100"></div>
        @endif

        @if (($errors ?? null)?->any())
            <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-2 text-sm text-rose-900">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}" class="space-y-5"
              x-data="{ paying: false }"
              @submit.prevent="window.confirmForm($el, {
                  title: @js($confirmTitle),
                  message: @js($confirmMessage),
                  confirmLabel: @js($payLabel),
                  tone: 'confirm'
              })"
              @sync-before-submit="paying = true"
              {{ $formAttributes ?? '' }}>
            @csrf
            @foreach ($hidden as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach

            {{ $beforeMethods ?? '' }}

            <x-site.payment-method-picker
                :amount="$amount"
                :method-field="$methodField"
                :mobile-field="$mobileField"
                :mobile-value="$mobileValue"
                :bank-value="$bankValue"
                :default-method="$defaultMethod"
                :mobile-details="$mobileDetails"
                :bank-accounts="$bankAccounts"
                :bank-reference="$reference"
                :show-mobile="$showMobile"
                :show-bank="$showBank"
                :mobile-input-value="$mobileInputValue"
                :country-code="$countryCode"
            >
                {{ $bankExtra ?? '' }}
            </x-site.payment-method-picker>

            {{ $afterMethods ?? '' }}

            <button type="submit" :disabled="paying"
                    class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3.5 rounded-xl text-sm shadow-sm disabled:opacity-70 inline-flex items-center justify-center gap-2">
                <svg x-show="paying" x-cloak class="size-4 animate-spin shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span x-show="!paying">{{ $payLabel }}</span>
                <span x-cloak x-show="paying">{{ $payingLabel }}</span>
            </button>
        </form>
    </div>
</div>
