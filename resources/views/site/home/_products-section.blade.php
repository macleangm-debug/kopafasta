{{-- Featured products — horizontal snap carousel on mobile and desktop --}}
<section
    class="bg-white py-14 lg:py-18"
    data-landing-products
    x-data="{
        scrollByCard(dir) {
            const track = this.$refs.track;
            if (!track) return;
            const slide = track.querySelector('[data-product-slide]');
            const step = (slide ? slide.getBoundingClientRect().width : 320) + 20;
            track.scrollBy({ left: dir * step, behavior: 'smooth' });
        },
    }"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.products.featured_title') }}</p>
                <h2 class="text-2xl sm:text-4xl font-bold text-gray-900">{{ __('site.products.all_title') }}</h2>
                <p class="mt-2 text-gray-600 max-w-xl text-sm sm:text-base">{{ __('site.products.all_subtitle') }}</p>
            </div>
            <a href="{{ route('site.products') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.nav.all_products') }} →</a>
        </div>

        @if ($products->isNotEmpty())
            <div class="relative">
                <div
                    x-ref="track"
                    class="overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory scroll-smooth scrollbar-none"
                    style="-webkit-overflow-scrolling: touch;"
                >
                    <div class="flex gap-5 w-max items-stretch">
                        @foreach ($products as $product)
                            <div data-product-slide class="snap-start shrink-0 w-[min(320px,calc(100vw-2.5rem))]">
                                <x-site.product-card :product="$product" />
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($products->count() > 1)
                    <button
                        type="button"
                        @click="scrollByCard(-1)"
                        class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1 z-10 grid place-items-center size-10 rounded-full bg-brand text-white shadow-md"
                        aria-label="{{ __('site.products.carousel_prev') }}"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4 6 10l6 6"/></svg>
                    </button>
                    <button
                        type="button"
                        @click="scrollByCard(1)"
                        class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1 z-10 grid place-items-center size-10 rounded-full bg-brand text-white shadow-md"
                        aria-label="{{ __('site.products.carousel_next') }}"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 4l6 6-6 6"/></svg>
                    </button>
                @endif
            </div>
        @endif
    </div>
</section>
