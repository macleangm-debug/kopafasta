@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

<div class="mb-6">
    @if ($eyebrow)
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ $eyebrow }}</p>
    @endif
    <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">{{ $title }}</h2>
    @if ($subtitle)
        <p class="mt-1 text-sm text-gray-600">{{ $subtitle }}</p>
    @endif
</div>
