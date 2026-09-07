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
    'cancelUrl' => null,
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
            <div class="mt-4 space-y-2 text-sm">
                <template x-if="(quoteLines || []).length">
                    <div class="space-y-2">
                        <template x-for="line in quoteLines" :key="line.key">
                            <div class="flex items-start justify-between gap-3" :class="line.kind === 'total' ? 'pt-2 mt-1 border-t border-white/15' : ''">
                                <span class="text-white/80 min-w-0 pr-2" x-text="line.label"></span>
                                <span class="font-bold tabular-nums whitespace-nowrap shrink-0"
                                      :class="line.kind === 'total' ? 'text-brand-gold text-xl' : (line.kind === 'discount' ? 'text-brand-gold' : '')"
                                      x-text="line.display || formatLineAmount(line)"></span>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!(quoteLines || []).length">
                    <p class="text-4xl font-extrabold tabular-nums tracking-tight">
                        {{ $currency }} {{ format_number((float) $amount) }}
                    </p>
                </template>
            </div>
            @if ($reference)
                <p class="mt-4 text-xs text-white/70">{{ __('borrower.membership.payment_reference_label') }}</p>
                <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1.5 rounded-lg">{{ $reference }}</p>
            @endif
        </div>
        {{ $amountFooter ?? '' }}
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5 sm:p-6 space-y-5">
        <div x-show="checkoutStep === 'adjust'" class="space-y-5">
            @php
                $hasReward = is_array($walletReward) && ($walletReward['discount'] ?? 0) > 0;
                $showSavings = $hasReward || $showPromo || isset($promo);
            @endphp
            @if ($showSavings)
                <div class="rounded-2xl bg-brand-muted/30 ring-1 ring-brand/15 p-4 sm:p-5 space-y-4">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('borrower.payments_page.show.save_on_payment') }}</p>

                    @if ($hasReward)
                        <div x-show="!promoValid || stackWithPromo || applyReward" class="rounded-xl bg-white ring-1 ring-brand/10 px-4 py-3 space-y-2">
                            <template x-if="!applyReward">
                                <div class="space-y-2">
                                    <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('borrower.payments_page.show.use_reward') }}</p>
                                    <p class="font-semibold text-gray-900">{{ $walletReward['label'] }}</p>
                                    @if ((int) ($walletReward['points'] ?? 0) > 0)
                                        <p class="text-xs text-gray-600">{{ __('borrower.payments_page.show.costs_points', ['points' => (int) $walletReward['points']]) }}</p>
                                        <p class="text-xs text-gray-600">{{ __('borrower.payments_page.show.points_available', [
                                            'points' => (int) ($walletReward['points_balance'] ?? 0),
                                        ]) }}</p>
                                    @endif
                                    <button type="button" @click="toggleReward()"
                                            class="inline-flex rounded-lg bg-brand-gold text-brand text-xs font-bold px-3 py-1.5">
                                        {{ __('borrower.payments_page.show.use_reward') }}
                                    </button>
                                </div>
                            </template>
                            <template x-if="applyReward">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-[10px] uppercase tracking-widest text-emerald-700 font-bold">{{ __('borrower.payments_page.show.reward_applied_inline') }}</p>
                                            <p class="font-semibold text-gray-900 truncate">{{ $walletReward['label'] }}</p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button type="button" @click="toggleReward()" class="text-xs font-bold text-brand hover:underline">{{ __('borrower.apply.change') }}</button>
                                            <button type="button" @click="toggleReward()" class="text-xs font-bold text-red-700 hover:underline">{{ __('borrower.payments_page.show.remove_reward') }}</button>
                                        </div>
                                    </div>
                                    @if ((int) ($walletReward['points'] ?? 0) > 0)
                                        <p class="text-xs text-gray-600">{{ __('borrower.payments_page.show.points_preview', [
                                            'current' => (int) ($walletReward['points_balance'] ?? 0),
                                            'after' => (int) ($walletReward['points_after'] ?? 0),
                                        ]) }}</p>
                                    @endif
                                </div>
                            </template>
                        </div>
                    @endif

                    @if ($hasReward && ($showPromo || isset($promo)))
                        <p x-show="!applyReward && !promoValid" class="text-center text-[10px] uppercase tracking-widest font-bold text-gray-400">{{ __('borrower.payments_page.show.or_divider') }}</p>
                    @endif

                    @if (isset($promo))
                        <div x-show="stackWithPromo || !applyReward">{{ $promo }}</div>
                    @elseif ($showPromo)
                        <div x-show="stackWithPromo || !applyReward" class="rounded-xl bg-white ring-1 ring-brand/10 px-4 py-3 space-y-2">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('borrower.membership.promo_section_title') }}</p>

                            <div x-cloak x-show="promoValid && promoCode && promoStatus === 'success'" class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-3 py-3 space-y-2">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-emerald-900" x-text="promoTitle || @js(__('borrower.payments_page.show.promo_applied_title'))"></p>
                                        <p class="mt-0.5 text-xs text-emerald-800" x-text="promoBody || (@js(__('borrower.membership.promo_applied', ['code' => ':code'])).replace(':code', promoCode))"></p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" @click="changePromo()" class="text-xs font-bold text-brand hover:underline">{{ __('borrower.apply.change') }}</button>
                                        <button type="button" @click="clearPromo()" class="text-xs font-bold text-red-700 hover:underline">{{ __('borrower.payments_page.show.remove_promo') }}</button>
                                    </div>
                                </div>
                            </div>

                            <div x-cloak x-show="promoStatus === 'invalid' || promoStatus === 'expired'" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-3">
                                <p class="text-sm font-bold text-amber-950" x-text="promoTitle"></p>
                                <p class="mt-0.5 text-xs text-amber-900" x-text="promoBody"></p>
                            </div>

                            <div x-show="!promoValid || promoStatus === 'invalid' || promoStatus === 'expired' || !promoCode" class="space-y-2"
                                 x-data="{ promoOpen: false }">
                                <button type="button"
                                        @click="promoOpen = !promoOpen"
                                        class="text-sm font-semibold text-brand hover:underline inline-flex items-center gap-1.5">
                                    <span x-text="promoOpen ? @js(__('borrower.membership.hide_promo')) : @js(__('borrower.membership.apply_promo_link'))"></span>
                                    <svg class="w-3.5 h-3.5 transition" :class="promoOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                </button>
                                <div x-show="promoOpen || promoStatus === 'invalid' || promoStatus === 'expired'" x-cloak class="flex gap-2">
                                    <input type="text"
                                           x-model="promoCode"
                                           @keydown.enter.prevent="applyPromo()"
                                           maxlength="40"
                                           class="flex-1 rounded-lg border-gray-300 text-sm font-mono uppercase"
                                           placeholder="{{ __('borrower.membership.promo_code_placeholder') }}">
                                    <button type="button"
                                            @click="applyPromo()"
                                            class="shrink-0 inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-lg text-sm">
                                        {{ __('borrower.membership.apply_promo') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (($errors ?? null)?->any())
                        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-2 text-sm text-rose-900">
                            {{ $errors->first() }}
                        </div>
                    @endif
                </div>
            @elseif (($errors ?? null)?->any())
                <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-2 text-sm text-rose-900">
                    {{ $errors->first() }}
                </div>
            @endif

            <button type="button" @click="checkoutStep = 'pay'"
                    class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3.5 rounded-xl text-sm shadow-sm">
                {{ __('borrower.apply.next') }}
            </button>
            @if (filled($cancelUrl))
                <a href="{{ $cancelUrl }}" class="block text-center text-sm font-semibold text-gray-500 hover:text-gray-800">
                    {{ __('borrower.payments_page.show.back_to_quote') }}
                </a>
            @endif
        </div>

        <div x-show="checkoutStep === 'pay'" x-cloak class="space-y-5">
            <button type="button" @click="checkoutStep = 'adjust'" class="text-sm font-semibold text-gray-600 hover:text-gray-900">
                ← {{ __('borrower.apply.back') }}
            </button>

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
                <input type="hidden" name="promo_code" :value="promoCode || ''">

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
</div>
