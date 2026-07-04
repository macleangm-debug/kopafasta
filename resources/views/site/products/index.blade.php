<x-site.layout :title="brand_title(__('site.nav.all_products'))">
    <section class="relative overflow-hidden premium-gradient border-b border-gray-100/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.products.featured_title') }}</p>
            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-brand">{{ __('site.products.all_title') }}</h1>
            <p class="mt-3 text-gray-600 max-w-2xl text-sm sm:text-base">{{ __('site.products.all_subtitle') }}</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        @if ($products->isEmpty())
            <x-site.empty-state
                icon="📋"
                :title="__('borrower.dashboard_page.no_products')"
                :description="__('site.products.all_subtitle')"
            />
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 lg:gap-6">
                @foreach ($products as $product)
                    @if (is_marketplace_loan_product($product->code))
                        <article class="glass-card overflow-hidden flex flex-col h-full hover:shadow-[0_16px_48px_rgba(0,77,64,0.14)] hover:-translate-y-0.5 transition-all duration-300">
                            <div class="p-4 pb-0">
                                <x-site.product-illustration code="AL" size="card" />
                            </div>
                            <div class="p-5 pt-4 flex flex-col flex-1">
                                <p class="text-[11px] font-mono font-semibold uppercase tracking-widest text-brand/60">{{ $product->code }}</p>
                                <h2 class="text-xl font-extrabold text-brand leading-tight mt-1">{{ $product->localizedName() }}</h2>
                                <p class="mt-2 text-sm text-gray-600 flex-1">{{ __('borrower.marketplace.subtitle') }}</p>
                                <a href="{{ route('site.marketplace') }}"
                                   class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-bold px-4 py-3 transition shadow-sm">
                                    {{ __('site.marketplace.browse') }}
                                </a>
                            </div>
                        </article>
                    @else
                        <x-site.product-card :product="$product" />
                    @endif
                @endforeach
            </div>

            <div class="mt-12 glass-card p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ __('site.products.picker_help_title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1 max-w-xl">{{ __('site.products.picker_help_body') }}</p>
                </div>
                @auth
                    <a href="{{ route('site.borrower.dashboard') }}"
                       class="inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shrink-0">
                        {{ __('borrower.nav.dashboard') }} →
                    </a>
                @else
                    <a href="{{ route('site.register.borrower') }}"
                       class="inline-flex justify-center bg-brand hover:bg-brand-light text-white font-bold px-6 py-3 rounded-xl text-sm shrink-0">
                        {{ __('site.hero.get_started') }}
                    </a>
                @endauth
            </div>
        @endif
    </section>
</x-site.layout>
