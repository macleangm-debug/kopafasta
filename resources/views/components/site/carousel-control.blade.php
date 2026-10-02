@props([
    'direction' => 'next', // prev | next
    'label' => null,
])

@php
    $isPrev = $direction === 'prev';
    $label = $label ?? ($isPrev ? 'Previous' : 'Next');
@endphp

<button
    type="button"
    {{ $attributes->class([
        'kf-carousel-ctrl',
        'kf-carousel-ctrl--prev' => $isPrev,
        'kf-carousel-ctrl--next' => ! $isPrev,
        'hidden sm:grid',
    ]) }}
    aria-label="{{ $label }}"
>
    @if ($isPrev)
        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 4 6 10l6 6"/></svg>
    @else
        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M8 4l6 6-6 6"/></svg>
    @endif
</button>
