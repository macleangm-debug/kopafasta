{{-- Collapsible promo / affiliate code field for payment screens --}}
@props([
    'name' => 'promo_code',
    'value' => '',
    'method' => 'GET',
    'action' => null,
    'alpineModel' => null,
    'alpineApply' => null,
    'quote' => null,
    'hiddenWhen' => null,
    'inline' => false,
])

@php
    $hasCode = filled($value) || filled(data_get($quote, 'promo_code'));
    $autoApplied = ($quote['affiliate_auto_applied'] ?? false) && ($quote['has_affiliate'] ?? false) && ($quote['promo_valid'] ?? false);
@endphp

<div
    @if ($hiddenWhen) x-show="{{ $hiddenWhen }}" @endif
    x-data="{ open: @js($hasCode && ! $autoApplied) }"
    class="mb-6"
>
    @if ($autoApplied)
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ __('site.affiliate_portal.promo_auto_applied', ['code' => $quote['promo_code'] ?? '']) }}
            @if (filled($quote['referred_by'] ?? null))
                <span class="block text-xs mt-1">{{ __('site.affiliate_portal.referred_by', ['name' => $quote['referred_by']]) }}</span>
            @endif
        </div>
    @else
    <button type="button"
            @click="open = !open"
            class="text-sm font-semibold text-brand hover:underline inline-flex items-center gap-1.5">
        <span x-text="open ? @js(__('borrower.membership.hide_promo')) : @js(__('borrower.membership.apply_promo_link'))"></span>
        <svg class="w-3.5 h-3.5 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
    </button>

    <div x-show="open" x-cloak x-collapse class="mt-3 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-4 text-sm">
        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.apply.application_fee.promo_label') }}</label>
        @if ($alpineModel)
            <div class="flex gap-2">
                <input type="text"
                       x-model="{{ $alpineModel }}"
                       @if ($alpineApply) @keydown.enter.prevent="{{ $alpineApply }}" @endif
                       maxlength="40"
                       class="flex-1 rounded-lg border-gray-300 text-sm font-mono uppercase"
                       placeholder="{{ __('borrower.membership.promo_code_placeholder') }}">
                @if ($alpineApply)
                    <button type="button"
                            @click="{{ $alpineApply }}"
                            class="shrink-0 inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-lg text-sm">
                        {{ __('borrower.membership.apply_promo') }}
                    </button>
                @endif
            </div>
            {{ $slot }}
        @elseif ($inline)
            <input type="text" name="{{ $name }}" value="{{ $value }}" maxlength="40"
                   class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase"
                   placeholder="{{ __('borrower.membership.promo_code_placeholder') }}">
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
        @else
            <form method="{{ $method }}" @if ($action) action="{{ $action }}" @endif class="flex gap-2">
                <input type="text" name="{{ $name }}" value="{{ $value }}" maxlength="40"
                       class="flex-1 rounded-lg border-gray-300 text-sm font-mono uppercase"
                       placeholder="{{ __('borrower.membership.promo_code_placeholder') }}">
                <button type="submit"
                        class="shrink-0 inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-lg text-sm">
                    {{ __('borrower.membership.apply_promo') }}
                </button>
            </form>
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
        @endif
    </div>
@endif
</div>
