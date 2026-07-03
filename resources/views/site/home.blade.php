<x-site.layout :title="brand_name().' — '.__('site.hero.title')">

    {{-- HERO --}}
    <section class="relative overflow-hidden premium-gradient">
        <div class="absolute inset-0 bg-cover bg-center opacity-[0.08] blur-[2px]"
             style="background-image: url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1600&q=80');"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-[#faf8f5] via-[#faf8f5]/95 to-transparent"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20">
            <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div class="animate-fade-up">
                    <span class="inline-flex items-center gap-2 rounded-full glass-card px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-gray-600">
                        {{ __('site.hero.badge') }}
                    </span>
                    <h1 class="mt-5 text-4xl sm:text-5xl lg:text-[3.25rem] font-bold tracking-tight leading-[1.1] text-brand">
                        {{ __('site.hero.title') }}
                    </h1>
                    <p class="mt-5 text-base sm:text-lg text-gray-600 max-w-lg leading-relaxed">
                        {{ __('site.hero.subtitle') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('site.register.borrower') }}" class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-xl shadow-md transition hover:shadow-lg">
                            {{ __('site.hero.get_started') }}
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                        </a>
                        <a href="{{ route('site.how-it-works') }}" class="inline-flex items-center gap-2 glass-card hover:bg-white text-brand font-semibold px-6 py-3 rounded-xl transition">
                            {{ __('site.hero.learn_more') }}
                        </a>
                    </div>
                </div>
                <div class="relative lg:min-h-[32rem] animate-fade-up">
                    <div class="relative rounded-3xl overflow-hidden shadow-2xl aspect-[4/5] lg:aspect-auto lg:h-[32rem] bg-brand-muted ring-1 ring-white/50">
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=800&q=80" alt="" class="absolute inset-0 w-full h-full object-cover object-top">
                    </div>
                    @guest
                    <div class="absolute -left-4 sm:left-4 top-8 sm:top-12 w-[min(100%,20rem)] glass-card p-6 z-10" x-data="{ tab: 'login' }">
                        <p class="text-lg font-bold text-gray-900">{{ __('site.hero.welcome_back') }} 👋</p>
                        <div class="mt-4 inline-flex rounded-xl ring-1 ring-gray-200/80 bg-gray-50/80 p-1 text-sm w-full">
                            <button type="button" @click="tab = 'login'" :class="tab === 'login' ? 'bg-white text-brand shadow-sm font-semibold' : 'text-gray-500'" class="flex-1 rounded-lg py-2 transition">{{ __('site.nav.log_in') }}</button>
                            <button type="button" @click="tab = 'register'" :class="tab === 'register' ? 'bg-white text-brand shadow-sm font-semibold' : 'text-gray-500'" class="flex-1 rounded-lg py-2 transition">{{ __('site.nav.register') }}</button>
                        </div>
                        <div x-show="tab === 'login'" x-cloak class="mt-4">
                            <form method="POST" action="{{ route('site.login.post') }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="auth_method" value="pin">
                                <input type="tel" name="phone" placeholder="{{ __('site.auth.phone_pin') }}" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                                <input type="password" name="pin" maxlength="4" placeholder="PIN" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                                <button class="w-full bg-brand text-white font-semibold py-2.5 rounded-xl text-sm hover:bg-brand-light transition">{{ __('site.nav.log_in') }}</button>
                            </form>
                        </div>
                        <div x-show="tab === 'register'" x-cloak class="mt-4">
                            <a href="{{ route('site.register.borrower') }}" class="block w-full text-center bg-brand text-white font-semibold py-2.5 rounded-xl text-sm hover:bg-brand-light transition">{{ __('site.nav.register') }}</a>
                        </div>
                    </div>
                    @endguest
                </div>
            </div>
        </div>
    </section>

    {{-- PRODUCTS --}}
    <section class="bg-white py-16 lg:py-20" x-data="{ expanded: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.products.featured_title') }}</p>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">{{ __('site.products.all_title') }}</h2>
            <p class="mt-3 text-gray-600 max-w-2xl mx-auto">{{ __('site.products.all_subtitle') }}</p>

            <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-5 text-left">
                @foreach ($products as $index => $product)
                    <div @if($index >= 4) x-show="expanded" x-collapse x-cloak @endif>
                        <x-site.product-card :product="$product" />
                    </div>
                @endforeach
            </div>

            @if ($products->count() > 4)
                <button type="button" @click="expanded = !expanded"
                        class="mt-10 inline-flex items-center gap-2 rounded-xl border border-brand/30 text-brand hover:bg-brand-muted font-semibold px-6 py-3 transition">
                    <span x-text="expanded ? @js(__('site.products.see_less')) : @js(__('site.products.see_more'))"></span>
                    <svg class="w-4 h-4 transition-transform" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </button>
            @endif

            <a href="{{ route('site.products') }}" class="mt-4 block text-sm font-semibold text-brand hover:underline">{{ __('site.nav.all_products') }} →</a>
        </div>
    </section>

    {{-- MARKETPLACE --}}
    <section class="premium-gradient py-16 lg:py-20 border-y border-gray-100/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.marketplace.title') }}</p>
                    <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">{{ __('site.marketplace.subtitle') }}</h2>
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

    {{-- STATS --}}
    <section class="bg-brand text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 lg:grid-cols-4 gap-8 text-center lg:text-left">
            @foreach (__('site.stats') as $stat)
                <div>
                    <div class="text-2xl sm:text-3xl font-bold">{{ $stat['value'] }}</div>
                    <div class="text-sm text-white/70 mt-1">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- AFFILIATE TEASER --}}
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-brand">{{ __('site.affiliate.hero_title') }}</h2>
            <p class="mt-3 text-gray-600 max-w-xl mx-auto">{{ __('site.affiliate.hero_body') }}</p>
            <a href="{{ route('site.affiliate') }}" class="mt-6 inline-flex items-center gap-2 bg-brand text-white font-semibold px-6 py-3 rounded-xl hover:bg-brand-light transition shadow-md">
                {{ __('site.affiliate.cta_apply') }}
            </a>
        </div>
    </section>

</x-site.layout>
