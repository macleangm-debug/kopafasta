@props([
    'available' => 0,
    'inProgress' => 0,
    'withdrawMode' => 'link',
    'withdrawHref' => null,
    'hasPayoutAccount' => true,
])

@php
    $withdrawHref = $withdrawHref ?: route('site.affiliate.performance', ['tab' => 'withdrawals']);
@endphp

<div class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-5 py-4 min-w-[14rem] shrink-0">
    <div class="flex items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.hero_available') }}</p>
            <p class="text-2xl font-extrabold tabular-nums mt-1" title="{{ format_money($available) }}">{{ format_money($available) }}</p>
            @if ($inProgress > 0)
                <p class="text-xs text-white/70 mt-1">{{ __('site.affiliate_portal.hero_in_progress', ['amount' => format_money($inProgress)]) }}</p>
            @endif
            @if ($withdrawMode === 'button')
                <p x-show="withdrawHint" x-cloak class="text-xs text-brand-gold mt-2 leading-snug">
                    @if (! $hasPayoutAccount)
                        {{ __('site.affiliate_portal.withdraw_need_account_body') }}
                    @else
                        {{ __('site.affiliate_portal.remaining_to_withdraw', ['amount' => format_money($remainingToWithdraw ?? 0)]) }}
                    @endif
                </p>
            @endif
        </div>
        @if ($withdrawMode === 'button')
            <button type="button"
                    @click="if (needsAccount || belowMinimum) { withdrawHint = !withdrawHint; withdrawing = false; } else { withdrawing = true; withdrawHint = false; step = 'form'; }"
                    class="shrink-0 inline-flex justify-center bg-white text-brand font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-gold transition">
                {{ __('site.affiliate_portal.withdraw') }}
            </button>
        @else
            <a href="{{ $withdrawHref }}"
               class="shrink-0 inline-flex justify-center bg-white text-brand font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-gold transition">
                {{ __('site.affiliate_portal.withdraw') }}
            </a>
        @endif
    </div>
</div>
