@props([
    'outbound' => false,
    'time' => null,
    'text' => null,
])

@php
    // Shared Member ↔ Support bubble. Width is content-driven up to ~70% of the chat column.
    $bubbleClass = $outbound
        ? 'kf-support-bubble kf-support-bubble--outbound'
        : 'kf-support-bubble kf-support-bubble--inbound';
@endphp

<div {{ $attributes->class(['flex', $outbound ? 'justify-end' : 'justify-start']) }}>
    <div class="{{ $bubbleClass }}">
        <div class="kf-support-bubble__body whitespace-pre-wrap">{{ $text !== null ? $text : $slot }}</div>
        @if ($time)
            <p class="kf-support-bubble__time">{{ $time }}</p>
        @endif
    </div>
</div>
