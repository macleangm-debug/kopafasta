@props([
    'label' => 'Fee',
    'currency' => 'TZS',
    'quote' => null,
    'variant' => 'dark',
])

@if ($quote && ($quote['base'] ?? 0) > 0)
    @php
        $isDark = $variant === 'dark';
        $wrap = $isDark ? 'rounded-xl bg-white/10 ring-1 ring-white/20 text-sm space-y-1.5' : 'rounded-xl bg-gray-50 ring-1 ring-gray-200 text-sm space-y-1.5';
        $muted = $isDark ? 'text-white/80' : 'text-gray-600';
        $labelMuted = $isDark ? 'text-white/70' : 'text-gray-500';
        $discount = $isDark ? 'text-emerald-200' : 'text-emerald-700';
        $totalBorder = $isDark ? 'border-white/10' : 'border-gray-200';
        $totalText = $isDark ? '' : 'text-gray-900';
        $walletPoints = wallet_balance_as_points((float) ($quote['wallet_applied'] ?? 0));
    @endphp
    <div {{ $attributes->merge(['class' => $wrap.' px-4 py-3']) }}>
        <p class="text-[10px] uppercase tracking-widest {{ $labelMuted }}">{{ $label }}</p>
        <div class="flex justify-between gap-4">
            <span class="{{ $muted }}">{{ $label }}</span>
            <span class="font-mono {{ $totalText }}">{{ $currency }} {{ format_number($quote['base']) }}</span>
        </div>
        @if (($quote['promo_discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">{{ __('borrower.apply.application_fee.promo_discount') }}</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['promo_discount']) }}</span>
            </div>
        @endif
        @if (($quote['referral_discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">{{ __('borrower.apply.application_fee.referral_discount') }}</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['referral_discount']) }}</span>
            </div>
        @endif
        @if (($quote['affiliate_discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">{{ __('borrower.apply.application_fee.affiliate_discount') }}</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['affiliate_discount']) }}</span>
            </div>
        @endif
        @if (($quote['loyalty_discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">{{ __('borrower.apply.application_fee.loyalty_discount') }}</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['loyalty_discount']) }}</span>
            </div>
        @endif
        @if (($quote['streak_discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">{{ __('borrower.apply.application_fee.streak_discount') }}</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['streak_discount']) }}</span>
            </div>
        @endif
        @if ($walletPoints > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">{{ __('borrower.apply.application_fee.referral_points') }}</span>
                <span class="font-mono {{ $discount }}">− {{ format_reward_points($walletPoints) }}</span>
            </div>
        @endif
        <div class="flex justify-between gap-4 font-semibold pt-1 border-t {{ $totalBorder }} {{ $totalText }}">
            <span>{{ __('borrower.apply.application_fee.amount_due') }}</span>
            <span class="font-mono">{{ $currency }} {{ format_number($quote['cash_due'] ?? $quote['after_discount']) }}</span>
        </div>
    </div>
@endif
