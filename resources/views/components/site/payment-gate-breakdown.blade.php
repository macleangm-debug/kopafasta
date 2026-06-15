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
    @endphp
    <div {{ $attributes->merge(['class' => $wrap.' px-4 py-3']) }}>
        <p class="text-[10px] uppercase tracking-widest {{ $labelMuted }}">{{ $label }}</p>
        <div class="flex justify-between gap-4">
            <span class="{{ $muted }}">{{ $label }}</span>
            <span class="font-mono {{ $totalText }}">{{ $currency }} {{ format_number($quote['base']) }}</span>
        </div>
        @if (($quote['promo_discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">Promo discount</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['promo_discount']) }}</span>
            </div>
        @endif
        @if (($quote['referral_discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">Referral discount</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['referral_discount']) }}</span>
            </div>
        @endif
        @if (($quote['affiliate_discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">Affiliate discount</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['affiliate_discount']) }}</span>
            </div>
        @endif
        @if (($quote['wallet_applied'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="{{ $muted }}">Referral wallet</span>
                <span class="font-mono {{ $discount }}">− {{ $currency }} {{ format_number($quote['wallet_applied']) }}</span>
            </div>
        @endif
        <div class="flex justify-between gap-4 font-semibold pt-1 border-t {{ $totalBorder }} {{ $totalText }}">
            <span>Amount due</span>
            <span class="font-mono">{{ $currency }} {{ format_number($quote['cash_due'] ?? $quote['after_discount']) }}</span>
        </div>
    </div>
@endif
