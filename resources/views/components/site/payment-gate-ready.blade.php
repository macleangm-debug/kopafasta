@props([
    'payment',
    'canSwitchToBank' => false,
    'showPromo' => false,
    'editPhone' => false,
    'quote' => null,
    'promoValue' => null,
])

@php
    $badge = 'bg-brand-gold/25 text-brand-gold ring-brand-gold/40';
    $openPhone = $editPhone
        || $errors->has('mobile_number')
        || $errors->has('payment_phone');
@endphp

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20">
    <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
    <div class="relative px-5 sm:px-7 py-7">
        <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ __('borrower.payment_waiting.gate_eyebrow') }}</p>
        <p class="mt-2 text-xl sm:text-2xl font-extrabold tracking-tight">{{ $payment->typeLabel() }}</p>
        <p class="mt-4 text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.payments_page.show.amount') }}</p>
        <p class="mt-1 text-3xl font-extrabold tabular-nums tracking-tight text-amber-300">
            {{ format_money((float) $payment->amount) }}
        </p>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badge }}">{{ $payment->statusLabel() }}</span>
            <span class="font-mono text-xs text-white/70">{{ $payment->reference }}</span>
        </div>
    </div>
</section>

@if ($showPromo)
    <x-site.promo-code-toggle
        name="promo_code"
        :value="$promoValue ?? old('promo_code')"
        :action="url()->current()"
        :quote="$quote"
    />
@endif

<div class="rounded-2xl ring-1 ring-brand/10 bg-white px-5 py-5 space-y-4"
     x-data="{ changePhone: @js($openPhone) }">
    <div>
        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.show.mobile_number') }}</p>
        <p class="mt-1.5 font-mono text-sm font-semibold text-gray-900">{{ $payment->mobile_number ?: '—' }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ __('borrower.payment_waiting.gate_phone_help') }}</p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-2 text-sm text-rose-900">
            {{ $errors->first() }}
        </div>
    @endif

    <div x-show="changePhone" x-cloak class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/20 p-4 space-y-3">
        <form method="POST" action="{{ route('site.borrower.payments.phone', $payment) }}" class="space-y-3">
            @csrf
            <x-site.phone-input
                name="mobile_number"
                :label="__('borrower.payment_waiting.new_phone_label')"
                :value="old('mobile_number', $payment->mobile_number)"
                :required="true"
                :locked-country="$payment->customer?->country_code"
            />
            <button type="submit" class="w-full sm:w-auto inline-flex justify-center font-extrabold px-5 py-2.5 rounded-xl text-sm bg-white ring-1 ring-brand/20 text-brand hover:bg-brand-muted/40">
                {{ __('borrower.payment_waiting.save_phone') }}
            </button>
        </form>
    </div>

    <form method="POST" action="{{ route('site.borrower.payments.pay', $payment) }}" class="space-y-3">
        @csrf
        <button type="submit" class="w-full inline-flex justify-center font-extrabold px-6 py-3.5 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
            {{ __('borrower.payment_waiting.pay_now') }}
        </button>
    </form>

    <div class="flex flex-wrap gap-3 pt-1">
        <button type="button" @click="changePhone = !changePhone"
                class="text-sm font-bold text-brand hover:underline">
            {{ __('borrower.payment_waiting.change_phone') }}
        </button>
        @if ($canSwitchToBank)
            <form method="POST" action="{{ route('site.borrower.payments.switch-bank', $payment) }}">
                @csrf
                <button type="submit" class="text-sm font-bold text-gray-600 hover:underline">
                    {{ __('borrower.payment_waiting.pay_by_bank') }}
                </button>
            </form>
        @endif
    </div>
</div>
