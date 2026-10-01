{{-- How it works — constrained premium journey (concise copy; no truncation) --}}
@php
    $howSteps = array_values(__('site.how_it_works.steps') ?: []);
@endphp
<section class="bg-white border-b border-gray-100 py-10 lg:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 text-center sm:text-left">
            <p class="text-[11px] uppercase tracking-[0.2em] text-brand font-semibold">{{ __('site.how_it_works.title') }}</p>
            @if (count($howSteps) >= 4)
                <p class="mt-2 text-sm text-gray-600 font-medium hidden sm:block">
                    1 {{ $howSteps[0]['title'] }} → 2 {{ $howSteps[1]['title'] }} → 3 {{ $howSteps[2]['title'] }} → 4 {{ $howSteps[3]['title'] }}
                </p>
            @endif
        </div>
        <div class="overflow-x-auto pb-2 -mx-4 px-4 snap-x snap-mandatory lg:overflow-visible lg:mx-0 lg:px-0">
            <div class="flex lg:grid lg:grid-cols-4 gap-3 lg:gap-0 w-max lg:w-auto relative">
                <div class="hidden lg:block absolute top-7 left-[12%] right-[12%] h-0.5 bg-gradient-to-r from-brand/20 via-brand/40 to-brand/20 pointer-events-none" aria-hidden="true"></div>
                @foreach ($howSteps as $i => $step)
                    <div class="snap-start shrink-0 w-[min(220px,calc(100vw-3.5rem))] lg:w-auto relative z-[1]">
                        <div class="rounded-2xl bg-gradient-to-b from-brand-muted/35 to-white ring-1 ring-brand/10 shadow-sm p-4 h-full flex flex-col">
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
    </div>
</section>
