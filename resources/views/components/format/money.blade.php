@props([
    'amount' => null,
    'decimals' => 0,
    'withCode' => true,
])

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ format_money($amount, (bool) $withCode, (int) $decimals) }}</span>
