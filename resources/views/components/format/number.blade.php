@props([
    'value' => null,
    'decimals' => 0,
])

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ format_number($value, (int) $decimals) }}</span>
