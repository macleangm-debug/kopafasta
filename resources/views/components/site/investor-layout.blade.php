@props(['title' => 'Investor portal — Kopafasta', 'active' => 'dashboard'])

@php
$nav = [
    ['key' => 'dashboard',     'label' => 'Dashboard',         'route' => 'site.investor.dashboard',     'icon' => 'home'],
    ['key' => 'pools',         'label' => 'Funding Pools',     'route' => 'site.investor.pools',         'icon' => 'layers'],
    ['key' => 'investments',   'label' => 'My Investments',    'route' => 'site.investor.investments',   'icon' => 'chart'],
    ['key' => 'funded',        'label' => 'Funded Loans',      'route' => 'site.investor.funded-loans',  'icon' => 'chart'],
    ['key' => 'returns',       'label' => 'Returns & Earnings','route' => 'site.investor.returns',       'icon' => 'trend'],
    ['key' => 'analytics',     'label' => 'Portfolio Analytics','route' => 'site.investor.analytics',    'icon' => 'pie'],
    ['key' => 'transactions',  'label' => 'Transactions',      'route' => 'site.investor.transactions',  'icon' => 'list'],
    ['key' => 'wallet',        'label' => 'Wallet',            'route' => 'site.investor.wallet',        'icon' => 'wallet'],
    ['key' => 'documents',     'label' => 'Documents',         'route' => 'site.investor.documents',     'icon' => 'folder'],
    ['key' => 'notifications', 'label' => 'Notifications',     'route' => 'site.investor.notifications', 'icon' => 'bell'],
    ['key' => 'support',       'label' => 'Support',           'route' => 'site.investor.support',       'icon' => 'help'],
    ['key' => 'profile',       'label' => 'Profile',           'route' => 'site.investor.profile',       'icon' => 'user'],
];

$icon = function (string $name) {
    return match ($name) {
        'home'    => '<path d="M3 12 12 4l9 8M5 10v10h14V10"/>',
        'layers'  => '<path d="M12 3 2 8l10 5 10-5-10-5zM2 14l10 5 10-5M2 19l10 5 10-5"/>',
        'chart'   => '<path d="M4 19V5M4 19h16M8 16V9M12 16V6M16 16v-4"/>',
        'trend'   => '<path d="M3 17l6-6 4 4 8-8M21 7h-5M21 7v5"/>',
        'pie'     => '<path d="M21 12A9 9 0 1 1 12 3v9h9z"/>',
        'list'    => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'wallet'  => '<path d="M3 7h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm0 0V5a2 2 0 0 1 2-2h11M16 13h2"/>',
        'folder'  => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/>',
        'bell'    => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9zM10 21a2 2 0 0 0 4 0"/>',
        'help'    => '<path d="M12 18v.01M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/>',
        'user'    => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/>',
        default   => '<circle cx="12" cy="12" r="8"/>',
    };
};
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased" x-data="{open:false}">

<div class="min-h-screen flex">

    {{-- Sidebar (desktop) — premium dark navy with emerald accents --}}
    <aside class="hidden lg:flex w-64 shrink-0 flex-col bg-gradient-to-b from-slate-900 via-slate-900 to-emerald-950 text-white sticky top-0 h-screen shadow-2xl">
        <a href="{{ route('site.home') }}" class="flex items-center gap-2 px-5 h-16 border-b border-white/10">
            <span class="size-9 grid place-items-center rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 text-slate-900 font-extrabold shadow">K</span>
            <div class="leading-tight">
                <div class="font-extrabold tracking-tight">Kopafasta</div>
                <div class="text-[11px] text-emerald-300/80 font-semibold uppercase tracking-wider">Capital · Investor</div>
            </div>
        </a>
        <nav class="flex-1 overflow-y-auto py-4">
            @foreach ($nav as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 mx-3 my-0.5 px-3 py-2.5 text-sm rounded-lg transition
                          {{ $isActive ? 'bg-emerald-500 text-slate-900 font-semibold shadow-lg shadow-emerald-500/30'
                                       : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('site.logout') }}" class="p-4 border-t border-white/10">
            @csrf
            <button class="w-full text-left text-sm text-slate-300 hover:text-white inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M16 17l5-5-5-5M21 12H9M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                Sign out
            </button>
        </form>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen min-w-0">

        {{-- Topbar (desktop) --}}
        <header class="hidden lg:flex sticky top-0 z-20 bg-white border-b border-slate-200 items-center justify-end gap-4 px-8 h-16">
            <div class="text-right leading-tight">
                <p class="text-sm font-semibold text-slate-900">{{ Auth::user()->name }}</p>
                <p class="text-xs text-slate-500">{{ Auth::user()->email }}</p>
            </div>
            <div class="size-9 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center font-bold">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
            <form method="POST" action="{{ route('site.logout') }}">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-red-50 hover:text-red-700 hover:border-red-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 17l5-5-5-5M21 12H9M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                    Sign out
                </button>
            </form>
        </header>

        {{-- Topbar (mobile) --}}
        <header class="lg:hidden sticky top-0 z-30 bg-white border-b border-slate-200 flex items-center justify-between px-4 h-14 gap-2">
            <a href="{{ route('site.home') }}" class="flex items-center gap-2 min-w-0">
                <span class="size-7 grid place-items-center rounded-md bg-emerald-500 text-slate-900 font-extrabold text-sm shrink-0">K</span>
                <span class="font-bold truncate">Investor</span>
            </a>
            <div class="flex items-center gap-1 shrink-0">
                <x-site.locale-switcher variant="compact" />
                <button @click="open = true" class="p-2 -mr-2 text-slate-700" aria-label="Open menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </header>

        {{-- Mobile drawer --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-40 lg:hidden">
            <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
            <div class="absolute inset-y-0 left-0 w-72 bg-gradient-to-b from-slate-900 via-slate-900 to-emerald-950 text-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-5 h-14 border-b border-white/10">
                    <span class="font-extrabold">Menu</span>
                    <button @click="open = false" class="p-1 text-white/80"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                <nav class="flex-1 overflow-y-auto py-2">
                    @foreach ($nav as $item)
                        @php $isActive = $active === $item['key']; @endphp
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-3 mx-3 my-0.5 px-3 py-3 text-sm rounded-lg
                                  {{ $isActive ? 'bg-emerald-500 text-slate-900 font-semibold' : 'text-slate-200 hover:bg-white/10' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
                <form method="POST" action="{{ route('site.logout') }}" class="p-4 border-t border-white/10">
                    @csrf
                    <button class="w-full text-sm text-left text-slate-200 hover:text-white font-medium">Sign out</button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="mx-4 lg:mx-8 mt-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mx-4 lg:mx-8 mt-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                <p class="font-semibold mb-1">Please fix:</p>
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <main class="flex-1 px-4 lg:px-8 py-6 lg:py-8">{{ $slot }}</main>

        <footer class="px-4 lg:px-8 py-6 text-center text-xs text-slate-400">
            © {{ date('Y') }} Kopafasta Capital · <a href="{{ route('site.faq') }}" class="hover:text-slate-600">Help</a>
        </footer>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
