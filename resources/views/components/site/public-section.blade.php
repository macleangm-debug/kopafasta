@props([
    'eyebrow' => null,
    'title' => null,
    'body' => null,
    'actionHref' => null,
    'actionLabel' => null,
    'tone' => 'white', // white | muted | soft | brand-soft
    'narrow' => false,
    'pad' => true,
    'reveal' => true,
])

@php
    $bg = match ($tone) {
        'muted' => 'bg-[#f7faf8]',
        'soft', 'brand-soft' => 'premium-gradient',
        default => 'bg-white',
    };
    $hasIntro = filled($eyebrow) || filled($title) || filled($body) || (filled($actionHref) && filled($actionLabel));
@endphp

<section
    {{ $attributes->class([
        'kf-public-section',
        $bg,
        'border-y border-brand/5' => $tone !== 'white',
        '!py-0' => ! $pad,
    ]) }}
    @if ($reveal) data-kf-reveal @endif
>
    <div @class([
        'kf-public-container',
        '!max-w-3xl' => $narrow,
    ])>
        @if ($hasIntro)
            <header class="kf-public-section__intro">
                <div class="kf-public-section__copy min-w-0 flex-1">
                    @if (filled($eyebrow))
                        <p class="kf-public-section__eyebrow">{{ $eyebrow }}</p>
                    @endif
                    @if (filled($title))
                        <h2 class="kf-public-section__title">{{ $title }}</h2>
                    @endif
                    @if (filled($body))
                        <p class="kf-public-section__body">{{ $body }}</p>
                    @endif
                </div>
                @if (filled($actionHref) && filled($actionLabel))
                    <a href="{{ $actionHref }}" class="kf-public-section__action shrink-0">
                        {{ $actionLabel }}
                    </a>
                @endif
            </header>
        @endif

        <div @class(['kf-public-section__content' => $hasIntro])>
            {{ $slot }}
        </div>
    </div>
</section>
