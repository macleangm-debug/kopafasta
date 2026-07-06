@props([
    'title' => null,
    'description' => null,
])
@php
    $title = $title ?? brand_title(brand('tagline'));
    $description = $description ?? brand('tagline').'. Transparent microfinance for Tanzania.';
    $navProducts = $navProducts ?? collect();
    $siteLocale = $siteLocale ?? app()->getLocale();
    $siteCountry = $siteCountry ?? 'TZ';
    $siteCountries = $siteCountries ?? [];
    $currentCountry = collect($siteCountries)->firstWhere('code', $siteCountry) ?? ['code' => 'TZ', 'name' => 'Tanzania', 'emoji' => '🇹🇿'];
@endphp
<!DOCTYPE html>
<html lang="{{ $siteLocale }}" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-currency" content="{{ currency_code() }}">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-white text-gray-900 antialiased flex flex-col">

    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md shadow-sm border-b border-gray-100">
        <div class="hidden md:block border-b border-gray-100 bg-[#faf8f5]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-10 flex items-center justify-end gap-3">
                <x-site.locale-switcher variant="header" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 grid grid-cols-[auto_1fr_auto] items-center gap-4"
             x-data="{ open: false, menu: null }"
             @keydown.escape.window="menu = null; open = false"
             @click.outside="menu = null">
            <a href="{{ route('site.home') }}" class="flex items-center gap-2 shrink-0">
                <x-site.brand-mark size="md" />
            </a>

            <nav class="hidden lg:flex items-center justify-center gap-1 text-sm font-medium text-gray-700">
                <a href="{{ route('site.home') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">{{ __('site.nav.home') }}</a>

                <div class="relative" @mouseenter="menu = 'products'" @mouseleave="menu = null">
                    <button type="button" @click.stop="menu = menu === 'products' ? null : 'products'"
                            class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand inline-flex items-center gap-1 transition"
                            :class="menu === 'products' ? 'text-brand bg-brand-muted' : ''">
                        {{ __('site.nav.products') }}
                        <svg class="w-3.5 h-3.5 transition" :class="menu === 'products' ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-cloak x-show="menu === 'products'" x-transition.opacity
                         class="absolute left-1/2 -translate-x-1/2 top-full pt-2 w-80">
                        <div class="glass-card p-2 max-h-80 overflow-y-auto bg-white shadow-xl ring-1 ring-gray-200/80">
                            <a href="{{ route('site.products') }}" class="block px-3 py-2 rounded-lg hover:bg-brand-muted text-sm font-semibold text-brand">{{ __('site.nav.all_products') }}</a>
                            @foreach ($navProducts as $navProduct)
                                <a href="{{ route('site.product', $navProduct->code) }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">
                                    {{ $navProduct->localizedName() }}
                                    @if ($navProduct->status !== 'active')
                                        <span class="text-[10px] text-gray-400 uppercase">· {{ __('site.products.status_coming_soon') }}</span>
                                    @endif
                                </a>
                            @endforeach
                            <div class="border-t border-gray-100 mt-1 pt-1">
                                <a href="{{ route('site.marketplace') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm font-medium text-brand">{{ __('site.nav.marketplace') }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="{{ route('site.marketplace') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">{{ __('site.nav.marketplace') }}</a>
                <a href="{{ route('site.affiliate') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">{{ __('site.nav.affiliate') }}</a>
                <a href="{{ route('site.feedback') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">{{ __('site.footer.feedback') }}</a>
            </nav>

            <div class="hidden lg:flex items-center justify-end gap-2">
                @auth
                    <a href="{{ Auth::user()->role === 'vendor' ? route('site.partner.dashboard') : (Auth::user()->role === 'investor' ? route('site.investor.dashboard') : route('site.borrower.dashboard')) }}"
                       class="text-sm font-medium text-gray-700 hover:text-brand">{{ __('site.auth.welcome_back') }}</a>
                    <form method="POST" action="{{ route('site.logout') }}">@csrf
                        <button class="text-sm text-gray-500 hover:text-gray-900">{{ __('site.auth.sign_in') === 'Sign in' ? 'Log out' : 'Toka' }}</button>
                    </form>
                @else
                    <a href="{{ route('site.login') }}" class="text-sm font-semibold text-brand border border-brand/30 hover:border-brand px-4 py-2 rounded-lg transition">{{ __('site.nav.log_in') }}</a>
                    <a href="{{ route('site.register.borrower') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-2 shadow-sm transition">
                        {{ __('site.nav.register') }}
                    </a>
                @endauth
            </div>

            <div class="lg:hidden justify-self-end relative" @click.outside="open = false">
                <button @click.stop="open = !open" class="p-2 rounded-md hover:bg-gray-100" aria-label="Menu" :aria-expanded="open">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div x-cloak x-show="open"
                     class="absolute right-0 top-full mt-1 w-[min(100vw-2rem,20rem)] bg-white shadow-xl ring-1 ring-gray-200 max-h-[80vh] overflow-y-auto z-50 rounded-xl">
                    <div class="px-4 py-4 flex flex-col gap-3 text-sm border-b border-gray-100">
                        <x-site.locale-switcher variant="mobile" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                    </div>
                    <div class="px-4 py-4 flex flex-col gap-1 text-sm">
                        <a href="{{ route('site.home') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.home') }}</a>
                        <a href="{{ route('site.products') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.all_products') }}</a>
                        <a href="{{ route('site.marketplace') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.marketplace') }}</a>
                        <a href="{{ route('site.affiliate') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.affiliate') }}</a>
                        <a href="{{ route('site.how-it-works') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.how_it_works.title') }}</a>
                        <div class="border-t border-gray-200 pt-3 mt-2 flex flex-col gap-2">
                            @auth
                                <a href="{{ Auth::user()->role === 'vendor' ? route('site.partner.dashboard') : (Auth::user()->role === 'investor' ? route('site.investor.dashboard') : route('site.borrower.dashboard')) }}" class="py-1.5">{{ __('site.auth.welcome_back') }}</a>
                                <form method="POST" action="{{ route('site.logout') }}">@csrf<button class="py-1.5 text-left text-gray-500 w-full">Log out</button></form>
                            @else
                                <a href="{{ route('site.login') }}" class="py-1.5">{{ __('site.nav.log_in') }}</a>
                                <a href="{{ route('site.register.borrower') }}" class="inline-flex justify-center rounded-lg bg-brand text-white font-semibold px-4 py-2 mt-1">{{ __('site.nav.register') }}</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    @if (session('status'))
        <div class="bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-sm py-2">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">{{ session('status') }}</div>
        </div>
    @endif

    <main class="flex-1">{{ $slot }}</main>

    <footer class="bg-brand text-gray-300 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-site.brand-mark variant="light" size="md" :showSubtitle="true" />
                <p class="text-sm text-gray-400 max-w-xs mt-3">{{ brand('tagline') }}. {{ __('site.footer.tagline') }}</p>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">{{ __('site.nav.products') }}</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.products') }}" class="hover:text-brand-gold transition">{{ __('site.nav.all_products') }}</a></li>
                    <li><a href="{{ route('site.marketplace') }}" class="hover:text-brand-gold transition">{{ __('site.nav.marketplace') }}</a></li>
                    @foreach ($navProducts->take(4) as $navProduct)
                        <li><a href="{{ route('site.product', $navProduct->code) }}" class="hover:text-brand-gold transition">{{ $navProduct->localizedName() }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">{{ __('site.footer.invest') }}</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.invest') }}" class="hover:text-brand-gold transition">{{ __('site.footer.individual_investor') }}</a></li>
                    <li><a href="{{ route('site.capital-partners') }}" class="hover:text-brand-gold transition">{{ __('site.footer.capital_partner') }}</a></li>
                    <li><a href="{{ route('site.register.investor') }}" class="hover:text-brand-gold transition">{{ __('site.footer.open_account') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">{{ __('site.footer.partners') }}</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.partners') }}" class="hover:text-brand-gold transition">{{ __('site.footer.become_partner') }}</a></li>
                    <li><a href="{{ route('site.affiliate') }}" class="hover:text-brand-gold transition">{{ __('site.footer.become_affiliate') }}</a></li>
                    <li><a href="{{ route('site.login', ['portal' => 'partner']) }}" class="hover:text-brand-gold transition">{{ __('site.auth.partner_portal') }}</a></li>
                    <li><a href="{{ route('site.register.vendor') }}" class="hover:text-brand-gold transition">{{ __('site.auth.create_account') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">{{ __('site.footer.company') }}</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.about') }}" class="hover:text-brand-gold transition">{{ __('site.footer.about') }}</a></li>
                    <li><a href="{{ route('site.how-it-works') }}" class="hover:text-brand-gold transition">{{ __('site.how_it_works.title') }}</a></li>
                    <li><a href="{{ route('site.faq') }}" class="hover:text-brand-gold transition">{{ __('site.footer.faq') }}</a></li>
                    <li><a href="{{ route('site.support') }}" class="hover:text-brand-gold transition">{{ __('site.footer.support') }}</a></li>
                    <li><a href="{{ route('site.feedback') }}" class="hover:text-brand-gold transition">{{ __('site.footer.feedback') }}</a></li>
                    <li><a href="mailto:{{ brand('support_email') }}" class="hover:text-brand-gold transition">{{ __('site.nav.contact') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 py-5 text-xs text-gray-500">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
                <span>&copy; {{ date('Y') }} {{ brand('legal_name') }}.</span>
                <span class="flex items-center gap-4">
                    <a href="{{ route('site.faq') }}" class="hover:text-brand-gold transition">{{ __('site.footer.terms') }}</a>
                    <a href="{{ route('site.faq') }}" class="hover:text-brand-gold transition">{{ __('site.footer.privacy') }}</a>
                </span>
            </div>
        </div>
    </footer>

    <x-site.chatbot-widget />
    <x-site.confirm-modal name="default" />
    <script>
        document.addEventListener('alpine:init', () => {
            window.confirmForm = (form, detail = {}) => {
                window.dispatchEvent(new CustomEvent('open-confirm-default', { detail: { form, ...detail } }));
            };
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
