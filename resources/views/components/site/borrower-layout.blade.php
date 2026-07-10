@props(['title' => null, 'active' => 'dashboard', 'portalMode' => 'borrower', 'contentWidth' => 'default'])

@php
    $pageTitle = $title ?? brand_title('My account');
    $contentMax = match ($contentWidth) {
        'narrow' => 'max-w-3xl',
        'wide'   => 'max-w-7xl',
        default  => 'max-w-6xl',
    };
    $siteLocale = $siteLocale ?? app()->getLocale();
    $siteCountry = $siteCountry ?? strtoupper((string) session('country', 'TZ'));
    $siteCountries = $siteCountries ?? collect(app(\App\Services\CountrySettingsService::class)->codes())
        ->map(fn (string $code) => app(\App\Services\CountrySettingsService::class)->forCode($code))
        ->values()
        ->all();

    $nav = $portalMode === 'guarantor'
        ? [
            ['key' => 'loans', 'label' => __('borrower.loans_page.tab_guarantor_requests'), 'route' => 'site.borrower.loans', 'route_params' => ['tab' => 'guarantor'], 'icon' => 'users'],
            ['key' => 'guarantor-notifications', 'label' => __('borrower.nav.guarantor_notifications'), 'route' => 'site.borrower.guarantor-notifications', 'icon' => 'bell'],
            ['key' => 'profile', 'label' => __('borrower.nav.profile'), 'route' => 'site.borrower.profile', 'icon' => 'user'],
            ['key' => 'support', 'label' => __('borrower.nav.support'), 'route' => 'site.borrower.support', 'icon' => 'help'],
        ]
        : [
            ['key' => 'dashboard',     'label' => __('borrower.nav.dashboard'),     'route' => 'site.borrower.dashboard',     'icon' => 'home'],
            ['key' => 'engagement',    'label' => __('borrower.nav.engagement'),    'route' => 'site.borrower.engagement',    'icon' => 'users'],
            ['key' => 'loans',         'label' => __('borrower.nav.loans'),         'route' => 'site.borrower.loans',         'icon' => 'wallet'],
            ['key' => 'marketplace',   'label' => __('borrower.nav.marketplace'),   'route' => 'site.borrower.marketplace', 'icon' => 'folder'],
            ['key' => 'payments',      'label' => __('borrower.nav.payments'),      'route' => 'site.borrower.payments',      'icon' => 'pay'],
            ['key' => 'notifications', 'label' => __('borrower.nav.notifications'), 'route' => 'site.borrower.notifications', 'icon' => 'bell'],
            ['key' => 'support',       'label' => __('borrower.nav.support'),       'route' => 'site.borrower.support',       'icon' => 'help'],
            ['key' => 'profile',       'label' => __('borrower.nav.profile'),       'route' => 'site.borrower.profile',       'icon' => 'user'],
        ];

    $borrowerCustomer = auth()->user()?->customer;
    $portalContext = app(\App\Services\PortalContextService::class);
    $displayName = $portalContext->displayName($borrowerCustomer);
    $notificationQuery = $portalMode === 'guarantor' && $borrowerCustomer
        ? $portalContext->guarantorNotificationsQuery($borrowerCustomer)
        : ($borrowerCustomer ? $portalContext->borrowerNotificationsQuery($borrowerCustomer) : null);
    $unreadNotifications = $notificationQuery
        ? $notificationQuery->whereNull('read_at')->count()
        : 0;

    $icon = function (string $name) {
        return match ($name) {
            'home'    => '<path d="M3 12 12 4l9 8M5 10v10h14V10"/>',
            'doc'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6"/>',
            'wallet'  => '<path d="M3 7h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm0 0V5a2 2 0 0 1 2-2h11M16 13h2"/>',
            'calendar'=> '<path d="M5 7h14a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1zM8 3v4M16 3v4M4 11h16"/>',
            'pay'     => '<path d="M3 10h18M5 6h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm3 9h3"/>',
            'folder'  => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/>',
            'users'   => '<path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM3 21a7 7 0 0 1 14 0M22 11a3 3 0 1 0-3-3"/>',
            'bell'    => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9zM10 21a2 2 0 0 0 4 0"/>',
            'user'    => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/>',
            'help'    => '<path d="M12 18v.01M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/>',
            'shield'  => '<path d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5l-8-3zM9 12l2 2 4-4"/>',
            default   => '<circle cx="12" cy="12" r="8"/>',
        };
    };
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $siteLocale) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-[#faf8f5] text-gray-900 antialiased" x-data="{open:false}">

<x-site.flash-notice />

<div class="min-h-screen flex">

    {{-- Sidebar (desktop) --}}
    <aside class="hidden lg:flex w-64 shrink-0 flex-col bg-brand text-white sticky top-0 h-screen shadow-xl">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <a href="{{ route('site.borrower.dashboard') }}" class="relative flex items-center gap-2 px-5 h-16 border-b border-white/15">
            <x-site.brand-mark size="sm" variant="light" />
            <div class="leading-tight ml-1">
                <div class="text-[11px] text-white/70">{{ __('borrower.portal') }}</div>
            </div>
        </a>
        <nav class="relative flex-1 overflow-y-auto py-4">
            @foreach ($nav as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                   class="flex items-center gap-3 mx-3 my-0.5 px-3 py-2.5 text-sm rounded-xl transition
                          {{ $isActive ? 'bg-brand-gold text-brand font-bold shadow-sm'
                                       : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        {!! $icon($item['icon']) !!}
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="relative p-4 border-t border-white/15 text-[11px] text-white/60">
            {{ __('borrower.signed_in_as', ['name' => $displayName]) }}
        </div>
    </aside>

    {{-- Main column --}}
    <div class="flex-1 flex flex-col min-h-screen min-w-0">

        {{-- Topbar (desktop) --}}
        <header class="hidden lg:flex sticky top-0 z-20 glass-nav items-center justify-between gap-4 px-6 lg:px-8 h-16">
            <a href="{{ route('site.home') }}" class="text-xs font-medium text-gray-500 hover:text-brand transition">
                ← {{ brand_name() }}
            </a>
            <div class="flex items-center gap-3">
                <x-site.locale-switcher variant="header" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                <div class="relative" x-data="notificationBell()" x-init="load()">
                    <button type="button" @click="toggle()" class="relative p-2 rounded-lg text-gray-600 hover:bg-brand-muted hover:text-brand" title="{{ __('borrower.layout.notifications') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $icon('bell') !!}</svg>
                        <span x-show="unread > 0" x-cloak class="absolute -top-0.5 -right-0.5 min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center" x-text="unread > 9 ? '9+' : unread"></span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-96 max-w-[calc(100vw-2rem)] rounded-2xl glass-card overflow-hidden z-50">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-white/80">
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.layout.notifications') }}</p>
                            <a href="{{ route('site.borrower.notifications') }}" class="text-xs font-semibold text-brand hover:underline">{{ __('borrower.layout.view_all') }}</a>
                        </div>
                        <div class="max-h-80 overflow-y-auto bg-white/90">
                            <template x-if="items.length === 0">
                                <p class="px-4 py-8 text-sm text-gray-500 text-center">{{ __('borrower.layout.no_notifications') }}</p>
                            </template>
                            <template x-for="item in items" :key="item.id">
                                <div class="px-4 py-3 border-b border-gray-50 hover:bg-brand-muted/30" :class="!item.read ? 'bg-brand-muted/50' : ''">
                                    <p class="text-xs uppercase tracking-widest text-gray-400" x-text="item.category"></p>
                                    <p class="text-sm text-gray-800 mt-0.5" x-text="item.message"></p>
                                    <p class="text-[11px] text-gray-400 mt-1" x-text="item.when"></p>
                                    <template x-if="item.action_url">
                                        <a :href="item.action_url" class="inline-flex mt-2 text-xs font-semibold text-brand hover:underline" x-text="item.action_label || @js(__('borrower.notifications.view_application'))"></a>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="relative" x-data="{ profileOpen: false }">
                    <button type="button" @click="profileOpen = !profileOpen"
                            class="flex items-center gap-3 rounded-xl hover:bg-brand-muted/60 px-2 py-1.5 transition">
                        <div class="text-right leading-tight hidden sm:block">
                            <p class="text-sm font-semibold text-gray-900">{{ $displayName }}</p>
                            <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
                        </div>
                        <div class="size-9 rounded-full bg-brand text-white grid place-items-center font-bold text-sm">
                            {{ strtoupper(substr($displayName, 0, 1)) }}
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                         class="absolute right-0 mt-2 w-56 rounded-2xl glass-card overflow-hidden z-50 py-1 bg-white/95">
                        <a href="{{ route('site.borrower.profile') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.layout.my_profile') }}</a>
                        <a href="{{ route('site.borrower.profile', ['section' => 'security']) }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.layout.security_settings') }}</a>
                        <a href="{{ route('site.borrower.notifications') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.layout.notifications') }}</a>
                        <a href="{{ route('site.borrower.support') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.layout.help_center') }}</a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="{{ route('site.logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">{{ __('borrower.layout.sign_out') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Topbar (mobile) --}}
        <header class="lg:hidden sticky top-0 z-30 glass-nav flex items-center justify-between px-3 h-14 gap-2">
            <a href="{{ route('site.borrower.dashboard') }}" class="flex items-center gap-2 shrink-0">
                <x-site.brand-mark size="sm" />
            </a>
            <div class="flex items-center gap-0.5 shrink-0">
                <x-site.locale-switcher variant="compact" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                <a href="{{ route('site.borrower.notifications') }}" class="relative p-2 text-gray-600 hover:text-brand" title="{{ __('borrower.layout.notifications') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $icon('bell') !!}</svg>
                    @if ($unreadNotifications > 0)
                        <span class="absolute top-1 right-1 min-w-[1rem] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                    @endif
                </a>
                <div class="relative" x-data="{ profileOpen: false }">
                    <button type="button" @click="profileOpen = !profileOpen" class="p-1.5 rounded-lg hover:bg-brand-muted/60" title="{{ __('borrower.layout.my_profile') }}">
                        <div class="size-8 rounded-full bg-brand text-white grid place-items-center font-bold text-xs">
                            {{ strtoupper(substr($displayName, 0, 1)) }}
                        </div>
                    </button>
                    <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                         class="absolute right-0 mt-2 w-56 rounded-2xl glass-card overflow-hidden z-50 py-1 bg-white/95 shadow-xl">
                        <a href="{{ route('site.borrower.profile') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.layout.my_profile') }}</a>
                        <a href="{{ route('site.borrower.profile', ['section' => 'security']) }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.layout.security_settings') }}</a>
                        <a href="{{ route('site.borrower.support') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.layout.help_center') }}</a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="{{ route('site.logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">{{ __('borrower.layout.sign_out') }}</button>
                        </form>
                    </div>
                </div>
                <button @click="open = true" class="p-2 text-gray-700" aria-label="{{ __('borrower.layout.menu') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </header>

        {{-- Mobile drawer --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-40 lg:hidden">
            <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
            <div class="absolute inset-y-0 left-0 w-72 bg-brand text-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-5 h-14 border-b border-white/15">
                    <span class="font-bold">{{ __('borrower.layout.menu') }}</span>
                    <button @click="open = false" class="p-1 text-white/80"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                <nav class="flex-1 overflow-y-auto py-2">
                    @foreach ($nav as $item)
                        @php $isActive = $active === $item['key']; @endphp
                        <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                           class="flex items-center gap-3 mx-3 my-0.5 px-3 py-3 text-sm rounded-xl
                                  {{ $isActive ? 'bg-brand-gold text-brand font-bold' : 'text-white/90 hover:bg-white/10' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
                <div class="p-4 border-t border-white/15">
                    <x-site.locale-switcher :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                </div>
            </div>
        </div>

        {{-- Flash messages use modal via flash-notice --}}
        @if ($errors->any())
            <div class="mx-4 lg:mx-8 mt-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                <p class="font-semibold mb-1">{{ __('borrower.layout.fix_errors') }}</p>
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <main class="flex-1 px-4 lg:px-8 py-6 lg:py-8">
            <div class="{{ $contentMax }} w-full mx-auto">
                {{ $slot }}
            </div>
        </main>

        <footer class="px-4 lg:px-8 py-6 text-center text-xs text-gray-400 border-t border-gray-200/60">
            © {{ date('Y') }} {{ brand('legal_name') }} · <a href="{{ route('site.faq') }}" class="hover:text-brand">{{ __('borrower.layout.help') }}</a>
        </footer>
    </div>
</div>

<x-site.confirm-modal name="default" />
<x-site.borrower-help-hub />
<x-site.celebration-confetti />

@stack('scripts')
<script>
(function () {
    function spinnerHtml(label) {
        return '<svg class="size-4 animate-spin shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>' +
            '</svg><span>' + label + '</span>';
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.skipLoading === '1' || form.dataset.loadingBound === '1') {
            return;
        }
        // Alpine forms that already toggle a submitting flag.
        if (form.getAttribute('x-data') && /submitting/.test(form.getAttribute('x-data'))) {
            return;
        }

        const submitter = event.submitter instanceof HTMLButtonElement
            ? event.submitter
            : form.querySelector('button[type="submit"], input[type="submit"]');
        if (! submitter || submitter.disabled) {
            return;
        }

        form.dataset.loadingBound = '1';
        const label = (submitter.dataset.loadingLabel || submitter.textContent || 'Saving').trim().replace(/\s+/g, ' ');
        const loadingLabel = /…$|\.\.\.$/.test(label) ? label : (label + '…');

        submitter.disabled = true;
        submitter.classList.add('opacity-70', 'cursor-wait', 'inline-flex', 'items-center', 'gap-2');
        if (submitter.tagName === 'BUTTON') {
            submitter.innerHTML = spinnerHtml(loadingLabel);
        } else {
            submitter.value = loadingLabel;
        }
    }, true);
})();
</script>
<script>
document.addEventListener('alpine:init', () => {
    window.confirmForm = (form, detail = {}) => {
        window.dispatchEvent(new CustomEvent('open-confirm-default', {
            detail: { form, ...detail },
        }));
    };

    Alpine.data('notificationBell', () => ({
        open: false,
        unread: {{ $unreadNotifications }},
        items: [],
        async load() {
            try {
                const res = await fetch(@js(route('site.borrower.notifications.preview')), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const data = await res.json();
                this.unread = data.unread ?? 0;
                this.items = data.items ?? [];
            } catch (e) {}
        },
        async toggle() {
            const willOpen = !this.open;
            this.open = willOpen;
            if (!willOpen) return;
            await this.load();
            if (this.unread <= 0) return;
            try {
                const res = await fetch(@js(route('site.borrower.notifications.read')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                });
                if (res.ok) {
                    this.unread = 0;
                    this.items = this.items.map(item => ({ ...item, read: true }));
                }
            } catch (e) {}
        },
    }));
});
</script>
@vite('resources/js/alpine-init.js')
</body>
</html>
