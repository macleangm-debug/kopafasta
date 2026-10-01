{{-- How it works — constrained premium strip (copy unchanged) --}}
<section class="bg-white border-b border-gray-100 py-10 lg:py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 text-center sm:text-left">
            <p class="text-[11px] uppercase tracking-[0.2em] text-brand font-semibold">{{ __('site.how_it_works.title') }}</p>
        </div>
        <div class="overflow-x-auto pb-2 -mx-4 px-4 snap-x snap-mandatory lg:overflow-visible lg:mx-0 lg:px-0">
            <div class="flex lg:grid lg:grid-cols-4 gap-4 w-max lg:w-auto">
                @foreach (__('site.how_it_works.steps') as $i => $step)
                    <div class="snap-start shrink-0 w-[min(240px,calc(100vw-3.5rem))] lg:w-auto rounded-2xl bg-gradient-to-b from-brand-muted/40 to-white ring-1 ring-brand/10 shadow-sm p-5 flex flex-col">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="size-10 rounded-xl bg-brand text-white text-sm font-extrabold grid place-items-center shrink-0 tabular-nums">{{ $i + 1 }}</span>
                            <span class="text-2xl leading-none" aria-hidden="true">{{ $step['icon'] }}</span>
                        </div>
                        <h3 class="font-bold text-gray-900 text-[15px] leading-snug">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-snug">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
