@props([
    'icon' => '📄',
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionUrl' => null,
])

<div {{ $attributes->merge(['class' => 'glass-card p-10 sm:p-14 text-center']) }}>
    <div class="mx-auto size-16 rounded-2xl bg-brand-muted/60 grid place-items-center text-3xl mb-5 ring-1 ring-brand/10" aria-hidden="true">{{ $icon }}</div>
    <h2 class="text-lg sm:text-xl font-bold text-gray-900 tracking-tight">{{ $title }}</h2>
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
