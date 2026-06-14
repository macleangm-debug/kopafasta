@props([
    'label' => 'Fee',
    'currency' => 'TZS',
    'quote' => null,
])

@if ($quote && ($quote['has_affiliate'] ?? false) && ($quote['base'] ?? 0) > 0)
    <div class="rounded-xl bg-white/10 ring-1 ring-white/20 px-4 py-3 text-sm space-y-1 mb-4">
        <p class="text-[10px] uppercase tracking-widest text-white/70">{{ $label }}</p>
        <div class="flex justify-between gap-4">
            <span class="text-white/80">Original</span>
            <span class="font-mono">{{ $currency }} {{ format_number($quote['base']) }}</span>
        </div>
        @if (($quote['discount'] ?? 0) > 0)
            <div class="flex justify-between gap-4">
                <span class="text-white/80">Discount</span>
                <span class="font-mono text-emerald-200">− {{ $currency }} {{ format_number($quote['discount']) }}</span>
            </div>
        @endif
        <div class="flex justify-between gap-4 font-semibold pt-1 border-t border-white/10">
            <span>You pay</span>
            <span class="font-mono">{{ $currency }} {{ format_number($quote['after_discount']) }}</span>
        </div>
        @if (($quote['commission'] ?? 0) > 0)
            <div class="flex justify-between gap-4 text-xs text-white/70 pt-1">
                <span>Affiliate commission</span>
                <span class="font-mono">{{ $currency }} {{ format_number($quote['commission']) }}</span>
            </div>
        @endif
    </div>
@endif
