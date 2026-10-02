{{-- How it works — shared public section grammar; cards carry 1→4 --}}
@php
    $howSteps = array_values(__('site.how_it_works.steps') ?: []);
@endphp
<x-site.public-section
    :eyebrow="__('site.how_it_works.title')"
    :title="__('site.how_it_works.headline')"
    :body="__('site.how_it_works.subtitle')"
>
    <div class="overflow-x-auto pb-2 -mx-4 px-4 snap-x snap-mandatory lg:overflow-visible lg:mx-0 lg:px-0">
        <div class="flex lg:grid lg:grid-cols-4 gap-3 lg:gap-4 w-max lg:w-auto relative">
            <div class="hidden lg:block absolute top-8 left-[10%] right-[10%] h-0.5 bg-gradient-to-r from-brand/20 via-brand/40 to-brand/20 pointer-events-none" aria-hidden="true"></div>
            @foreach ($howSteps as $i => $step)
                <div class="snap-start shrink-0 w-[min(240px,calc(100vw-3.5rem))] lg:w-auto relative z-[1]" data-kf-reveal data-kf-reveal-delay="{{ min($i + 1, 3) }}">
                    <div class="kf-info-card kf-card-lift p-4 sm:p-5 h-full flex flex-col bg-gradient-to-b from-brand-muted/35 to-white">
                        <div class="flex items-center gap-2.5 mb-2.5">
                            <span class="size-9 rounded-xl bg-brand text-white text-sm font-extrabold grid place-items-center shrink-0 tabular-nums ring-4 ring-white">{{ $i + 1 }}</span>
                            <span class="text-xl leading-none" aria-hidden="true">{{ $step['icon'] ?? '' }}</span>
                        </div>
                        <h3 class="font-bold text-gray-900 text-sm leading-snug">{{ $step['title'] ?? '' }}</h3>
                        <p class="mt-1.5 text-xs text-gray-600 leading-snug">{{ $step['body'] ?? '' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.public-section>
