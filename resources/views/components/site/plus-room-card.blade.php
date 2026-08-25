@props([
    'href',
    'icon',
    'title',
    'cta',
    'stat' => null,
    'hint' => null,
])

<article class="snap-start shrink-0 w-[min(82vw,280px)] self-stretch glass-card overflow-hidden flex flex-col hover:shadow-[0_16px_48px_rgba(0,77,64,0.12)] transition-shadow">
    <div class="px-4 pt-4 flex flex-col flex-1 min-h-0">
        <div class="size-12 rounded-2xl bg-brand/10 ring-1 ring-brand/10 text-2xl grid place-items-center">{{ $icon }}</div>
        <h3 class="mt-3 text-base font-extrabold text-brand leading-snug tracking-tight">{{ $title }}</h3>
        @if ($stat)
            <p class="mt-1.5 text-lg font-bold tabular-nums text-gray-900">{{ $stat }}</p>
        @endif
        @if ($hint)
            <p class="mt-0.5 text-xs text-gray-600 leading-snug">{{ $hint }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-2 text-sm text-gray-700 flex-1">{{ $slot }}</div>
        @endif
    </div>
    <div class="mt-auto p-4 pt-3">
        <a href="{{ $href }}" class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-bold px-4 py-2.5 rounded-xl text-sm shadow-sm">
            {{ $cta }} →
        </a>
    </div>
</article>
