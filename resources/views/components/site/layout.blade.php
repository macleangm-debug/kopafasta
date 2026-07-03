@props([
    'title' => null,
    'description' => null,
])
@php
    $title = $title ?? brand_title(brand('tagline'));
    $description = $description ?? brand('tagline').'. Transparent microfinance for Tanzania.';
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
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

    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 grid grid-cols-[auto_1fr_auto] items-center gap-4"
             x-data="{ open: false, menu: null }"
             @keydown.escape.window="menu = null; open = false"
             @click.outside="menu = null">
            <a href="{{ route('site.home') }}" class="flex items-center gap-2 shrink-0">
                <x-site.brand-mark size="md" />
            </a>

            <nav class="hidden lg:flex items-center justify-center gap-1 text-sm font-medium text-gray-700">
                <a href="{{ route('site.home') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">Home</a>

                <div class="relative" @mouseenter="menu = 'products'" @mouseleave="menu = null">
                    <button type="button" @click.stop="menu = menu === 'products' ? null : 'products'"
                            class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand inline-flex items-center gap-1 transition"
                            :class="menu === 'products' ? 'text-brand bg-brand-muted' : ''">
                        Products
                        <svg class="w-3.5 h-3.5 transition" :class="menu === 'products' ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-cloak x-show="menu === 'products'" x-transition.opacity
                         class="absolute left-1/2 -translate-x-1/2 top-full pt-2 w-72">
                        <div class="rounded-xl border border-gray-200 bg-white shadow-xl p-2">
                            <a href="{{ route('site.products') }}" class="block px-3 py-2 rounded-lg hover:bg-brand-muted text-sm font-semibold text-brand">All loan products</a>
                            <a href="{{ route('site.product', 'IL') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">Individual loan</a>
                            <a href="{{ route('site.product', 'BP') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">Business loan</a>
                            <a href="{{ route('site.product', 'AB') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">Asset-backed loan</a>
                            <a href="{{ route('site.product', 'EM') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">Emergency loan</a>
                        </div>
                    </div>
                </div>

                <a href="{{ route('site.how-it-works') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">How It Works</a>
                <a href="{{ route('site.about') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">About Us</a>
                <a href="mailto:{{ brand('support_email') }}" class="px-3 py-2 rounded-lg hover:bg-brand-muted hover:text-brand transition">Contact</a>
            </nav>

            <div class="hidden lg:flex items-center justify-end gap-3">
                @auth
                    <a href="{{ Auth::user()->role === 'vendor' ? route('site.partner.dashboard') : (Auth::user()->role === 'investor' ? route('site.investor.dashboard') : route('site.borrower.dashboard')) }}"
                       class="text-sm font-medium text-gray-700 hover:text-brand">My account</a>
                    <form method="POST" action="{{ route('site.logout') }}">@csrf
                        <button class="text-sm text-gray-500 hover:text-gray-900">Log out</button>
                    </form>
                @else
                    <a href="{{ route('site.login') }}" class="text-sm font-semibold text-brand border border-brand/30 hover:border-brand px-4 py-2 rounded-lg transition">Log In</a>
                    <a href="{{ route('site.register') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-2 shadow-sm transition">
                        Register
                    </a>
                @endauth
            </div>

            {{-- mobile menu --}}
            <div class="lg:hidden justify-self-end relative" @click.outside="open = false">
                <button @click.stop="open = !open" class="p-2 rounded-md hover:bg-gray-100" aria-label="Menu" :aria-expanded="open">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div x-cloak x-show="open"
                     class="absolute right-0 top-full mt-1 w-[min(100vw-2rem,20rem)] rounded-2xl border border-gray-200 bg-white shadow-xl max-h-[80vh] overflow-y-auto z-50">
                    <div class="px-4 py-4 flex flex-col gap-1 text-sm">
                        <a href="{{ route('site.home') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Home</a>
                        <a href="{{ route('site.products') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Products</a>
                        <a href="{{ route('site.how-it-works') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">How It Works</a>
                        <a href="{{ route('site.about') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">About Us</a>
                        <a href="mailto:{{ brand('support_email') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Contact</a>
                        <a href="{{ route('site.invest') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Invest</a>
                        <a href="{{ route('site.affiliate.apply') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Become an affiliate</a>

                        <div class="border-t border-gray-200 pt-3 mt-4 flex flex-col gap-2">
                            @auth
                                <a href="{{ Auth::user()->role === 'vendor' ? route('site.partner.dashboard') : (Auth::user()->role === 'investor' ? route('site.investor.dashboard') : route('site.borrower.dashboard')) }}" class="py-1.5">My account</a>
                                <form method="POST" action="{{ route('site.logout') }}">@csrf
                                    <button class="py-1.5 text-left text-gray-500 w-full">Log out</button>
                                </form>
                            @else
                                <a href="{{ route('site.login') }}" class="py-1.5">Log In</a>
                                <a href="{{ route('site.register') }}"
                                   class="inline-flex justify-center rounded-lg bg-brand text-white font-semibold px-4 py-2 mt-1">Register</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- ===== STATUS / ERRORS GLOBAL ===== --}}
    @if (session('status'))
        <div class="bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-sm py-2">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">{{ session('status') }}</div>
        </div>
    @endif

    {{-- ===== CONTENT ===== --}}
    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- ===== FOOTER ===== --}}
    <footer class="bg-brand text-gray-300 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <div class="mb-3">
                    <x-site.brand-mark variant="light" size="md" :showSubtitle="true" />
                </div>
                <p class="text-sm text-gray-400 max-w-xs">{{ brand('tagline') }}. Tanzania's mobile-first microfinance — open to borrowers, investors and institutional partners.</p>
                <div class="mt-4 flex items-center gap-2 text-[11px] text-gray-500">
                    <span class="px-2 py-0.5 rounded bg-white/5 border border-white/10">BoT Tier 2</span>
                    <span class="px-2 py-0.5 rounded bg-white/5 border border-white/10">ISO 27001</span>
                    <span class="px-2 py-0.5 rounded bg-white/5 border border-white/10">PCI-DSS L1</span>
                </div>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">Borrow</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.product', 'IL') }}" class="hover:text-amber-400">Individual Loan</a></li>
                    <li><a href="{{ route('site.product', 'GL') }}" class="hover:text-amber-400">Group Loan</a></li>
                    <li><a href="{{ route('site.product', 'AB') }}" class="hover:text-amber-400">Asset Backed</a></li>
                    <li><a href="{{ route('site.product', 'EM') }}" class="hover:text-amber-400">Emergency</a></li>
                    <li><a href="{{ route('site.products') }}" class="hover:text-amber-400">All products</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">Invest</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.invest') }}" class="hover:text-emerald-400">Individual investor</a></li>
                    <li><a href="{{ route('site.capital-partners') }}" class="hover:text-emerald-400">Capital partner</a></li>
                    <li><a href="{{ route('site.register.investor') }}" class="hover:text-emerald-400">Open an account</a></li>
                    <li><a href="{{ route('site.register.capital') }}" class="hover:text-emerald-400">Institutional sign-up</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">Partners</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.register.partner') }}" class="hover:text-amber-400">Become a partner</a></li>
                    <li><a href="{{ route('site.affiliate.apply') }}" class="hover:text-amber-400">Become an affiliate</a></li>
                    <li><a href="{{ route('site.login') }}" class="hover:text-amber-400">Partner login</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">Company</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.about') }}" class="hover:text-amber-400">About</a></li>
                    <li><a href="{{ route('site.how-it-works') }}" class="hover:text-amber-400">How it works</a></li>
                    <li><a href="{{ route('site.faq') }}" class="hover:text-amber-400">FAQ</a></li>
                    <li><a href="mailto:{{ brand('support_email') }}" class="hover:text-amber-400">Contact</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 py-5 text-xs text-gray-500">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
                <span>&copy; {{ date('Y') }} {{ brand('legal_name') }}. Licensed by the Bank of Tanzania.</span>
                <span class="flex items-center gap-4">
                    <a href="{{ route('site.faq') }}" class="hover:text-amber-400">Terms</a>
                    <a href="{{ route('site.faq') }}" class="hover:text-amber-400">Privacy</a>
                    <a href="{{ route('site.faq') }}" class="hover:text-amber-400">Risk disclosure</a>
                </span>
            </div>
        </div>
    </footer>

    {{-- Alpine for nav + wizard --}}
    <x-site.confirm-modal name="default" />
    <script>
        document.addEventListener('alpine:init', () => {
            window.confirmForm = (form, detail = {}) => {
                window.dispatchEvent(new CustomEvent('open-confirm-default', {
                    detail: { form, ...detail },
                }));
            };
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
