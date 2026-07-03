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
@endphp

<section class="mb-6 rounded-2xl ring-1 p-5 sm:p-6 {{ $shell }}">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-widest opacity-80 font-semibold">{{ $hero['title'] ?? '' }}</p>
            @if (! empty($hero['amount']))
                <p class="text-3xl font-bold mt-1">{{ $hero['amount'] }}</p>
            @endif
            @if (! empty($hero['subtitle']))
                <p class="text-sm mt-2 opacity-90">{{ $hero['subtitle'] }}</p>
            @endif
            @if (! empty($hero['meta']))
                <p class="text-xs mt-2 opacity-80">{{ $hero['meta'] }}</p>
            @endif
        </div>
        @if (! empty($hero['cta_url']) && ! empty($hero['cta_label']))
            <a href="{{ $hero['cta_url'] }}"
               @class([
                   'inline-flex justify-center font-semibold px-5 py-2.5 rounded-full text-sm shrink-0',
                   $variant === 'under_review' ? 'bg-gray-900 text-white hover:bg-gray-800' : 'bg-white/15 hover:bg-white/25 ring-1 ring-white/20',
               ])>
                {{ $hero['cta_label'] }}
            </a>
        @endif
    </div>
</section>
