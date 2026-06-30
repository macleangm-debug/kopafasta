@props(['title' => 'Partner portal — Kopafasta', 'active' => 'dashboard'])

@php
$nav = [
    ['key' => 'dashboard',     'label' => 'Dashboard',      'route' => 'site.partner.dashboard',       'icon' => 'home'],
    ['key' => 'tasks',         'label' => 'Assigned Tasks', 'route' => 'site.partner.tasks',           'icon' => 'clipboard'],
    ['key' => 'recovery',      'label' => 'Recovery Cases', 'route' => 'site.partner.recovery-cases',  'icon' => 'alert'],
    ['key' => 'recovery_wallet','label' => 'Commission Wallet', 'route' => 'site.partner.recovery-wallet', 'icon' => 'wallet'],
    ['key' => 'active',        'label' => 'Active Jobs',    'route' => 'site.partner.tasks.active',    'icon' => 'play'],
    ['key' => 'completed',     'label' => 'Completed Jobs', 'route' => 'site.partner.tasks.completed', 'icon' => 'check'],
    ['key' => 'documents',     'label' => 'Documents',      'route' => 'site.partner.documents',       'icon' => 'folder'],
    ['key' => 'payments',      'label' => 'Payments',       'route' => 'site.partner.payments',        'icon' => 'wallet'],
    ['key' => 'calendar',      'label' => 'Calendar',       'route' => 'site.partner.calendar',        'icon' => 'calendar'],
    ['key' => 'notifications', 'label' => 'Notifications',  'route' => 'site.partner.notifications',   'icon' => 'bell'],
    ['key' => 'support',       'label' => 'Support',        'route' => 'site.partner.support',         'icon' => 'help'],
    ['key' => 'profile',       'label' => 'Profile',        'route' => 'site.partner.profile',         'icon' => 'user'],
];

$portalVendor = auth()->user()
    ? \App\Models\Vendor::query()->where('user_id', auth()->id())->first()
    : null;
$showRecoveryNav = $portalVendor
    && app(\App\Services\RecoveryPartnerService::class)->isRecoveryPartner($portalVendor);

if (! $showRecoveryNav) {
    $nav = array_values(array_filter($nav, fn (array $item) => ! in_array($item['key'], ['recovery', 'recovery_wallet'], true)));
}

$icon = function (string $name) {
    return match ($name) {
        'home'      => '<path d="M3 12 12 4l9 8M5 10v10h14V10"/>',
        'clipboard' => '<path d="M9 5h6a2 2 0 0 1 2 2v0h-10v0a2 2 0 0 1 2-2zM7 7H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/>',
        'alert'     => '<path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>',
        'play'      => '<path d="M8 5l12 7-12 7z"/>',
        'check'     => '<path d="M5 13l4 4L19 7"/>',
        'folder'    => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/>',
        'wallet'    => '<path d="M3 7h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm0 0V5a2 2 0 0 1 2-2h11M16 13h2"/>',
        'calendar'  => '<path d="M5 7h14a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1zM8 3v4M16 3v4M4 11h16"/>',
        'bell'      => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9zM10 21a2 2 0 0 0 4 0"/>',
        'help'      => '<path d="M12 18v.01M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/>',
        'user'      => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/>',
        default     => '<circle cx="12" cy="12" r="8"/>',
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
<body class="bg-gray-50 text-gray-900 antialiased" x-data="{open:false}">

<div class="min-h-screen flex">

    {{-- Sidebar (desktop) --}}
    <aside class="hidden lg:flex w-64 shrink-0 flex-col bg-gradient-to-b from-indigo-700 via-indigo-800 to-slate-900 text-white sticky top-0 h-screen shadow-xl">
        <a href="{{ route('site.home') }}" class="flex items-center gap-2 px-5 h-16 border-b border-white/15">
            <span class="size-9 grid place-items-center rounded-lg bg-white text-indigo-700 font-extrabold shadow">K</span>
            <div class="leading-tight">
                <div class="font-extrabold tracking-tight text-sm">Kopafasta</div>
                <div class="text-[11px] text-white/70">Partner portal</div>
            </div>
        </a>
        <nav class="flex-1 overflow-y-auto py-4">
            @foreach ($nav as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 mx-3 my-0.5 px-3 py-2.5 text-sm rounded-lg transition
                          {{ $isActive ? 'bg-white text-indigo-700 font-semibold shadow'
                                       : 'text-white/85 hover:bg-white/15 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('site.logout') }}" class="p-4 border-t border-white/15">
            @csrf
            <button class="w-full text-left text-sm text-white/85 hover:text-white inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M16 17l5-5-5-5M21 12H9M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                Sign out
            </button>
        </form>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen min-w-0">

        {{-- Topbar (desktop) --}}
        <header class="hidden lg:flex sticky top-0 z-20 bg-white border-b border-gray-200 items-center justify-end gap-4 px-8 h-16">
            <div class="text-right leading-tight">
                <p class="text-sm font-semibold text-gray-900">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
            </div>
            <div class="size-9 rounded-full bg-indigo-100 text-indigo-700 grid place-items-center font-bold">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
            <form method="POST" action="{{ route('site.logout') }}">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-red-50 hover:text-red-700 hover:border-red-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 17l5-5-5-5M21 12H9M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                    Sign out
                </button>
            </form>
        </header>

        {{-- Topbar (mobile) --}}
        <header class="lg:hidden sticky top-0 z-30 bg-white border-b border-gray-200 flex items-center justify-between px-4 h-14">
            <a href="{{ route('site.home') }}" class="flex items-center gap-2">
                <span class="size-7 grid place-items-center rounded-md bg-indigo-600 text-white font-extrabold text-sm">K</span>
                <span class="font-bold">Partner</span>
            </a>
            <div class="flex items-center gap-1">
                <form method="POST" action="{{ route('site.logout') }}">
                    @csrf
                    <button class="p-2 text-gray-600 hover:text-red-600" title="Sign out">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 17l5-5-5-5M21 12H9M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                    </button>
                </form>
                <button @click="open = true" class="p-2 -mr-2 text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </header>

        {{-- Mobile drawer --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-40 lg:hidden">
            <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
            <div class="absolute inset-y-0 left-0 w-72 bg-gradient-to-b from-indigo-700 via-indigo-800 to-slate-900 text-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-5 h-14 border-b border-white/15">
                    <span class="font-extrabold">Menu</span>
                    <button @click="open = false" class="p-1 text-white/80"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                <nav class="flex-1 overflow-y-auto py-2">
                    @foreach ($nav as $item)
                        @php $isActive = $active === $item['key']; @endphp
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-3 mx-3 my-0.5 px-3 py-3 text-sm rounded-lg
                                  {{ $isActive ? 'bg-white text-indigo-700 font-semibold' : 'text-white/90 hover:bg-white/15' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
                <form method="POST" action="{{ route('site.logout') }}" class="p-4 border-t border-white/15">
                    @csrf
                    <button class="w-full text-sm text-left text-white/90 hover:text-white font-medium">Sign out</button>
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

        <footer class="px-4 lg:px-8 py-6 text-center text-xs text-gray-400">
            © {{ date('Y') }} Kopafasta · <a href="{{ route('site.faq') }}" class="hover:text-gray-600">Help</a>
        </footer>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
