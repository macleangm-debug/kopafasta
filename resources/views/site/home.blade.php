<x-site.layout>

    {{-- HERO --}}
    <section class="relative overflow-hidden premium-gradient">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 lg:py-16">
            @include($landingHeroPartial ?? 'site.home._hero-a')
        </div>
    </section>

    @if ($landingProductsFirst ?? false)
        @include('site.home._products-section')
        @include('site.home._steps-strip')
    @else
        @include('site.home._steps-strip')
        @include('site.home._products-section')
    @endif

    {{-- KOPAFASTA PLUS --}}
    <section class="py-12 lg:py-14 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-brand font-bold mb-2">
                <span class="text-brand-gold tracking-[-0.16em]" aria-hidden="true">›››</span>
                {{ __('site.plus.teaser_kicker') }}
            </p>
            <h2 class="text-2xl sm:text-4xl font-bold text-gray-900">{{ __('site.plus.teaser_title') }}</h2>
            <p class="mt-3 text-lg text-gray-600">{{ __('site.plus.hero_title') }}</p>
            <p class="mt-2 text-gray-600">{{ __('site.plus.teaser_body') }}</p>
            <a href="{{ route('site.plus') }}" class="mt-5 inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('site.plus.explore') }} →</a>
        </div>
    </section>

    {{-- MARKETPLACE --}}
    <section class="premium-gradient py-12 lg:py-14 border-y border-gray-100/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.marketplace.title') }}</p>
                    <h2 class="text-2xl sm:text-4xl font-bold text-gray-900">{{ __('site.marketplace.subtitle') }}</h2>
                </div>
                <a href="{{ route('site.marketplace') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.marketplace.view_all') }} →</a>
            </div>
            @if (! empty($featuredAssets))
                <div class="overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory">
                    <div class="flex gap-5 w-max">
                        @foreach ($featuredAssets as $asset)
                            <div class="snap-start shrink-0 w-[min(320px,calc(100vw-2rem))]">
                                @include('site.marketplace._asset-card', [
                                    'asset' => $asset,
                                    'categories' => $marketplaceCategories,
                                    'showUrl' => route('site.marketplace.show', $asset['id']),
                                    'authenticated' => false,
                                ])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @guest
                <p class="mt-4 text-sm text-gray-600">{{ __('site.marketplace.guest_cta') }}
                    <a href="{{ route('site.register.borrower') }}" class="text-brand font-semibold">{{ __('site.nav.register') }}</a>
                    · <a href="{{ route('site.login') }}" class="text-brand font-semibold">{{ __('site.nav.log_in') }}</a>
                </p>
            @endguest
        </div>
    </section>

    {{-- AFFILIATE TEASER --}}
    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="inline-flex items-center gap-2 text-brand-gold font-black tracking-[-0.16em] text-2xl" aria-hidden="true">›››</p>
            <h2 class="mt-2 text-2xl sm:text-3xl font-bold text-brand">{{ __('site.affiliate.hero_title') }}</h2>
            <p class="mt-2 text-gray-600 max-w-md mx-auto text-sm sm:text-base">{{ __('site.affiliate.hero_body') }}</p>
            <a href="{{ route('site.affiliate') }}" class="mt-5 inline-flex items-center gap-2 bg-brand text-white font-semibold px-6 py-3 rounded-xl hover:bg-brand-light transition shadow-md">
                {{ __('site.affiliate.cta_apply') }}
            </a>
        </div>
    </section>

</x-site.layout>
