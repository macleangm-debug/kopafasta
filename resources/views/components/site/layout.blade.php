@props([
    'title' => null,
    'description' => null,
    'auth' => false,
])
@php
    $title = $title ?? brand_title(brand('tagline'));
    $description = $description ?? brand('tagline').'. Transparent microfinance for Tanzania.';
    $navProducts = $navProducts ?? collect();
    $siteLocale = $siteLocale ?? app()->getLocale();
    $siteCountry = $siteCountry ?? 'TZ';
    $siteCountries = $siteCountries ?? [];
    $currentCountry = collect($siteCountries)->firstWhere('code', $siteCountry) ?? ['code' => 'TZ', 'name' => 'Tanzania', 'emoji' => '🇹🇿'];
    $auth = (bool) $auth;
@endphp
<!DOCTYPE html>
<html lang="{{ $siteLocale }}" class="h-full scroll-smooth {{ $auth ? 'overflow-hidden' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Permissions-Policy" content="camera=(self), microphone=(), geolocation=(), notifications=(), push=()">
    <meta name="app-currency" content="{{ currency_code() }}">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body @class([
    'bg-white text-gray-900 antialiased flex flex-col',
    'h-[100svh] max-h-[100svh] overflow-hidden' => $auth,
    'min-h-full' => ! $auth,
])>

    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md shadow-sm border-b border-gray-100">
        <div class="hidden md:block border-b border-gray-100 bg-[#faf8f5]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-10 flex items-center justify-end gap-3">
                <x-site.locale-switcher variant="header" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 grid grid-cols-[minmax(0,1fr)_auto] lg:grid-cols-[auto_1fr_auto] items-center gap-2 sm:gap-4">
            <a href="{{ route('site.home') }}" class="flex items-center min-w-0 max-w-[min(100%,11.5rem)] sm:max-w-none shrink">
                <span class="lg:hidden min-w-0"><x-site.brand-mark size="md" /></span>
                <span class="hidden lg:inline-flex"><x-site.brand-mark size="lg" /></span>
            </a>

            <nav class="hidden lg:flex items-center justify-center gap-1 text-sm font-medium text-gray-700">
                <div class="relative" x-data="{ productsOpen: false }"
                     @mouseenter="productsOpen = true" @mouseleave="productsOpen = false"
                     @keydown.escape.window="productsOpen = false">
                    <button type="button" @click.stop="productsOpen = !productsOpen"
                            class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand inline-flex items-center gap-1 transition"
                            :class="productsOpen ? 'text-brand bg-brand-muted' : ''">
                        {{ __('site.nav.products') }}
                        <svg class="w-3.5 h-3.5 transition" :class="productsOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-cloak x-show="productsOpen" x-transition.opacity @click.outside="productsOpen = false"
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
                <a href="{{ route('site.plus') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">{{ __('site.nav.plus') }}</a>
                <a href="{{ route('site.rewards') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">{{ __('site.nav.rewards') }}</a>
                <a href="{{ route('site.card.verify') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">{{ __('site.nav.verify') }}</a>
                <a href="{{ route('site.affiliate') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">{{ __('site.nav.affiliate') }}</a>
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

            <div class="lg:hidden justify-self-end flex items-center gap-1.5 min-w-0">
                @guest
                    <a href="{{ route('site.login') }}"
                       class="text-xs font-semibold text-brand border border-brand/30 px-2.5 py-1.5 rounded-lg whitespace-nowrap">
                        {{ __('site.nav.log_in') }}
                    </a>
                    <a href="{{ route('site.register.borrower') }}"
                       class="inline-flex items-center rounded-lg bg-brand text-white text-xs font-semibold px-2.5 py-1.5 whitespace-nowrap">
                        {{ __('site.nav.register') }}
                    </a>
                @else
                    <a href="{{ Auth::user()->role === 'vendor' ? route('site.partner.dashboard') : (Auth::user()->role === 'investor' ? route('site.investor.dashboard') : route('site.borrower.dashboard')) }}"
                       class="text-xs font-semibold text-brand px-1.5 py-1.5 whitespace-nowrap max-w-[5.5rem] truncate">
                        {{ __('site.auth.welcome_back') }}
                    </a>
                @endguest
                <div class="relative" data-mobile-menu>
                    <button type="button" data-mobile-menu-toggle class="p-2 rounded-md hover:bg-gray-100" aria-label="Menu" aria-expanded="false">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div data-mobile-menu-panel hidden
                         class="absolute right-0 top-full mt-1 w-[min(100vw-2rem,20rem)] bg-white shadow-xl ring-1 ring-gray-200 max-h-[80vh] overflow-y-auto z-50 rounded-xl">
                        <div class="px-4 py-4 flex flex-col gap-1 text-sm">
                            <div class="px-2 py-2 border-b border-gray-100 mb-1">
                                <x-site.locale-switcher variant="mobile" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                            </div>
                            <a href="{{ route('site.products') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.all_products') }}</a>
                            <a href="{{ route('site.marketplace') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.marketplace') }}</a>
                            <a href="{{ route('site.plus') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.plus') }}</a>
                            <a href="{{ route('site.rewards') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.rewards') }}</a>
                            <a href="{{ route('site.card.verify') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.verify') }}</a>
                            <a href="{{ route('site.affiliate') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.nav.affiliate') }}</a>
                            <a href="{{ route('site.how-it-works') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">{{ __('site.how_it_works.title') }}</a>
                            @auth
                                <div class="border-t border-gray-200 pt-3 mt-2">
                    <form method="POST" action="{{ route('site.logout') }}">@csrf<button class="px-2 py-2 text-left text-gray-500 w-full hover:bg-gray-50 rounded-lg">{{ __('borrower.sign_out') }}</button></form>
                                </div>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main @class(['flex-1', 'min-h-0 overflow-y-auto overscroll-y-contain' => $auth])>{{ $slot }}</main>

    @unless ($auth)
        <footer class="bg-brand text-gray-300 mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <x-site.brand-mark variant="light" size="lg" :showSubtitle="true" />
                    <p class="text-sm text-gray-400 max-w-xs mt-3">{{ brand('tagline') }}. {{ __('site.footer.tagline') }}</p>
                    <p class="text-sm text-brand-gold/90 max-w-sm mt-3 font-medium leading-snug">
                        {{ __('site.footer.ownership', ['legal_name' => brand('legal_name')]) }}
                    </p>
                    <div class="mt-4 space-y-1 text-sm">
                        <p class="text-[11px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.footer.complaints_heading') }}</p>
                        @foreach (support_phones() as $phone)
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="block text-gray-300 hover:text-brand-gold transition">{{ $phone }}</a>
                        @endforeach
                        @foreach (support_emails() as $email)
                            <a href="mailto:{{ $email }}" class="block text-gray-300 hover:text-brand-gold transition">{{ $email }}</a>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">{{ __('site.nav.products') }}</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('site.products') }}" class="hover:text-brand-gold transition">{{ __('site.nav.all_products') }}</a></li>
                        <li><a href="{{ route('site.marketplace') }}" class="hover:text-brand-gold transition">{{ __('site.nav.marketplace') }}</a></li>
                        <li><a href="{{ route('site.plus') }}" class="hover:text-brand-gold transition">{{ __('site.nav.plus') }}</a></li>
                        <li><a href="{{ route('site.rewards') }}" class="hover:text-brand-gold transition">{{ __('site.nav.rewards') }}</a></li>
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
                        <li><a href="{{ route('site.partners') }}" class="hover:text-brand-gold transition">{{ __('site.footer.service_partners') }}</a></li>
                        <li><a href="{{ route('site.partners.apply', 'debt_collector') }}" class="hover:text-brand-gold transition">{{ __('site.footer.enroll_partner') }}</a></li>
                        <li><a href="{{ route('site.affiliate') }}" class="hover:text-brand-gold transition">{{ __('site.footer.become_affiliate') }}</a></li>
                        <li><a href="{{ route('site.card.verify') }}" class="hover:text-brand-gold transition">{{ __('site.footer.verify_card') }}</a></li>
                        <li><a href="{{ route('site.affiliate.verify.index') }}" class="hover:text-brand-gold transition">{{ __('site.footer.verify_affiliate') }}</a></li>
                        <li><a href="{{ route('site.login.partner') }}" class="hover:text-brand-gold transition">{{ __('site.auth.partner_portal') }}</a></li>
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
                    <span class="text-center sm:text-left">
                        &copy; {{ date('Y') }} {{ brand('legal_name') }}.
                        <span class="block sm:inline sm:ml-1 text-gray-400">{{ __('site.footer.ownership', ['legal_name' => brand('legal_name')]) }}</span>
                    </span>
                    <span class="flex items-center gap-4">
                        <a href="{{ route('site.legal.terms') }}" class="hover:text-brand-gold transition">{{ __('site.footer.terms') }}</a>
                        <a href="{{ route('site.legal.privacy') }}" class="hover:text-brand-gold transition">{{ __('site.footer.privacy') }}</a>
                        <a href="{{ route('site.legal') }}" class="hover:text-brand-gold transition">Legal</a>
                    </span>
                </div>
            </div>
        </footer>

        <x-site.chatbot-widget />
    @endunless
    <x-site.upload-busy-overlay />
    <x-site.confirm-modal name="default" />
    <x-site.feedback-modal name="default" />
    @stack('scripts')
    <script>
        (function () {
            const menu = document.querySelector('[data-mobile-menu]');
            if (!menu) return;

            const toggle = menu.querySelector('[data-mobile-menu-toggle]');
            const panel = menu.querySelector('[data-mobile-menu-panel]');
            if (!toggle || !panel) return;

            function setOpen(open) {
                panel.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                setOpen(panel.hidden);
            });

            document.addEventListener('click', function (event) {
                if (!menu.contains(event.target)) {
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    setOpen(false);
                }
            });
        })();
    </script>
    <script>
        document.addEventListener('alpine:init', () => {
            window.confirmForm = (form, detail = {}) => {
                const tone = detail.tone
                    || (String(detail.confirmClass || '').includes('red') ? 'warning' : 'confirm');
                window.dispatchEvent(new CustomEvent('open-confirm-default', {
                    detail: { form: form || null, tone, ...detail },
                }));
            };
            window.confirmAction = (detail = {}) => window.confirmForm(null, detail);

            window.showBorrowerFeedback = (detail = {}) => {
                window.dispatchEvent(new CustomEvent('open-feedback-default', {
                    detail: typeof detail === 'string' ? { message: detail } : detail,
                }));
            };
        });
        document.addEventListener('DOMContentLoaded', () => {
            @if (session('feedback'))
                @php $feedback = session('feedback'); @endphp
                window.showBorrowerFeedback({
                    tone: @js($feedback['tone'] ?? 'info'),
                    title: @js($feedback['title'] ?? brand_name()),
                    message: @js($feedback['message'] ?? ''),
                    lines: @js($feedback['lines'] ?? []),
                });
            @elseif (session('status'))
                window.showBorrowerFeedback({
                    tone: 'success',
                    title: @js(brand_name()),
                    message: @js(session('status')),
                    lines: [],
                });
            @elseif ($errors instanceof \Illuminate\Support\ViewErrorBag && $errors->any() && request()->routeIs('site.forgot-pin', 'site.forgot-pin.*', 'site.login', 'site.register*', 'site.borrower.setup-pin*'))
                window.showBorrowerFeedback({
                    tone: 'error',
                    title: @js(__('site.auth.pin_recovery.title')),
                    message: '',
                    lines: @js($errors->all()),
                });
            @endif
        });
    </script>
    @vite('resources/js/alpine-init.js')
</body>
</html>
