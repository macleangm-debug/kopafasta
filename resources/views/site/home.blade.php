<x-site.layout title="{{ brand_name() }} — Capital that moves at your pace">

    @php
        $productIcons = [
            'business' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 .414-.336.75-.75.75h-4.5a.75.75 0 01-.75-.75v-4.25m0 0h4.125c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9m9 9H9.375c-.621 0-1.125.504-1.125 1.125v3.375m0 0h4.125c.621 0 1.125.504 1.125 1.125v4.125c0 .621-.504 1.125-1.125 1.125H9.375a1.125 1.125 0 01-1.125-1.125v-4.125c0-.621.504-1.125 1.125-1.125z"/></svg>',
            'individual' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>',
            'asset' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>',
            'group' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>',
            'default' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/></svg>',
        ];
        $featuredProducts = $products->take(4);
    @endphp

    {{-- ===== HERO ===== --}}
    <section class="relative overflow-hidden bg-[#faf8f5]">
        <div class="absolute inset-0 bg-cover bg-center opacity-[0.12] blur-[2px]"
             style="background-image: url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1600&q=80');"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-[#faf8f5] via-[#faf8f5]/95 to-transparent"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20">
            <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white border border-gray-200 px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-gray-600 shadow-sm">
                        <svg class="w-3.5 h-3.5 text-brand" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 01.894.553l1.618 3.28 3.622.526a1 1 0 01.554 1.705l-2.62 2.554.618 3.607a1 1 0 01-1.451 1.054L10 13.347l-3.235 1.702a1 1 0 01-1.451-1.054l.618-3.607-2.62-2.554a1 1 0 01.554-1.705l3.622-.526L9.106 2.553A1 1 0 0110 2z"/></svg>
                        Microfinance solutions
                    </span>
                    <h1 class="mt-5 text-4xl sm:text-5xl lg:text-[3.25rem] font-bold tracking-tight leading-[1.1] text-brand">
                        Capital that moves at your pace.
                    </h1>
                    <p class="mt-5 text-base sm:text-lg text-gray-600 max-w-lg leading-relaxed">
                        Flexible loans designed to help you grow your business, manage your expenses, and build a better tomorrow.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('site.register.borrower') }}"
                           class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-lg shadow-md transition">
                            Get Started
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                        </a>
                        <a href="{{ route('site.how-it-works') }}"
                           class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-brand font-semibold px-6 py-3 rounded-lg border border-brand/20 transition">
                            Learn More
                        </a>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-gray-600">
                        <span class="inline-flex items-center gap-2">
                            <span class="size-8 rounded-full bg-brand-muted text-brand grid place-items-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                            </span>
                            Quick approval
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="size-8 rounded-full bg-brand-muted text-brand grid place-items-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                            </span>
                            Flexible repayment
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="size-8 rounded-full bg-brand-muted text-brand grid place-items-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                            </span>
                            Trusted by thousands
                        </span>
                    </div>
                </div>

                <div class="relative lg:min-h-[32rem]">
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl aspect-[4/5] lg:aspect-auto lg:h-[32rem] bg-brand-muted">
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=800&q=80"
                             alt="Small business owner"
                             class="absolute inset-0 w-full h-full object-cover object-top">
                        <div class="absolute inset-0 bg-gradient-to-t from-brand/20 to-transparent"></div>
                    </div>

                    @guest
                    <div class="absolute -left-4 sm:left-4 top-8 sm:top-12 w-[min(100%,20rem)] bg-white rounded-2xl shadow-2xl border border-gray-100 p-6 z-10"
                         x-data="{ tab: 'login' }">
                        <p class="text-lg font-bold text-gray-900">Welcome back 👋</p>
                        <p class="text-xs text-gray-500 mt-0.5">Sign in to manage your loans</p>

                        <div class="mt-4 inline-flex rounded-lg ring-1 ring-gray-200 bg-gray-50 p-1 text-sm w-full">
                            <button type="button" @click="tab = 'login'"
                                    :class="tab === 'login' ? 'bg-white text-brand shadow-sm font-semibold' : 'text-gray-500'"
                                    class="flex-1 rounded-md py-2 transition">Login</button>
                            <button type="button" @click="tab = 'register'"
                                    :class="tab === 'register' ? 'bg-white text-brand shadow-sm font-semibold' : 'text-gray-500'"
                                    class="flex-1 rounded-md py-2 transition">Register</button>
                        </div>

                        <div x-show="tab === 'login'" x-cloak class="mt-4 space-y-3">
                            <form method="POST" action="{{ route('site.login.post') }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="auth_method" value="pin">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Mobile number</label>
                                    <input type="tel" name="phone" autocomplete="tel"
                                           class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-1 focus:ring-brand outline-none"
                                           placeholder="0712 345 678">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">PIN</label>
                                    <input type="password" name="pin" inputmode="numeric" maxlength="4"
                                           class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-1 focus:ring-brand outline-none"
                                           placeholder="••••">
                                </div>
                                <button class="w-full bg-brand hover:bg-brand-light text-white font-semibold py-2.5 rounded-lg transition text-sm">
                                    Log In
                                </button>
                            </form>
                        </div>

                        <div x-show="tab === 'register'" x-cloak class="mt-4 space-y-3">
                            <p class="text-sm text-gray-600">Create your account in minutes — phone and PIN is all you need.</p>
                            <a href="{{ route('site.register.borrower') }}"
                               class="block w-full text-center bg-brand hover:bg-brand-light text-white font-semibold py-2.5 rounded-lg transition text-sm">
                                Register now
                            </a>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-[11px] text-center text-gray-400 mb-2">Or continue with</p>
                            <div class="flex gap-2">
                                <button type="button" disabled class="flex-1 flex items-center justify-center gap-2 rounded-lg border border-gray-200 py-2 text-xs text-gray-400 cursor-not-allowed">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                    Google
                                </button>
                                <button type="button" disabled class="flex-1 flex items-center justify-center gap-2 rounded-lg border border-gray-200 py-2 text-xs text-gray-400 cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98 1.04 7.22-.87 2.12-2.02 4.22-4.09 4.19zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
                                    Apple
                                </button>
                            </div>
                        </div>
                    </div>
                    @endguest
                </div>
            </div>
        </div>
    </section>

    {{-- ===== PRODUCTS ===== --}}
    <section class="bg-white py-16 lg:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">Our products</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">Financial solutions for every need</h2>
            <p class="mt-3 text-gray-600 max-w-2xl mx-auto">
                From your first individual loan to asset-backed financing — transparent pricing, disbursed in hours.
            </p>

            <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-5 text-left">
                @foreach ($featuredProducts as $product)
                    @php
                        $category = strtolower((string) ($product->category ?? 'default'));
                        $iconKey = match (true) {
                            str_contains($category, 'business') => 'business',
                            str_contains($category, 'individual') || str_contains($category, 'education') || str_contains($category, 'emergency') => 'individual',
                            str_contains($category, 'asset') || str_contains($category, 'agriculture') => 'asset',
                            str_contains($category, 'group') => 'group',
                            default => 'default',
                        };
                    @endphp
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:shadow-md hover:border-brand/30 transition">
                        <div class="size-11 rounded-full bg-brand-muted text-brand grid place-items-center mb-4">
                            {!! $productIcons[$iconKey] ?? $productIcons['default'] !!}
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $product->name }}</h3>
                        <p class="mt-2 text-sm text-gray-600 line-clamp-3 leading-relaxed">{{ $product->description }}</p>
                        <span class="inline-block mt-4 rounded-md bg-brand-gold/20 text-brand px-3 py-1 text-xs font-semibold">
                            Up to TZS {{ format_number($product->max_amount) }}
                        </span>
                        <a href="{{ route('site.product', $product->code) }}"
                           class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand hover:gap-2 transition-all">
                            Learn More
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="mt-10">
                <a href="{{ route('site.products') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-brand/30 text-brand hover:bg-brand-muted font-semibold px-6 py-3 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                    View All Products
                </a>
            </div>
        </div>
    </section>

    {{-- ===== STATISTICS ===== --}}
    <section class="bg-brand text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 lg:grid-cols-4 gap-8">
            @foreach ([
                ['icon' => 'users', 'value' => '12,400+', 'label' => 'Happy customers'],
                ['icon' => 'money', 'value' => 'TZS 14.2B+', 'label' => 'Loans disbursed'],
                ['icon' => 'check', 'value' => '96%', 'label' => 'Approval rate'],
                ['icon' => 'clock', 'value' => '24hrs', 'label' => 'Quick disbursement'],
            ] as $stat)
                <div class="text-center lg:text-left flex flex-col items-center lg:items-start gap-2">
                    <span class="size-10 rounded-full bg-brand-gold/20 text-brand-gold grid place-items-center">
                        @if ($stat['icon'] === 'users')
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        @elseif ($stat['icon'] === 'money')
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.745.39 1.51.617 2.298.622h.003c1.014 0 1.964-.258 2.787-.712M2.25 18.75V12a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0120.25 12v6.75"/></svg>
                        @elseif ($stat['icon'] === 'check')
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </span>
                    <div class="text-2xl sm:text-3xl font-bold">{{ $stat['value'] }}</div>
                    <div class="text-sm text-white/70">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ===== AFFILIATE ===== --}}
    <section id="affiliate" class="bg-[#faf8f5] py-16 lg:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-start">
                <div>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-brand">Earn more. Empower more.</h2>
                    <p class="mt-4 text-gray-600 max-w-lg leading-relaxed">
                        Join our affiliate programme and earn commissions by referring customers to {{ brand_name() }}. Help your community access flexible finance while building your own income stream.
                    </p>
                    <ul class="mt-6 space-y-3">
                        @foreach (['Easy registration', 'Real-time tracking', 'Weekly payouts', 'Marketing support'] as $item)
                            <li class="flex items-center gap-3 text-sm text-gray-700">
                                <span class="size-6 rounded-full bg-brand text-white grid place-items-center shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                </span>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('site.affiliate.apply') }}"
                       class="mt-8 inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-lg transition">
                        Become an Affiliate
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 5v10m-5-5h10"/></svg>
                    </a>

                    <div class="relative mt-10 max-w-sm">
                        <div class="rounded-2xl overflow-hidden shadow-lg aspect-[4/3] bg-brand-muted">
                            <img src="https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=600&q=80"
                                 alt="Affiliate checking earnings on phone"
                                 class="w-full h-full object-cover">
                        </div>
                        <div class="absolute -bottom-4 -right-2 sm:right-4 bg-white rounded-xl shadow-xl border border-gray-100 p-4 w-48">
                            <p class="text-[10px] uppercase tracking-wider text-gray-500">Your earnings</p>
                            <p class="text-lg font-bold text-brand mt-0.5">TZS 2,450,000</p>
                            <div class="mt-2 h-8 flex items-end gap-0.5">
                                @foreach ([40, 55, 45, 70, 60, 85, 75] as $h)
                                    <span class="flex-1 rounded-sm bg-brand/80" style="height: {{ $h }}%"></span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-brand rounded-2xl shadow-2xl p-6 sm:p-8 text-white">
                    <h3 class="text-xl font-bold">Affiliate Registration</h3>
                    <p class="text-sm text-white/70 mt-1">Start earning by referring customers today.</p>

                    @if (session('status'))
                        <div class="mt-4 rounded-lg bg-white/10 border border-white/20 px-4 py-3 text-sm">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('site.affiliate.apply.post') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-white/80 mb-1">Full name</label>
                            <input name="full_name" value="{{ old('full_name') }}" required
                                   class="w-full rounded-lg bg-white text-gray-900 border-0 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-gold">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-white/80 mb-1">Mobile number</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required
                                   class="w-full rounded-lg bg-white text-gray-900 border-0 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-gold"
                                   placeholder="0712 345 678">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-white/80 mb-1">Email address</label>
                            <input type="email" name="email" value="{{ old('email') }}" required
                                   class="w-full rounded-lg bg-white text-gray-900 border-0 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-gold">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-white/80 mb-1">Business name <span class="text-white/50">(optional)</span></label>
                            <input name="business_name" value="{{ old('business_name') }}"
                                   class="w-full rounded-lg bg-white text-gray-900 border-0 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-gold">
                        </div>
                        <button class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold py-3 rounded-lg transition text-sm">
                            Register Now
                        </button>
                        <p class="text-center text-xs text-white/60">
                            Already have an account?
                            <a href="{{ route('site.login') }}" class="text-brand-gold font-semibold hover:underline">Log In</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    @if (! empty($featuredAssets))
    {{-- ===== ASSET MARKETPLACE ===== --}}
    <section class="bg-white border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">Asset marketplace</p>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Finance vehicles, equipment &amp; more.</h2>
                    <p class="mt-3 text-gray-600 max-w-2xl">Browse supplier-listed assets with transparent deposits and weekly instalments.</p>
                </div>
                <a href="{{ route('site.marketplace') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:text-brand-light">
                    View all assets →
                </a>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($featuredAssets as $asset)
                    @include('site.marketplace._asset-card', [
                        'asset' => $asset,
                        'categories' => $marketplaceCategories,
                        'showUrl' => route('site.marketplace.show', $asset['id']),
                        'authenticated' => false,
                    ])
                @endforeach
            </div>
        </div>
    </section>
    @endif

</x-site.layout>
