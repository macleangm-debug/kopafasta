{{-- Featured products grid --}}
<section class="bg-white py-14 lg:py-18" x-data="{ expanded: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.products.featured_title') }}</p>
        <h2 class="text-2xl sm:text-4xl font-bold text-gray-900">{{ __('site.products.all_title') }}</h2>
        <p class="mt-2 text-gray-600 max-w-xl mx-auto text-sm sm:text-base">{{ __('site.products.all_subtitle') }}</p>

        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-5 text-left">
            @foreach ($products as $index => $product)
                <div @if($index >= 4) x-show="expanded" x-collapse x-cloak @endif>
                    <x-site.product-card :product="$product" />
                </div>
            @endforeach
        </div>

        @if ($products->count() > 4)
            <button type="button" @click="expanded = !expanded"
                    class="mt-8 inline-flex items-center gap-2 rounded-xl border border-brand/30 text-brand hover:bg-brand-muted font-semibold px-6 py-3 transition">
                <span x-text="expanded ? @js(__('site.products.see_less')) : @js(__('site.products.see_more'))"></span>
                <svg class="w-4 h-4 transition-transform" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
        @endif

        <a href="{{ route('site.products') }}" class="mt-4 block text-sm font-semibold text-brand hover:underline">{{ __('site.nav.all_products') }} →</a>
    </div>
</section>
