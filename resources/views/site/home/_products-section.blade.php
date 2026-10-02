{{-- Featured products — shared section grammar + subtle gutter carousel controls --}}
<div
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
    <x-site.public-section
        :eyebrow="__('site.products.featured_title')"
        :title="__('site.products.all_title')"
        :body="__('site.products.all_subtitle')"
        :action-href="route('site.products')"
        :action-label="__('site.products.view_all')"
    >
        @if ($products->isNotEmpty())
            <div class="kf-carousel-shell">
                <div
                    x-ref="track"
                    class="kf-carousel-track"
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
                    <x-site.carousel-control
                        direction="prev"
                        :label="__('site.products.carousel_prev')"
                        @click="scrollByCard(-1)"
                    />
                    <x-site.carousel-control
                        direction="next"
                        :label="__('site.products.carousel_next')"
                        @click="scrollByCard(1)"
                    />
                @endif
            </div>
        @endif
    </x-site.public-section>
</div>
