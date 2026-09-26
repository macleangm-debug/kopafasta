@props([
    'variant' => 'line',
    'lines' => 3,
    'cards' => 4,
    'width' => 'w-full',
    'height' => 'h-3',
])

@if ($variant === 'line')
    <div {{ $attributes->merge(['class' => "kf-skeleton {$width} {$height}"]) }}></div>
@elseif ($variant === 'hero')
    <div {{ $attributes->merge(['class' => 'kf-skeleton-surface rounded-2xl p-6 sm:p-8 space-y-4']) }}>
        <div class="kf-skeleton h-3 w-28"></div>
        <div class="kf-skeleton h-8 w-2/3 max-w-md"></div>
        <div class="kf-skeleton h-3 w-1/2 max-w-sm"></div>
        <div class="kf-skeleton h-3 w-40 mt-4"></div>
    </div>
@elseif ($variant === 'cards')
    <div {{ $attributes->merge(['class' => 'grid grid-cols-2 lg:grid-cols-4 gap-3']) }}>
        @for ($i = 0; $i < $cards; $i++)
            <div class="kf-skeleton-surface rounded-xl p-4 space-y-3 min-h-[6.5rem]">
                <div class="kf-skeleton h-3 w-1/2"></div>
                <div class="kf-skeleton h-7 w-2/3"></div>
            </div>
        @endfor
    </div>
@elseif ($variant === 'rows')
    <div {{ $attributes->merge(['class' => 'space-y-3']) }}>
        @for ($i = 0; $i < $lines; $i++)
            <div class="kf-skeleton-surface rounded-xl px-4 py-3 space-y-2">
                <div class="kf-skeleton h-3 w-1/3"></div>
                <div class="kf-skeleton h-3 w-2/3"></div>
            </div>
        @endfor
    </div>
@elseif ($variant === 'report')
    <div {{ $attributes->merge(['class' => 'space-y-4']) }}>
        <x-site.skeleton variant="hero" />
        <div class="kf-skeleton-surface rounded-2xl p-5 space-y-3">
            <div class="kf-skeleton h-4 w-40"></div>
            <x-site.skeleton variant="cards" :cards="4" />
        </div>
    </div>
@elseif ($variant === 'profile')
    <div {{ $attributes->merge(['class' => 'space-y-4']) }}>
        <x-site.skeleton variant="hero" />
        @for ($i = 0; $i < $lines; $i++)
            <div class="kf-skeleton-surface rounded-2xl p-5 space-y-3">
                <div class="kf-skeleton h-4 w-1/3"></div>
                <div class="kf-skeleton h-3 w-full"></div>
                <div class="kf-skeleton h-3 w-2/3"></div>
            </div>
        @endfor
    </div>
@elseif ($variant === 'asset')
    <div {{ $attributes->merge(['class' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4']) }}>
        @for ($i = 0; $i < $cards; $i++)
            <div class="kf-skeleton-surface rounded-2xl overflow-hidden">
                <div class="kf-skeleton h-36 w-full rounded-none"></div>
                <div class="p-4 space-y-3">
                    <div class="kf-skeleton h-4 w-3/4"></div>
                    <div class="kf-skeleton h-3 w-1/2"></div>
                </div>
            </div>
        @endfor
    </div>
@else
    <x-site.skeleton-card :lines="$lines" {{ $attributes }} />
@endif
