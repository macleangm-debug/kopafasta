<x-site.layout>

    {{-- HERO --}}
    <section class="relative overflow-hidden premium-gradient">
        <div class="relative kf-public-container py-6 sm:py-10 lg:py-12">
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

    {{-- KOPAFASTA PLUS — shared section rhythm; card keeps Plus-specific visual --}}
    <x-site.public-section
        :eyebrow="__('site.plus.teaser_kicker')"
        :title="__('site.plus.teaser_title')"
        :body="__('site.plus.teaser_body')"
    >
        <div class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-brand via-[#127A5F] to-[#082f27] text-white shadow-[0_24px_60px_rgba(8,47,39,0.24)] ring-1 ring-brand-gold/30 kf-glass-hero"
             data-kf-glass-hero
             x-data
             x-init="
                const key = 'kf-glass-glare:' + window.location.pathname + ':plus';
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                try {
                    if (sessionStorage.getItem(key) === '1') return;
                    sessionStorage.setItem(key, '1');
                    $el.classList.add('kf-glass-hero--glare');
                } catch (e) { $el.classList.add('kf-glass-hero--glare'); }
             ">
            <x-site.glass-hero-surface />
            <div class="absolute inset-0 opacity-[0.16] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
            <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-brand-gold/10 pointer-events-none"></div>
            <div class="relative grid lg:grid-cols-2 gap-8 lg:gap-12 items-center px-6 sm:px-10 py-8 sm:py-10">
                <div class="text-left">
                    <a href="{{ route('site.plus') }}" class="inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand font-extrabold px-5 py-3 kf-press">
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
    </x-site.public-section>

    {{-- MARKETPLACE --}}
    <x-site.public-section
        tone="soft"
        :eyebrow="__('site.marketplace.title')"
        :title="__('site.marketplace.subtitle')"
        :body="__('site.marketplace.body')"
        :action-href="route('site.marketplace')"
        :action-label="__('site.marketplace.view_all')"
    >
        @if (! empty($featuredAssets))
            <div class="kf-carousel-track">
                <div class="flex gap-5 w-max">
                    @foreach ($featuredAssets as $asset)
                        <div class="snap-start shrink-0 w-[calc(100vw-2rem)] sm:w-[min(320px,calc(100vw-2rem))]">
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
            <p class="mt-4 text-sm text-gray-600 text-left">{{ __('site.marketplace.guest_cta') }}
                <a href="{{ route('site.register.borrower') }}" class="text-brand font-semibold">{{ __('site.nav.register') }}</a>
                · <a href="{{ route('site.login') }}" class="text-brand font-semibold">{{ __('site.nav.log_in') }}</a>
            </p>
        @endguest
    </x-site.public-section>

    {{-- AFFILIATE — premium card family --}}
    <section class="kf-public-section bg-white">
        <div class="kf-public-container">
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
