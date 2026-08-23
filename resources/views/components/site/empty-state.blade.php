@props([
    'icon' => '📄',
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionUrl' => null,
    'compact' => false,
])

<div {{ $attributes->merge(['class' => $compact ? 'py-8 text-center' : 'glass-card p-10 sm:p-14 text-center']) }}>
    <div @class([
        'mx-auto grid place-items-center rounded-2xl bg-brand-muted/60 ring-1 ring-brand/10',
        'size-16 text-3xl mb-5' => ! $compact,
        'size-12 text-2xl mb-3' => $compact,
    ]) aria-hidden="true">{{ $icon }}</div>
    <h2 @class([
        'font-bold text-gray-900 tracking-tight',
        'text-lg sm:text-xl' => ! $compact,
        'text-base' => $compact,
    ])>{{ $title }}</h2>
    @if ($description)
        <p class="text-sm text-gray-500 mt-2 max-w-md mx-auto leading-relaxed">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}"
           class="mt-6 inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-xl text-sm transition shadow-sm">
            {{ $actionLabel }}
        </a>
    @endif
    @if (isset($slot) && ! $slot->isEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
