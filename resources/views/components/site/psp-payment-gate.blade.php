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
    'walletReward' => null,
    'showMobile' => true,
    'showBank' => true,
    'payLabel' => null,
    'payingLabel' => null,
    'hidden' => [],
    'formAttributes' => null,
])

@php
    $payLabel = $payLabel ?? __('borrower.membership.pay_now');
    $payingLabel = $payingLabel ?? __('borrower.membership.paying');
    $defaultMethod = $defaultMethod ?? old($methodField, $mobileValue);
    $promoAction = $promoAction ?? url()->current();
@endphp

<div {{ $attributes->class('space-y-5') }}>
    <div class="rounded-3xl kf-premium-panel">
        <div class="px-6 py-7">
            <p class="text-[10px] uppercase tracking-[0.2em] text-white/70 font-semibold">{{ $label }}</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <p class="text-4xl font-extrabold tabular-nums tracking-tight">
                    <span x-show="!applyReward">{{ $currency }} {{ format_number((float) $amount) }}</span>
                    <span x-cloak x-show="applyReward">{{ $currency }} {{ format_number((float) (is_array($walletReward) ? max(0, $amount - ($walletReward['discount'] ?? 0)) : $amount)) }}</span>
                </p>
            </div>
            @if (is_array($walletReward) && ($walletReward['discount'] ?? 0) > 0)
                <div class="mt-4 rounded-xl bg-white/10 ring-1 ring-white/15 px-4 py-3 text-sm space-y-2">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-bold">{{ __('borrower.payments_page.show.you_have_reward') }}</p>
                    <p class="font-semibold">{{ $walletReward['label'] }}</p>
                    <template x-if="applyReward">
                        <p class="text-white/80 text-xs">{{ $currency }} {{ format_number((float) $amount) }} · {{ __('borrower.payments_page.show.reward_minus') }} {{ format_money((float) $walletReward['discount']) }}</p>
                    </template>
                    <button type="button" @click="toggleReward()"
                            class="mt-1 inline-flex rounded-lg bg-brand-gold text-brand text-xs font-bold px-3 py-1.5">
                        <span x-show="!applyReward">{{ __('borrower.payments_page.show.apply_reward') }}</span>
                        <span x-cloak x-show="applyReward">{{ __('borrower.payments_page.show.remove_reward') }}</span>
                    </button>
                </div>
            @endif
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
            <div x-show="!applyReward">
            <x-site.promo-code-toggle
                :name="$promoName"
                :value="$promoValue ?? old($promoName)"
                :action="$promoAction"
                :quote="$quote"
            />
            </div>
            <p x-cloak x-show="applyReward" class="text-xs text-gray-500">{{ __('borrower.payments_page.show.reward_or_promo') }}</p>
            <div class="border-t border-gray-100"></div>
        @endif

        @if (($errors ?? null)?->any())
            <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-2 text-sm text-rose-900">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}" class="space-y-5" data-no-draft
              @submit.prevent="typeof payNow === 'function' ? payNow($el) : $el.submit()"
              {{ $formAttributes ?? '' }}>
            @csrf
            @foreach ($hidden as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="apply_reward" :value="applyReward ? 1 : 0">

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

            <button type="submit" :disabled="paying || busy"
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
