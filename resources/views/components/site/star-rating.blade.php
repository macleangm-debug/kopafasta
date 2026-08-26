@props([
    'filled' => 0,
    'max' => 5,
    'label' => null,
])

@php
    $filled = max(0, min((int) $max, (int) $filled));
    $max = max(1, (int) $max);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center tracking-wide text-amber-500']) }}
      role="img"
      aria-label="{{ $label ?: ($filled.' / '.$max) }}">
    @for ($i = 1; $i <= $max; $i++)
        <span aria-hidden="true">{{ $i <= $filled ? '★' : '☆' }}</span>
    @endfor
</span>
