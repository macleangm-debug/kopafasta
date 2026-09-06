<x-site.layout>

    {{-- HERO --}}
    <section class="relative overflow-hidden premium-gradient">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 lg:py-12">
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

    {{-- KOPAFASTA PLUS — premium feature card --}}
    <section class="py-10 lg:py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-brand via-[#127A5F] to-[#082f27] text-white shadow-[0_24px_60px_rgba(8,47,39,0.24)] ring-1 ring-brand-gold/30">
                <div class="absolute inset-0 opacity-[0.16] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
                <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-brand-gold/10 pointer-events-none"></div>
                <div class="relative grid lg:grid-cols-2 gap-8 lg:gap-12 items-center px-6 sm:px-10 py-8 sm:py-10">
                    <div class="text-left">
                        <p class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.2em] text-brand-gold">
                            <span class="text-lg tracking-[-0.18em] leading-none" aria-hidden="true">›››</span>
                            {{ __('site.plus.teaser_kicker') }}
                        </p>
                        <h2 class="mt-3 text-2xl sm:text-4xl font-black tracking-tight leading-tight">{{ __('site.plus.teaser_title') }}</h2>
                        <p class="mt-3 text-white/80 leading-relaxed max-w-xl">{{ __('site.plus.teaser_body') }}</p>
                        <a href="{{ route('site.plus') }}" class="mt-6 inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand font-extrabold px-5 py-3">
                            {{ __('site.plus.explore') }} →
                        </a>
                    </div>
                    <ul class="grid grid-cols-2 gap-3">
                        @foreach ([
                            __('site.plus.teaser_benefit_1'),
                            __('site.plus.teaser_benefit_2'),
                            __('site.plus.teaser_benefit_3'),
                            __('site.plus.teaser_benefit_4'),
                        ] as $benefit)
                            <li class="rounded-2xl bg-white/10 ring-1 ring-white/15 px-4 py-3.5 text-sm font-semibold text-white/95">
                                <span class="text-brand-gold font-black tracking-[-0.12em]" aria-hidden="true">›››</span>
                                <span class="mt-2 block leading-snug">{{ $benefit }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- MARKETPLACE --}}
    <section class="premium-gradient py-10 lg:py-12 border-y border-gray-100/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-6 text-left">
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.marketplace.title') }}</p>
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('site.marketplace.subtitle') }}</h2>
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
                <p class="mt-3 text-sm text-gray-600 text-left">{{ __('site.marketplace.guest_cta') }}
                    <a href="{{ route('site.register.borrower') }}" class="text-brand font-semibold">{{ __('site.nav.register') }}</a>
                    · <a href="{{ route('site.login') }}" class="text-brand font-semibold">{{ __('site.nav.log_in') }}</a>
                </p>
            @endguest
        </div>
    </section>

    {{-- AFFILIATE — premium card family --}}
    <section class="py-10 lg:py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#5c3d1e] via-[#8b5a2b] to-[#3f2a14] text-white shadow-[0_24px_60px_rgba(63,42,20,0.28)] ring-1 ring-brand-gold/35">
                <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold"></div>
                <div class="absolute inset-0 opacity-[0.14] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
                <div class="relative grid lg:grid-cols-[1.4fr_1fr] gap-8 items-center px-6 sm:px-10 py-8 sm:py-10">
                    <div class="text-left">
                        <p class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.2em] text-brand-gold">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-brand/80 text-brand-gold text-sm font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                            {{ __('site.affiliate.home_kicker') }}
                        </p>
                        <h2 class="mt-3 text-2xl sm:text-4xl font-black tracking-tight">{{ __('site.affiliate.home_title') }}</h2>
                        <p class="mt-3 text-white/80 max-w-xl leading-relaxed">{{ __('site.affiliate.home_body') }}</p>
                        <a href="{{ route('site.affiliate') }}" class="mt-6 inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-extrabold px-6 py-3 rounded-xl transition">
                            {{ __('site.affiliate.cta_apply') }}
                        </a>
                    </div>
                    <ul class="space-y-3">
                        @foreach ([
                            __('site.affiliate.home_benefit_1'),
                            __('site.affiliate.home_benefit_2'),
                            __('site.affiliate.home_benefit_3'),
                        ] as $item)
                            <li class="flex items-start gap-3 rounded-2xl bg-black/20 ring-1 ring-white/10 px-4 py-3 text-sm font-semibold">
                                <span class="text-brand-gold font-bold shrink-0" aria-hidden="true">›</span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

</x-site.layout>
