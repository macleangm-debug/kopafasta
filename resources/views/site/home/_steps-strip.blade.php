{{-- How it works compact strip --}}
<section class="bg-white border-b border-gray-100 py-8 lg:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-4 text-center lg:text-left">{{ __('site.how_it_works.title') }}</p>
        <div class="overflow-x-auto pb-2 -mx-4 px-4 snap-x snap-mandatory lg:overflow-visible lg:mx-0 lg:px-0">
            <div class="flex lg:grid lg:grid-cols-4 gap-4 w-max lg:w-auto min-w-full">
                @foreach (__('site.how_it_works.steps') as $i => $step)
                    <div class="snap-start shrink-0 w-[min(260px,calc(100vw-3rem))] lg:w-auto glass-card p-5 flex flex-col">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="size-9 rounded-xl bg-brand text-white text-sm font-bold grid place-items-center shrink-0">{{ $i + 1 }}</span>
                            <span class="text-2xl" aria-hidden="true">{{ $step['icon'] }}</span>
                        </div>
                        <h3 class="font-bold text-gray-900">{{ $step['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-gray-600 leading-snug line-clamp-2">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
