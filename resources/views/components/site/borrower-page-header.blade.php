@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-2xl sm:text-3xl font-bold text-brand tracking-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-gray-500 mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if (isset($actions) && ! $actions->isEmpty())
        <div class="flex flex-wrap items-center gap-2 shrink-0 self-start">{{ $actions }}</div>
    @endif
</div>
