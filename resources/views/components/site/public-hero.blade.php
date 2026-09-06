@props([
    'variant' => 'feature', // feature | compact | minimal
    'eyebrow' => null,
    'title',
    'body' => null,
    'primaryHref' => null,
    'primaryLabel' => null,
    'secondaryHref' => null,
    'secondaryLabel' => null,
    'facts' => [], // list of ['label' => ..., 'value' => ...] for compact
])

@php
    $variant = in_array($variant, ['feature', 'compact', 'minimal'], true) ? $variant : 'feature';
    $isFeature = $variant === 'feature';
    $isCompact = $variant === 'compact';
    $isMinimal = $variant === 'minimal';
    $pad = $isFeature
        ? 'px-6 sm:px-10 lg:px-14 py-10 sm:py-14 lg:py-16'
        : ($isCompact ? 'px-5 sm:px-8 py-7 sm:py-9' : 'px-5 sm:px-8 py-6 sm:py-7');
    $titleClass = $isFeature
        ? 'mt-4 text-3xl sm:text-5xl lg:text-[3.35rem] font-black tracking-tight leading-[1.05]'
        : ($isCompact ? 'mt-3 text-2xl sm:text-4xl font-black tracking-tight leading-tight' : 'mt-2 text-xl sm:text-2xl font-bold tracking-tight');
@endphp

<section @class(['relative overflow-hidden premium-gradient', 'py-6 sm:py-10' => $isFeature, 'py-5 sm:py-7' => ! $isFeature])>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-[1.75rem] sm:rounded-[2rem] bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white shadow-[0_28px_70px_rgba(8,47,39,0.28)] ring-1 ring-brand-gold/20">
            <div class="absolute inset-0 opacity-[0.18] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
            @unless ($isMinimal)
                <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-brand-gold/15 pointer-events-none"></div>
                <div class="absolute -left-20 bottom-0 h-52 w-52 rounded-full bg-white/5 pointer-events-none"></div>
            @endunless

            <div @class([
                'relative',
                $pad,
                'grid lg:grid-cols-2 gap-8 lg:gap-12 items-center' => $isFeature && $slot->isNotEmpty(),
            ])>
                <div class="text-left">
                    @if (filled($eyebrow))
                        <p class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.22em] text-brand-gold">
                            <span class="text-lg tracking-[-0.18em] leading-none" aria-hidden="true">›››</span>
                            {{ $eyebrow }}
                        </p>
                    @endif
                    <h1 class="{{ $titleClass }}">{{ $title }}</h1>
                    @if (filled($body))
                        <p @class([
                            'text-white/80 max-w-xl leading-relaxed',
                            'mt-4 text-base sm:text-lg' => $isFeature,
                            'mt-3 text-sm sm:text-base' => ! $isFeature,
                        ])>{{ $body }}</p>
                    @endif

                    @if ($isCompact && $facts !== [])
                        <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-3">
                            @foreach ($facts as $fact)
                                <div class="rounded-2xl bg-white/10 ring-1 ring-white/15 px-3.5 py-3">
                                    <p class="text-[10px] uppercase tracking-wider text-white/60 font-semibold">{{ $fact['label'] ?? '' }}</p>
                                    <p class="mt-1 text-sm sm:text-base font-bold tabular-nums">{{ $fact['value'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($primaryHref || $secondaryHref)
                        <div class="mt-6 sm:mt-7 flex flex-wrap gap-3">
                            @if ($primaryHref && $primaryLabel)
                                <a href="{{ $primaryHref }}" class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-extrabold px-6 py-3.5 rounded-xl shadow-md transition">
                                    {{ $primaryLabel }}
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                                </a>
                            @endif
                            @if ($secondaryHref && $secondaryLabel)
                                <a href="{{ $secondaryHref }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/15 ring-1 ring-white/25 text-white font-semibold px-6 py-3.5 rounded-xl transition">
                                    {{ $secondaryLabel }}
                                </a>
                            @endif
                        </div>
                    @endif

                    {{ $below ?? '' }}
                </div>

                @if ($slot->isNotEmpty())
                    <div @class(['hidden lg:block' => $isFeature])>
                        {{ $slot }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
