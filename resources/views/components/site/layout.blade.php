@props([
    'title' => 'Kopafasta — Capital that moves at your pace',
    'description' => 'Transparent microfinance for Tanzania. Ten products. One account. Disbursed in hours.',
])
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-white text-gray-900 antialiased flex flex-col">

    {{-- ===== TOP BAR ===== --}}
    <div class="hidden md:block bg-slate-900 text-white/80 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-9 flex items-center justify-between">
            <div class="flex items-center gap-5">
                <span class="inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-emerald-400"></span> BoT-licensed microfinance · Tier 2</span>
                <span class="hidden lg:inline-flex items-center gap-1.5 text-white/60">·</span>
                <span class="hidden lg:inline-flex items-center gap-1.5">12,400+ active members</span>
                <span class="hidden lg:inline-flex items-center gap-1.5 text-white/60">·</span>
                <span class="hidden lg:inline-flex items-center gap-1.5">TZS 14.2B disbursed</span>
            </div>
            <div class="flex items-center gap-5">
                <a href="tel:+255700000000" class="hover:text-amber-300">+255 700 000 000</a>
                <a href="mailto:hello@kopafasta.com" class="hover:text-amber-300">hello@kopafasta.com</a>
            </div>
        </div>
    </div>

    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-6"
             x-data="{ open: false, menu: null }" @keydown.escape.window="menu = null">
            <a href="{{ route('site.home') }}" class="flex items-center gap-2 shrink-0">
                <span class="size-9 rounded-lg bg-amber-500 grid place-items-center font-bold text-gray-900 text-lg">K</span>
                <span class="font-bold text-lg tracking-tight">kopafasta<span class="text-amber-600">.</span></span>
                <span class="hidden md:inline text-[10px] uppercase tracking-widest text-gray-500 ml-1">Microfinance</span>
            </a>

            <nav class="hidden md:flex items-center gap-1 text-sm font-medium text-gray-700">
                {{-- Borrow mega-menu --}}
                <div class="relative" @mouseleave="menu === 'borrow' && (menu = null)">
                    <button type="button" @click="menu = menu === 'borrow' ? null : 'borrow'" @mouseenter="menu = 'borrow'"
                            class="px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-amber-600 inline-flex items-center gap-1"
                            :class="menu === 'borrow' ? 'text-amber-700 bg-amber-50' : ''">
                        Borrow
                        <svg class="w-3.5 h-3.5 transition" :class="menu === 'borrow' ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-cloak x-show="menu === 'borrow'" x-transition.opacity
                         class="absolute left-0 top-full mt-2 w-[640px] rounded-2xl border border-gray-200 bg-white shadow-2xl p-6 grid grid-cols-2 gap-5">
                        <a href="{{ route('site.product', 'IL') }}" class="group p-4 rounded-xl hover:bg-amber-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-amber-100 text-amber-700">👤</span><span class="font-semibold text-gray-900 group-hover:text-amber-700">Individual loan</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Personal loans from TZS 50K to 10M. Repay in 1–24 months.</p>
                        </a>
                        <a href="{{ route('site.product', 'GL') }}" class="group p-4 rounded-xl hover:bg-amber-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-amber-100 text-amber-700">👥</span><span class="font-semibold text-gray-900 group-hover:text-amber-700">Group loan</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Joint-liability lending for chamas and savings circles.</p>
                        </a>
                        <a href="{{ route('site.product', 'AB') }}" class="group p-4 rounded-xl hover:bg-amber-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-amber-100 text-amber-700">🚗</span><span class="font-semibold text-gray-900 group-hover:text-amber-700">Asset-backed</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Cars, motorcycles & equipment as collateral. Up to TZS 50M.</p>
                        </a>
                        <a href="{{ route('site.product', 'EM') }}" class="group p-4 rounded-xl hover:bg-amber-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-amber-100 text-amber-700">⚡</span><span class="font-semibold text-gray-900 group-hover:text-amber-700">Emergency loan</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Same-hour disbursement for urgent needs. Up to 14 days.</p>
                        </a>
                        <div class="col-span-2 border-t border-gray-100 pt-4 flex items-center justify-between text-sm">
                            <a href="{{ route('site.products') }}" class="font-semibold text-amber-700 hover:underline">All ten loan products →</a>
                            <a href="{{ route('site.apply.show') }}" class="rounded-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-1.5">Apply now</a>
                        </div>
                    </div>
                </div>

                {{-- Invest mega-menu --}}
                <div class="relative" @mouseleave="menu === 'invest' && (menu = null)">
                    <button type="button" @click="menu = menu === 'invest' ? null : 'invest'" @mouseenter="menu = 'invest'"
                            class="px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-emerald-700 inline-flex items-center gap-1"
                            :class="menu === 'invest' ? 'text-emerald-700 bg-emerald-50' : ''">
                        Invest
                        <svg class="w-3.5 h-3.5 transition" :class="menu === 'invest' ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-cloak x-show="menu === 'invest'" x-transition.opacity
                         class="absolute left-0 top-full mt-2 w-[640px] rounded-2xl border border-gray-200 bg-white shadow-2xl p-6 grid grid-cols-2 gap-5">
                        <a href="{{ route('site.invest') }}" class="group p-4 rounded-xl hover:bg-emerald-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-emerald-100 text-emerald-700">📈</span><span class="font-semibold text-gray-900 group-hover:text-emerald-700">Individual investor</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Start from TZS 50,000. Earn 12–24% per year from vetted loan pools.</p>
                        </a>
                        <a href="{{ route('site.capital-partners') }}" class="group p-4 rounded-xl hover:bg-indigo-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-indigo-100 text-indigo-700">🏛️</span><span class="font-semibold text-gray-900 group-hover:text-indigo-700">Capital partner</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">For banks, MFIs, DFIs &amp; family offices deploying $50K+.</p>
                        </a>
                        <a href="{{ route('site.invest') }}#pools" class="group p-4 rounded-xl hover:bg-emerald-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-emerald-100 text-emerald-700">🧺</span><span class="font-semibold text-gray-900 group-hover:text-emerald-700">Pool marketplace</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Salary, business, car &amp; emergency pools — risk-graded.</p>
                        </a>
                        <a href="{{ route('site.invest') }}#performance" class="group p-4 rounded-xl hover:bg-emerald-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-emerald-100 text-emerald-700">📊</span><span class="font-semibold text-gray-900 group-hover:text-emerald-700">Portfolio performance</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">12-month track record. 96.3% on-time repayment.</p>
                        </a>
                        <div class="col-span-2 border-t border-gray-100 pt-4 flex items-center justify-between text-sm">
                            <a href="{{ route('site.invest') }}" class="font-semibold text-emerald-700 hover:underline">Learn more about investing →</a>
                            <a href="{{ route('site.register.investor') }}" class="rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-1.5">Open investor account</a>
                        </div>
                    </div>
                </div>

                {{-- Partners --}}
                <div class="relative" @mouseleave="menu === 'partners' && (menu = null)">
                    <button type="button" @click="menu = menu === 'partners' ? null : 'partners'" @mouseenter="menu = 'partners'"
                            class="px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-900 inline-flex items-center gap-1"
                            :class="menu === 'partners' ? 'text-gray-900 bg-gray-100' : ''">
                        Partners
                        <svg class="w-3.5 h-3.5 transition" :class="menu === 'partners' ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-cloak x-show="menu === 'partners'" x-transition.opacity
                         class="absolute left-0 top-full mt-2 w-[480px] rounded-2xl border border-gray-200 bg-white shadow-2xl p-6 grid gap-3">
                        <a href="{{ route('site.register.vendor') }}" class="group p-4 rounded-xl hover:bg-gray-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-gray-900 text-amber-400">🛠️</span><span class="font-semibold text-gray-900">GPS installers, valuers &amp; insurers</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Get jobs across the country with fast settlement.</p>
                        </a>
                        <a href="{{ route('site.register.vendor') }}" class="group p-4 rounded-xl hover:bg-gray-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-gray-900 text-amber-400">🏭</span><span class="font-semibold text-gray-900">Yard &amp; collection partners</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Help us recover and remarket repossessed assets.</p>
                        </a>
                        <a href="{{ route('site.register.vendor') }}" class="group p-4 rounded-xl hover:bg-gray-50 transition">
                            <div class="flex items-center gap-3"><span class="size-9 grid place-items-center rounded-lg bg-gray-900 text-amber-400">🤝</span><span class="font-semibold text-gray-900">Channel &amp; agent banking</span></div>
                            <p class="mt-1.5 text-xs text-gray-600">Distribute Kopafasta loans through your branch network.</p>
                        </a>
                    </div>
                </div>

                {{-- Company --}}
                <div class="relative" @mouseleave="menu === 'company' && (menu = null)">
                    <button type="button" @click="menu = menu === 'company' ? null : 'company'" @mouseenter="menu = 'company'"
                            class="px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-900 inline-flex items-center gap-1"
                            :class="menu === 'company' ? 'text-gray-900 bg-gray-100' : ''">
                        Company
                        <svg class="w-3.5 h-3.5 transition" :class="menu === 'company' ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-cloak x-show="menu === 'company'" x-transition.opacity
                         class="absolute left-0 top-full mt-2 w-56 rounded-2xl border border-gray-200 bg-white shadow-2xl p-3">
                        <a href="{{ route('site.about') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">About Kopafasta</a>
                        <a href="{{ route('site.how-it-works') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">How it works</a>
                        <a href="{{ route('site.faq') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">FAQ &amp; help center</a>
                        <a href="mailto:hello@kopafasta.com" class="block px-3 py-2 rounded-lg hover:bg-gray-50 text-sm">Contact us</a>
                    </div>
                </div>
            </nav>

            <div class="hidden md:flex items-center gap-3">
                @auth
                    <a href="{{ Auth::user()->role === 'vendor' ? route('site.vendor.dashboard') : (Auth::user()->role === 'investor' ? route('site.investor.dashboard') : route('site.borrower.dashboard')) }}"
                       class="text-sm font-medium text-gray-700 hover:text-amber-600">My account</a>
                    <form method="POST" action="{{ route('site.logout') }}">@csrf
                        <button class="text-sm text-gray-500 hover:text-gray-900">Log out</button>
                    </form>
                @else
                    <a href="{{ route('site.login') }}" class="text-sm font-medium text-gray-700 hover:text-amber-600">Log in</a>
                    <a href="{{ route('site.register') }}"
                       class="inline-flex items-center gap-2 rounded-full bg-amber-500 hover:bg-amber-400 text-gray-900 text-sm font-semibold px-4 py-2 shadow-sm transition">
                        Get started
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                    </a>
                @endauth
            </div>

            {{-- mobile toggle --}}
            <button @click="open = !open" class="md:hidden p-2 rounded-md hover:bg-gray-100" aria-label="Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            {{-- mobile drawer --}}
            <div x-cloak x-show="open" @click.away="open=false"
                 class="md:hidden absolute top-16 inset-x-0 bg-white border-b border-gray-200 shadow-xl max-h-[80vh] overflow-y-auto">
                <div class="px-4 py-4 flex flex-col gap-1 text-sm" x-data="{ tab: null }">
                    <p class="text-[11px] uppercase tracking-widest text-amber-600 font-bold px-2 mt-1">Borrow</p>
                    <a href="{{ route('site.products') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">All loan products</a>
                    <a href="{{ route('site.product', 'IL') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Individual loan</a>
                    <a href="{{ route('site.product', 'GL') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Group loan</a>
                    <a href="{{ route('site.product', 'AB') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Asset-backed</a>
                    <a href="{{ route('site.product', 'EM') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Emergency loan</a>

                    <p class="text-[11px] uppercase tracking-widest text-emerald-700 font-bold px-2 mt-4">Invest</p>
                    <a href="{{ route('site.invest') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Individual investor</a>
                    <a href="{{ route('site.capital-partners') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Capital partner</a>

                    <p class="text-[11px] uppercase tracking-widest text-gray-600 font-bold px-2 mt-4">Partners</p>
                    <a href="{{ route('site.register.vendor') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">Become a vendor</a>

                    <p class="text-[11px] uppercase tracking-widest text-gray-600 font-bold px-2 mt-4">Company</p>
                    <a href="{{ route('site.about') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">About</a>
                    <a href="{{ route('site.how-it-works') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">How it works</a>
                    <a href="{{ route('site.faq') }}" class="px-2 py-2 hover:bg-gray-50 rounded-lg">FAQ</a>

                    <div class="border-t border-gray-200 pt-3 mt-4 flex flex-col gap-2">
                        @auth
                            <a href="{{ Auth::user()->role === 'vendor' ? route('site.vendor.dashboard') : (Auth::user()->role === 'investor' ? route('site.investor.dashboard') : route('site.borrower.dashboard')) }}" class="py-1.5">My account</a>
                            <form method="POST" action="{{ route('site.logout') }}">@csrf
                                <button class="py-1.5 text-left text-gray-500 w-full">Log out</button>
                            </form>
                        @else
                            <a href="{{ route('site.login') }}" class="py-1.5">Log in</a>
                            <a href="{{ route('site.register') }}"
                               class="inline-flex justify-center rounded-full bg-amber-500 text-gray-900 font-semibold px-4 py-2 mt-1">Get started</a>
                        @endauth
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
    <footer class="bg-gray-900 text-gray-300 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <div class="flex items-center gap-2 mb-3">
                    <span class="size-9 rounded-lg bg-amber-500 grid place-items-center font-bold text-gray-900">K</span>
                    <span class="font-bold text-white text-lg">kopafasta<span class="text-amber-500">.</span></span>
                </div>
                <p class="text-sm text-gray-400 max-w-xs">Capital that moves at your pace. Tanzania's mobile-first microfinance — open to borrowers, investors and institutional partners.</p>
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
                    <li><a href="{{ route('site.register.vendor') }}" class="hover:text-amber-400">Become a vendor</a></li>
                    <li><a href="{{ route('site.login') }}" class="hover:text-amber-400">Vendor login</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs uppercase tracking-widest text-gray-400 mb-3">Company</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('site.about') }}" class="hover:text-amber-400">About</a></li>
                    <li><a href="{{ route('site.how-it-works') }}" class="hover:text-amber-400">How it works</a></li>
                    <li><a href="{{ route('site.faq') }}" class="hover:text-amber-400">FAQ</a></li>
                    <li><a href="mailto:hello@kopafasta.com" class="hover:text-amber-400">Contact</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 py-5 text-xs text-gray-500">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
                <span>&copy; {{ date('Y') }} Kopafasta Microfinance Ltd. Licensed by the Bank of Tanzania.</span>
                <span class="flex items-center gap-4">
                    <a href="{{ route('site.faq') }}" class="hover:text-amber-400">Terms</a>
                    <a href="{{ route('site.faq') }}" class="hover:text-amber-400">Privacy</a>
                    <a href="{{ route('site.faq') }}" class="hover:text-amber-400">Risk disclosure</a>
                </span>
            </div>
        </div>
    </footer>

    {{-- Alpine for nav + wizard --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
