@props([
    'mode' => 'link',
    'withdrawHref' => null,
    'hasPayoutAccount' => true,
    'available' => 0,
    'minPayout' => 0,
])

@php
    $withdrawHref = $withdrawHref ?: route('site.affiliate.performance', ['tab' => 'withdrawals']);
@endphp

<div class="w-full flex flex-col items-stretch gap-2">
    @if ($mode === 'button')
        <button type="button"
                @click="if (needsAccount || belowMinimum) { withdrawHint = !withdrawHint; withdrawing = false; } else { withdrawing = true; withdrawHint = false; step = 'form'; }"
                class="inline-flex justify-center bg-white text-brand font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-gold transition">
            {{ __('site.affiliate_portal.withdraw') }}
        </button>
        <div x-show="withdrawHint" x-cloak class="rounded-xl bg-white/10 ring-1 ring-white/20 px-4 py-3">
            <p x-show="needsAccount" class="text-sm text-white/90 leading-snug">
                {{ __('site.affiliate_portal.withdraw_need_account_body') }}
            </p>
            <p x-show="!needsAccount" class="text-sm text-white/90 leading-snug">
                {{ __('site.affiliate_portal.payout_not_ready', [
                    'amount' => format_money($minPayout),
                    'available' => format_money($available),
                ]) }}
            </p>
            <button type="button" @click="withdrawHint = false"
                    class="mt-2 text-sm font-semibold text-brand-gold">
                {{ __('site.affiliate_portal.cancel_withdraw') }}
            </button>
        </div>
    @else
        <a href="{{ $withdrawHref }}"
           class="inline-flex justify-center bg-white text-brand font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-gold transition">
            {{ __('site.affiliate_portal.withdraw') }}
        </a>
    @endif
</div>
