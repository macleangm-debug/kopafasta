<x-site.layout :title="brand_name().' — '.__('site.hero.title')">

    {{-- HERO --}}
    <section class="relative overflow-hidden premium-gradient">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-20">
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

    {{-- MARKETPLACE --}}
    <section class="premium-gradient py-14 lg:py-18 border-y border-gray-100/80" x-data="{ ready: false }" x-init="requestAnimationFrame(() => ready = true)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.marketplace.title') }}</p>
                    <h2 class="text-2xl sm:text-4xl font-bold text-gray-900">{{ __('site.marketplace.subtitle') }}</h2>
                </div>
                <a href="{{ route('site.marketplace') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.marketplace.view_all') }} →</a>
            </div>
            @if (! empty($featuredAssets))
                <div class="relative min-h-[280px]">
                    <div x-show="!ready" class="overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory">
                        <div class="flex gap-5 w-max">
                            @for ($i = 0; $i < 3; $i++)
                                <div class="snap-start shrink-0 w-[min(320px,calc(100vw-2rem))]">
                                    <x-site.skeleton-card />
                                </div>
                            @endfor
                        </div>
                    </div>
                    <div x-show="ready" x-cloak class="overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory">
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

    {{-- STATS --}}
    <section class="bg-brand text-white py-10 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-3 lg:grid-cols-4 gap-6 sm:gap-8 text-center">
            @foreach (__('site.stats') as $i => $stat)
                <div class="{{ $i === 3 ? 'hidden lg:block' : '' }}">
                    <div class="text-xl sm:text-3xl font-bold">{{ $stat['value'] }}</div>
                    <div class="text-xs sm:text-sm text-white/70 mt-1">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- AFFILIATE TEASER --}}
    <section class="py-14 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <span class="text-4xl mb-4 block" aria-hidden="true">🤝</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-brand">{{ __('site.affiliate.hero_title') }}</h2>
            <p class="mt-2 text-gray-600 max-w-md mx-auto text-sm sm:text-base">{{ __('site.affiliate.hero_body') }}</p>
            <a href="{{ route('site.affiliate') }}" class="mt-5 inline-flex items-center gap-2 bg-brand text-white font-semibold px-6 py-3 rounded-xl hover:bg-brand-light transition shadow-md">
                {{ __('site.affiliate.cta_apply') }}
            </a>
        </div>
    </section>

</x-site.layout>
