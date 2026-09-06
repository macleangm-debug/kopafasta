@props([
    'title',
    'body' => null,
    'primaryHref' => null,
    'primaryLabel' => null,
    'secondaryHref' => null,
    'secondaryLabel' => null,
])

<div {{ $attributes->class(['rounded-[1.75rem] bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white p-6 sm:p-8 ring-1 ring-brand-gold/20 shadow-[0_20px_50px_rgba(8,47,39,0.2)]']) }}>
    <p class="text-[11px] uppercase tracking-[0.18em] text-brand-gold font-bold">››› {{ brand_name() }}</p>
    <h2 class="mt-2 text-2xl sm:text-3xl font-black tracking-tight">{{ $title }}</h2>
    @if ($body)
        <p class="mt-2 text-sm text-white/80 max-w-2xl">{{ $body }}</p>
    @endif
    <div class="mt-5 flex flex-wrap gap-3">
        @if ($primaryHref && $primaryLabel)
            <a href="{{ $primaryHref }}" class="inline-flex rounded-xl bg-brand-gold text-brand font-extrabold px-5 py-3">{{ $primaryLabel }}</a>
        @endif
        @if ($secondaryHref && $secondaryLabel)
            <a href="{{ $secondaryHref }}" class="inline-flex rounded-xl bg-white/10 ring-1 ring-white/25 font-semibold px-5 py-3">{{ $secondaryLabel }}</a>
        @endif
    </div>
</div>
