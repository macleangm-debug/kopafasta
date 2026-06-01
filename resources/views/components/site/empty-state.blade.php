@props([
    'icon' => '📄',
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionUrl' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-gray-200 p-10 sm:p-12 text-center']) }}>
    <div class="text-4xl mb-4" aria-hidden="true">{{ $icon }}</div>
    <h2 class="text-lg font-semibold text-gray-900">{{ $title }}</h2>
    @if ($description)
        <p class="text-sm text-gray-500 mt-2 max-w-md mx-auto">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}"
           class="mt-6 inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
            {{ $actionLabel }}
        </a>
    @endif
    @if (isset($slot) && ! $slot->isEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
