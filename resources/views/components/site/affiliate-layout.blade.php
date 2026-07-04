@props(['title' => null, 'active' => 'dashboard'])

@php
    $pageTitle = $title ?? brand_title(__('site.affiliate_portal.title'));
    $nav = [
        ['key' => 'dashboard', 'label' => __('site.affiliate_portal.nav_dashboard'), 'route' => 'site.affiliate.dashboard', 'icon' => 'home'],
        ['key' => 'referrals', 'label' => __('site.affiliate_portal.nav_referrals'), 'route' => 'site.affiliate.referrals', 'icon' => 'users'],
        ['key' => 'wallet',    'label' => __('site.affiliate_portal.nav_wallet'),    'route' => 'site.affiliate.wallet',    'icon' => 'wallet'],
        ['key' => 'profile',   'label' => __('site.affiliate_portal.nav_profile'),   'route' => 'site.affiliate.profile',   'icon' => 'user'],
    ];

    $icon = function (string $name) {
        return match ($name) {
            'home'   => '<path d="M3 12 12 4l9 8M5 10v10h14V10"/>',
            'users'  => '<path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM3 21a7 7 0 0 1 14 0M22 11a3 3 0 1 0-3-3"/>',
            'wallet' => '<path d="M3 7h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm0 0V5a2 2 0 0 1 2-2h11M16 13h2"/>',
            'user'   => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/>',
            default  => '<circle cx="12" cy="12" r="8"/>',
        };
    };
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-[#faf8f5] text-gray-900 antialiased" x-data="{open:false}">

<div class="min-h-screen flex">
    <aside class="hidden lg:flex w-64 shrink-0 flex-col bg-brand text-white sticky top-0 h-screen shadow-xl">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <a href="{{ route('site.home') }}" class="relative flex items-center gap-2 px-5 h-16 border-b border-white/15">
            <span class="size-9 grid place-items-center rounded-lg bg-brand-gold text-brand font-extrabold shadow">K</span>
            <div class="leading-tight">
                <div class="font-extrabold tracking-tight text-sm">{{ brand_name() }}</div>
                <div class="text-[11px] text-white/70">{{ __('site.affiliate_portal.title') }}</div>
            </div>
        </a>
        <nav class="relative flex-1 overflow-y-auto py-4">
            @foreach ($nav as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 mx-3 my-0.5 px-3 py-2.5 text-sm rounded-lg transition
                          {{ $isActive ? 'bg-white text-brand font-semibold shadow' : 'text-white/85 hover:bg-white/15 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('site.logout') }}" class="relative p-4 border-t border-white/15">
            @csrf
            <button class="w-full text-left text-sm text-white/85 hover:text-white inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M16 17l5-5-5-5M21 12H9M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                {{ __('borrower.layout.sign_out') }}
            </button>
        </form>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen min-w-0">
        <header class="lg:hidden sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-gray-200 flex items-center justify-between px-4 h-14">
            <a href="{{ route('site.affiliate.dashboard') }}" class="flex items-center gap-2">
                <span class="size-7 grid place-items-center rounded-md bg-brand text-white font-extrabold text-sm">K</span>
                <span class="font-bold text-sm">{{ __('site.affiliate_portal.title') }}</span>
            </a>
            <button @click="open = true" class="p-2 -mr-2 text-gray-700" aria-label="Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </header>

        <div x-show="open" x-cloak class="fixed inset-0 z-40 lg:hidden">
            <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
            <div class="absolute inset-y-0 left-0 w-72 bg-brand text-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-5 h-14 border-b border-white/15">
                    <span class="font-extrabold">{{ __('borrower.layout.menu') }}</span>
                    <button @click="open = false" class="p-1 text-white/80"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                <nav class="flex-1 overflow-y-auto py-2">
                    @foreach ($nav as $item)
                        @php $isActive = $active === $item['key']; @endphp
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-3 mx-3 my-0.5 px-3 py-3 text-sm rounded-lg
                                  {{ $isActive ? 'bg-white text-brand font-semibold' : 'text-white/90 hover:bg-white/15' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
                <form method="POST" action="{{ route('site.logout') }}" class="p-4 border-t border-white/15">
                    @csrf
                    <button class="w-full text-sm text-left text-white/90 hover:text-white font-medium">{{ __('borrower.layout.sign_out') }}</button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="mx-4 lg:mx-8 mt-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mx-4 lg:mx-8 mt-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mx-4 lg:mx-8 mt-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <main class="flex-1 px-4 lg:px-8 py-6 lg:py-8 max-w-5xl mx-auto w-full">{{ $slot }}</main>

        <footer class="px-4 lg:px-8 py-6 text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ brand_name() }} · <a href="{{ route('site.faq') }}" class="hover:text-gray-600">{{ __('borrower.layout.help') }}</a>
        </footer>
    </div>
</div>

<x-site.celebration-confetti />
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
