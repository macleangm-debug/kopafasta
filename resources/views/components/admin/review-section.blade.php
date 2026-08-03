@props([
    'id' => null,
    'title',
    'subtitle' => null,
    'collapsible' => false,
    'open' => true,
])

@if ($collapsible)
    <details @if ($id) id="{{ $id }}" @endif class="scroll-mt-24 bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden group" @if ($open) open @endif>
        <summary class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex items-start justify-between gap-3 flex-wrap cursor-pointer list-none [&::-webkit-details-marker]:hidden">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-gray-900">{{ $title }}</h2>
                    <svg class="w-4 h-4 text-brand/50 transition group-open:rotate-180 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
                </div>
                @if ($subtitle)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2" onclick="event.preventDefault()">{{ $actions }}</div>
            @endisset
        </summary>
        <div class="p-5 sm:p-6">
            {{ $slot }}
        </div>
    </details>
@else
    <section @if ($id) id="{{ $id }}" @endif class="scroll-mt-24 bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
        <div class="p-5 sm:p-6">
            {{ $slot }}
        </div>
    </section>
@endif
