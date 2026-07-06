@props([
    'label' => null,
    'value' => 0,
    'hint' => null,
    'variant' => 'brand',
])

@php
    $label = $label ?? __('borrower.rewards.balance');
    $wrap = match ($variant) {
        'gold' => 'bg-gradient-to-br from-brand-gold/15 to-white',
        default => 'bg-gradient-to-br from-brand-muted/80 to-white',
    };
    $valueClass = $variant === 'gold' ? 'text-gray-900' : 'text-brand';
@endphp

<div {{ $attributes->merge(['class' => "glass-card p-5 {$wrap}"]) }}>
    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
    <p class="mt-2 text-3xl font-black tabular-nums {{ $valueClass }}">{{ is_numeric($value) ? number_format((int) $value) : $value }}</p>
    @if ($hint)
        <p class="text-xs text-gray-600 mt-1">{{ $hint }}</p>
    @endif
</div>
