@props([
    'kicker' => null,
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'mb-5 -mt-2 rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm']) }}>
    <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="shrink-0 rounded-xl bg-white/10 ring-1 ring-white/20 p-2.5">
                    <x-site.brand-mark size="sm" variant="light" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">
                        {{ brand_name() }}@if ($kicker) · {{ $kicker }}@endif
                    </p>
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="text-sm text-white/75 mt-1">{{ $subtitle }}</p>
                    @endif
                    @isset($meta)
                        <div class="text-xs text-white/70 mt-1.5">{{ $meta }}</div>
                    @endisset
                </div>
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2 shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    </div>
    @isset($stats)
        <div class="bg-white px-5 sm:px-6 py-4">{{ $stats }}</div>
    @endisset
</div>
