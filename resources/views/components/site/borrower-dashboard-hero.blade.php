@props(['hero'])

@php
    $variant = $hero['variant'] ?? 'no_loan';
    $shell = match ($variant) {
        'arrears' => 'bg-red-600 text-white ring-red-700',
        'active_loan' => 'bg-gray-900 text-white ring-gray-800',
        // Standard brand green for review / guarantor states (not amber).
        'under_review', 'guarantor_request', 'applications', 'no_loan' => 'bg-gradient-to-br from-brand to-brand-light text-white ring-brand/30',
        'settled' => 'bg-emerald-600 text-white ring-emerald-700',
        default => 'bg-gradient-to-br from-brand to-brand-light text-white ring-brand/30',
    };
    $decor = match ($variant) {
        'arrears', 'active_loan' => 'individual',
        'under_review', 'guarantor_request' => 'business',
        'settled' => 'wallet',
        default => 'individual',
    };
    $lightText = true;
    $cleanHome = in_array($variant, ['applications'], true)
        || (($variant === 'no_loan') && empty($hero['title']) && empty($hero['subtitle']));
@endphp

<section class="mb-6 rounded-2xl ring-1 p-5 sm:p-6 relative overflow-hidden {{ $shell }}">
    <div class="absolute inset-0 opacity-[0.07] pointer-events-none" aria-hidden="true">
        <svg class="absolute -right-6 -bottom-8 w-48 h-32" viewBox="0 0 200 120" fill="none">
            <circle cx="160" cy="30" r="40" stroke="currentColor" stroke-width="2"/>
            <path d="M20 80 Q100 20 180 90" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </div>
    <div class="absolute right-4 top-1/2 -translate-y-1/2 hidden lg:block opacity-20 pointer-events-none" aria-hidden="true">
        @include('components.site.illustrations.product', ['type' => $decor])
    </div>

    <div class="relative max-w-xl space-y-4">
        @if (! empty($hero['greeting']))
            <div>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <p @class([
                        'text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight min-w-0',
                        $lightText ? 'text-white' : 'text-gray-900',
                    ])>{{ $hero['greeting'] }}</p>
                    <x-site.grade-badge :grade="$hero['grade'] ?? 'bronze'" :plus="! empty($hero['plus_active'])" size="sm" class="shrink-0 mt-1" />
                </div>
                @if (! empty($hero['membership_no']))
                    <p class="text-sm font-mono mt-1 {{ $lightText ? 'text-white/75' : 'opacity-80' }}">{{ $hero['membership_no'] }}</p>
                @endif
            </div>
        @endif

        @if (! $cleanHome)
            <div>
                @if (! empty($hero['title']))
                    <p class="text-xs uppercase tracking-widest font-semibold {{ $lightText ? 'text-white/80' : 'opacity-80' }}">{{ $hero['title'] }}</p>
                @endif
                @if (! empty($hero['subtitle']))
                    <p class="text-sm mt-1 {{ $lightText ? 'text-white/90' : 'opacity-90' }}">{{ $hero['subtitle'] }}</p>
                @endif
                @if (! empty($hero['meta']))
                    <p class="text-xs mt-1 font-mono {{ $lightText ? 'text-white/70' : 'opacity-80' }}">{{ $hero['meta'] }}</p>
                @endif
            </div>
        @endif

        @if (! empty($hero['eligibility_amount']))
            <div @class([
                'rounded-2xl px-4 py-3 ring-1',
                $lightText ? 'bg-white/15 ring-white/25' : 'bg-white/70 ring-black/5',
            ])>
                <p class="text-[10px] uppercase tracking-widest font-semibold {{ $lightText ? 'text-white/80' : 'text-brand' }}">{{ __('borrower.dashboard.eligibility_title') }}</p>
                <p class="text-3xl sm:text-4xl font-extrabold tabular-nums mt-0.5 {{ $lightText ? 'text-white' : 'text-gray-900' }}">{{ $hero['eligibility_amount'] }}</p>
                @if (! empty($hero['eligibility_hint']))
                    <p class="text-xs mt-1 {{ $lightText ? 'text-white/85' : 'text-gray-600' }}">{{ $hero['eligibility_hint'] }}</p>
                @endif
            </div>
        @elseif (! empty($hero['amount']))
            <p class="text-3xl font-bold tabular-nums">{{ $hero['amount'] }}</p>
        @endif

        @if (! empty($hero['cta_url']) && ! empty($hero['cta_label']))
            <div class="flex flex-wrap gap-2">
                <a href="{{ $hero['cta_url'] }}"
                   data-loading="click"
                   class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white text-brand hover:bg-white/90 shadow-sm">
                    {{ $hero['cta_label'] }}
                </a>
                @if (! empty($hero['secondary_cta_url']) && ! empty($hero['secondary_cta_label']))
                    <a href="{{ $hero['secondary_cta_url'] }}"
                       data-loading="click"
                       class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white/15 text-white ring-1 ring-white/30 hover:bg-white/25">
                        {{ $hero['secondary_cta_label'] }}
                    </a>
                @endif
                @if (! empty($hero['tertiary_cta_url']) && ! empty($hero['tertiary_cta_label']))
                    <a href="{{ $hero['tertiary_cta_url'] }}"
                       data-loading="click"
                       class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-brand-gold/95 text-brand hover:brightness-95 shadow-sm ring-1 ring-brand-gold/40">
                        {{ $hero['tertiary_cta_label'] }}
                    </a>
                @endif
            </div>
        @elseif (! empty($hero['tertiary_cta_url']) && ! empty($hero['tertiary_cta_label']))
            <div class="flex flex-wrap gap-2">
                <a href="{{ $hero['tertiary_cta_url'] }}"
                   data-loading="click"
                   class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white text-brand hover:bg-white/90 shadow-sm">
                    {{ $hero['tertiary_cta_label'] }}
                </a>
            </div>
        @endif
    </div>
</section>
