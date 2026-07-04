@props(['delay' => 120])

<div x-data="{ ready: false }" x-init="setTimeout(() => ready = true, {{ (int) $delay }})">
    <div x-show="!ready" x-transition.opacity.duration.150ms>
        {{ $skeleton ?? '' }}
    </div>
    <div x-show="ready" x-cloak x-transition.opacity.duration.200ms>
        {{ $slot }}
    </div>
</div>
