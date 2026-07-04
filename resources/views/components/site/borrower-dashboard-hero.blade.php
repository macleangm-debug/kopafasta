@props(['hero'])

@php
    $variant = $hero['variant'] ?? 'no_loan';
    $shell = match ($variant) {
        'arrears' => 'bg-red-600 text-white ring-red-700',
        'active_loan' => 'bg-gray-900 text-white ring-gray-800',
        'under_review' => 'bg-amber-500 text-gray-900 ring-amber-600',
        'settled' => 'bg-emerald-600 text-white ring-emerald-700',
        default => 'bg-gradient-to-br from-brand to-brand-light text-white ring-brand/30',
    };
    $decor = match ($variant) {
        'arrears', 'active_loan' => 'individual',
        'under_review' => 'business',
        'settled' => 'wallet',
        default => 'individual',
    };
@endphp

<section class="mb-6 rounded-2xl ring-1 p-5 sm:p-6 relative overflow-hidden {{ $shell }}">
    <div class="absolute inset-0 opacity-[0.07] pointer-events-none" aria-hidden="true">
        <svg class="absolute -right-6 -bottom-8 w-48 h-32" viewBox="0 0 200 120" fill="none">
            <circle cx="160" cy="30" r="40" stroke="currentColor" stroke-width="2"/>
            <path d="M20 80 Q100 20 180 90" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </div>
    <div class="absolute right-4 top-1/2 -translate-y-1/2 hidden sm:block opacity-20 pointer-events-none" aria-hidden="true">
        @include('components.site.illustrations.product', ['type' => $decor])
    </div>
    <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pr-0 sm:pr-28">
        <div>
            <p class="text-xs uppercase tracking-widest opacity-80 font-semibold">{{ $hero['title'] ?? '' }}</p>
            @if (! empty($hero['amount']))
                <p class="text-3xl font-bold mt-1 tabular-nums">{{ $hero['amount'] }}</p>
            @endif
            @if (! empty($hero['subtitle']))
                <p class="text-sm mt-2 opacity-90">{{ $hero['subtitle'] }}</p>
            @endif
            @if (! empty($hero['meta']))
                <p class="text-xs mt-2 opacity-80 font-mono">{{ $hero['meta'] }}</p>
            @endif
        </div>
        @if (! empty($hero['cta_url']) && ! empty($hero['cta_label']))
            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <a href="{{ $hero['cta_url'] }}"
                   @class([
                       'inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition',
                       $variant === 'under_review'
                           ? 'bg-gray-900 text-white hover:bg-gray-800'
                           : 'bg-white text-brand hover:bg-white/90 shadow-sm',
                   ])>
                    {{ $hero['cta_label'] }}
                </a>
                @if (! empty($hero['secondary_cta_url']) && ! empty($hero['secondary_cta_label']))
                    <a href="{{ $hero['secondary_cta_url'] }}"
                       class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white/15 text-white ring-1 ring-white/30 hover:bg-white/25">
                        {{ $hero['secondary_cta_label'] }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</section>
