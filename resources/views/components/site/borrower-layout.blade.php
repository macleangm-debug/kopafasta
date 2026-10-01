@props(['title' => null, 'active' => 'dashboard', 'portalMode' => 'borrower', 'contentWidth' => 'default'])

@php
    $pageTitle = $title ?? brand_title('My account');
    $seoDocument = app(\App\Services\SeoService::class)->privateDocument(request(), $pageTitle);
    $contentMax = match ($contentWidth) {
        'narrow' => 'max-w-3xl mx-auto',
        'wide'   => 'max-w-7xl mx-auto',
        // Full shell width for the page column; pages that need a narrow card constrain themselves.
        'full'   => '',
        default  => 'max-w-7xl mx-auto',
    };
    $siteLocale = $siteLocale ?? app()->getLocale();
    $siteCountry = $siteCountry ?? strtoupper((string) session('country', 'TZ'));
    $siteCountries = $siteCountries ?? collect(app(\App\Services\CountrySettingsService::class)->codes())
        ->map(fn (string $code) => app(\App\Services\CountrySettingsService::class)->forCode($code))
        ->values()
        ->all();

    $nav = [
            ['key' => 'dashboard',     'label' => __('borrower.nav.dashboard'),     'route' => 'site.borrower.dashboard',     'icon' => 'home'],
            ['key' => 'plus',          'label' => __('borrower.nav.plus'),          'route' => 'site.borrower.plus.home',     'icon' => 'plus'],
            ['key' => 'engagement',    'label' => __('borrower.nav.engagement'),    'route' => 'site.borrower.engagement',    'icon' => 'users'],
            ['key' => 'loans',         'label' => __('borrower.nav.loans'),         'route' => 'site.borrower.loans',         'icon' => 'wallet'],
            ['key' => 'marketplace',   'label' => __('borrower.nav.marketplace'),   'route' => 'site.borrower.marketplace', 'icon' => 'folder'],
            ['key' => 'notifications', 'label' => __('borrower.nav.notifications'), 'route' => 'site.borrower.notifications', 'icon' => 'bell'],
            ['key' => 'profile',       'label' => __('borrower.nav.profile'),       'route' => 'site.borrower.profile',       'icon' => 'user'],
            ['key' => 'settings',      'label' => __('borrower.nav.settings'),      'route' => 'site.borrower.settings',      'icon' => 'settings'],
            ['key' => 'support',       'label' => __('borrower.nav.support'),       'route' => 'site.borrower.support',       'icon' => 'help'],
        ];

    $borrowerCustomer = auth()->user()?->customer;
    $portalContext = app(\App\Services\PortalContextService::class);
    $displayName = $portalContext->displayName($borrowerCustomer);
    $plusActive = $borrowerCustomer
        ? app(\App\Services\Plus\PlusService::class)->isActive($borrowerCustomer)
        : false;
    $inbox = app(\App\Services\NotificationInboxService::class);
    $bellPreviewItems = $borrowerCustomer ? $inbox->previewItems($borrowerCustomer) : [];
    $unreadNotifications = $borrowerCustomer ? $inbox->attentionCount($borrowerCustomer) : 0;
    $pendingGuarantorPopup = collect();

    $icon = function (string $name) {
        return match ($name) {
            'home'    => '<path d="M3 12 12 4l9 8M5 10v10h14V10"/>',
            'plus'    => '<path d="M12 3.2 13.4 8h4.8L15.2 11l1.4 4.8L12 13.2 7.4 15.8 8.8 11 5.8 8h4.8L12 3.2z"/>',
            'doc'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6"/>',
            'wallet'  => '<path d="M3 7h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm0 0V5a2 2 0 0 1 2-2h11M16 13h2"/>',
            'calendar'=> '<path d="M5 7h14a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1zM8 3v4M16 3v4M4 11h16"/>',
            'settings'=> '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.2.6.7 1 1.4 1.1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
            'pay'     => '<path d="M3 10h18M5 6h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm3 9h3"/>',
            'folder'  => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/>',
            'users'   => '<path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM3 21a7 7 0 0 1 14 0M22 11a3 3 0 1 0-3-3"/>',
            'bell'    => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9zM10 21a2 2 0 0 0 4 0"/>',
            'user'    => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/>',
            'help'    => '<path d="M12 18v.01M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/>',
            'shield'  => '<path d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5l-8-3zM9 12l2 2 4-4"/>',
            'list'    => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'chart'   => '<path d="M4 19V5M4 19h16M8 16V9M12 16V6M16 16v-4"/>',
            'trend'   => '<path d="M3 17l6-6 4 4 8-8M21 7h-5M21 7v5"/>',
            default   => '<circle cx="12" cy="12" r="8"/>',
        };
    };

    $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
    $mobileNavService = app(\App\Services\BorrowerMobileNavService::class);
    $mobileNavService->rememberPlusRoom($routeName);
    $plusWorkspace = $mobileNavService->showsPlusWorkspaceNav($plusActive, $routeName);
    $hideMobileNav = $mobileNavService->hidesMobileNav($routeName);
    $mobileNav = $plusWorkspace ? $mobileNavService->plusWorkspaceNav() : $mobileNavService->mobilePrimaryNav();
    $mobileActive = $plusWorkspace
        ? $mobileNavService->plusActiveKey($routeName)
        : ($mobileNavService->isPlusWorkspace($routeName) ? 'plus' : $active);
    $plusMoreItems = $plusWorkspace ? $mobileNavService->plusMoreItems() : [];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $siteLocale) }}" translate="no" class="notranslate h-full kf-account-shell"
      data-theme="{{ app(\App\Services\AccountThemeService::class)->resolved(auth()->user()) }}"
      data-kf-draft-owner="{{ $borrowerCustomer?->id ? 'customer:'.$borrowerCustomer->id : (auth()->id() ? 'user:'.auth()->id() : 'guest') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="kf-draft-owner" content="{{ $borrowerCustomer?->id ? 'customer:'.$borrowerCustomer->id : (auth()->id() ? 'user:'.auth()->id() : 'guest') }}">
    <meta http-equiv="Permissions-Policy" content="camera=(self), microphone=(), geolocation=(), notifications=(), push=()">
    <x-site.account-theme-boot />
    <x-site.seo :document="$seoDocument" />
    <link rel="icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-[#faf8f5] text-gray-900 antialiased" x-data="{open:false, profileSheet:false, plusMoreOpen:false}">
<x-site.environment-banner />
<x-site.kopafasta-launcher />
<script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

@if ((session('feedback') || session('status') || session('warning') || session('error')) && ! session('kf_status_inline'))
    <div class="sr-only" aria-hidden="true"
         x-data
         x-init="
            $nextTick(() => {
                @if (session('feedback'))
                    @php $feedback = session('feedback'); @endphp
                    window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: {
                            tone: @js($feedback['tone'] ?? 'info'),
                            title: @js($feedback['title'] ?? brand_name()),
                            message: @js($feedback['message'] ?? ''),
                            lines: @js($feedback['lines'] ?? []),
                        }
                    }));
                @elseif (session('error'))
                    window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: { tone: 'error', title: @js(brand_name()), message: @js(session('error')), lines: [] }
                    }));
                @elseif (session('warning'))
                    window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: { tone: 'warning', title: @js(brand_name()), message: @js(session('warning')), lines: [] }
                    }));
            @elseif (session('status'))
                @php
                    $statusMessage = (string) session('status');
                    $ordinarySave = (bool) preg_match('/\b(saved|updated|imehifadhiwa|imesasishwa)\b/i', $statusMessage);
                @endphp
                if (typeof window.kfFlashInlineSaved === 'function') {
                    window.kfFlashInlineSaved(@js($ordinarySave ? __('borrower.document_upload.saved') : $statusMessage));
                }
            @endif
            });
         "></div>
@endif

<div class="kf-account-shell-frame min-h-screen">

    {{-- Sidebar (desktop) --}}
    <aside class="kf-chrome-sidebar hidden lg:flex w-64 shrink-0 flex-col bg-brand text-white sticky top-0 h-screen shadow-xl">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <a href="{{ route('site.borrower.dashboard') }}" data-kf-motion="tab" class="relative block px-5 py-4 border-b border-white/15">
            <x-site.brand-mark size="md" variant="light" :portal="__('borrower.portal')" />
        </a>
        <nav class="relative flex-1 overflow-y-auto px-3 py-5 space-y-1">
            @foreach ($nav as $item)
                @php
                    $isActive = $active === $item['key'];
                    $showPlusOptional = ($item['key'] ?? '') === 'plus' && ! $plusActive;
                @endphp
                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                   data-kf-motion="tab"
                   class="group relative flex items-center gap-3 px-3.5 py-2.5 text-sm rounded-xl transition
                          {{ $isActive ? 'bg-brand-gold text-brand font-bold shadow-sm'
                                       : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                    @if ($isActive)
                        <span class="absolute left-0 inset-y-2 w-1 rounded-full bg-brand" aria-hidden="true"></span>
                    @endif
                    <svg class="w-5 h-5 shrink-0 {{ $isActive ? '' : 'opacity-90 group-hover:opacity-100' }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        {!! $icon($item['icon']) !!}
                    </svg>
                    <span class="min-w-0 flex-1 leading-snug">
                        <span class="block">{{ $item['label'] }}</span>
                        @if ($showPlusOptional)
                            <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-[0.14em] {{ $isActive ? 'text-brand/70' : 'text-white/55' }}">{{ __('plus.home.nav_optional') }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </nav>
        <div class="relative p-4 border-t border-white/15 text-[11px] text-white/60">
            {{ __('borrower.signed_in_as', ['name' => $displayName]) }}
        </div>
    </aside>

    {{-- Main column: full remaining width; never wrap topbar in content max-width. --}}
    <div class="flex-1 flex flex-col min-h-screen min-w-0 w-full max-w-none">

        {{-- Topbar (desktop) — full shell width; padding matches main. --}}
        <header class="kf-chrome-topbar-desktop hidden lg:flex sticky top-0 z-40 glass-nav w-full max-w-none min-w-0 shrink-0 self-stretch">
            <div class="flex w-full max-w-none min-w-0 items-center gap-4 px-4 lg:px-8 h-16">
                <a href="{{ route('site.home') }}" class="text-xs font-medium text-gray-500 hover:text-brand transition shrink-0">
                    ← {{ brand_name() }}
                </a>
                <div class="flex items-center gap-3 ml-auto shrink-0">
                    <x-site.theme-toggle variant="header" />
                    <x-site.locale-switcher variant="header" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                    <div class="relative" x-data="{ sheetOpen: false }">
                        <button type="button" @click="sheetOpen = !sheetOpen" class="relative p-2 rounded-lg text-gray-600 hover:bg-brand-muted hover:text-brand" title="{{ __('borrower.layout.notifications') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $icon('bell') !!}</svg>
                            @if ($unreadNotifications > 0)
                                <span class="absolute -top-0.5 -right-0.5 min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                            @endif
                        </button>
                        <div x-show="sheetOpen" @click.outside="sheetOpen = false" x-cloak
                             class="absolute right-0 mt-2 w-96 max-w-[calc(100%-2rem)] rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden z-[80]">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-white">
                                <p class="text-sm font-semibold text-gray-900">{{ __('borrower.layout.notifications') }}</p>
                                <a href="{{ route('site.borrower.notifications') }}" data-kf-motion="tab" class="text-xs font-semibold text-brand hover:underline">{{ __('borrower.layout.view_all') }}</a>
                            </div>
                            <div class="max-h-80 overflow-y-auto bg-white">
                                <x-site.borrower-bell-items :items="$bellPreviewItems" />
                            </div>
                        </div>
                    </div>
                    <div class="relative" x-data="{ profileOpen: false }">
                        <button type="button" @click="profileOpen = !profileOpen"
                                class="flex items-center gap-3 rounded-xl hover:bg-brand-muted/60 px-2 py-1.5 transition">
                            <div class="text-right leading-tight hidden sm:block">
                                <p class="text-sm font-semibold text-gray-900">{{ $displayName }}</p>
                                @php
                                    $headerMemberNo = $borrowerCustomer
                                        ? (\App\Support\MemberNumberFormatter::display($borrowerCustomer->member_no)
                                            ?: (string) ($borrowerCustomer->customer_number ?? ''))
                                        : '';
                                @endphp
                                @if (filled($headerMemberNo))
                                    <p class="text-xs text-gray-500 font-mono">{{ $headerMemberNo }}</p>
                                @endif
                            </div>
                            <div class="size-9 rounded-full bg-brand text-white grid place-items-center font-bold text-sm">
                                {{ strtoupper(substr($displayName, 0, 1)) }}
                            </div>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                             class="absolute right-0 mt-2 w-56 rounded-2xl glass-card overflow-hidden z-50 py-1 bg-white/95">
                            <a href="{{ route('site.borrower.profile') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.layout.my_profile') }}</a>
                            <a href="{{ route('site.borrower.settings') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('borrower.nav.settings') }}</a>
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
            </div>
        </header>

        {{-- Topbar (mobile) --}}
        <header class="kf-chrome-topbar-mobile lg:hidden sticky top-0 z-40 glass-nav flex items-center justify-between px-3 h-14 gap-2 w-full">
            @if ($plusWorkspace)
                <a href="{{ route('site.borrower.dashboard') }}" data-kf-motion="tab" class="flex items-center gap-2 min-w-0 text-sm font-extrabold text-brand">
                    <span aria-hidden="true">←</span>
                    <span class="truncate">{{ __('borrower.layout.back_to_kopafasta') }}</span>
                </a>
            @else
                <a href="{{ route('site.borrower.dashboard') }}" data-kf-motion="tab" class="flex items-center gap-2 shrink-0">
                    <x-site.brand-mark size="sm" />
                </a>
            @endif
            <div class="flex items-center gap-0.5 shrink-0">
                <x-site.theme-toggle variant="compact" />
                <x-site.locale-switcher variant="compact" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                <div class="relative" x-data="{ sheetOpen: false }">
                    <button type="button" @click="sheetOpen = !sheetOpen" class="relative p-2 text-gray-600 hover:text-brand" title="{{ __('borrower.layout.notifications') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $icon('bell') !!}</svg>
                        @if ($unreadNotifications > 0)
                            <span class="absolute top-1 right-1 min-w-[1rem] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                        @endif
                    </button>
                    <template x-teleport="body">
                        <div x-show="sheetOpen" x-cloak class="fixed inset-0 z-[10060] lg:hidden" role="dialog" aria-modal="true">
                            <div class="absolute inset-0 bg-black/40" @click="sheetOpen = false"></div>
                            <div class="absolute inset-x-0 bottom-0 max-h-[min(85vh,640px)] flex flex-col rounded-t-2xl bg-white shadow-[0_-8px_40px_rgba(0,0,0,0.18)]"
                                 @click.stop
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="translate-y-full"
                                 x-transition:enter-end="translate-y-0"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="translate-y-0"
                                 x-transition:leave-end="translate-y-full">
                                <div class="flex justify-center pt-3 pb-1 shrink-0">
                                    <div class="w-10 h-1 rounded-full bg-gray-300"></div>
                                </div>
                                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 shrink-0">
                                    <h2 class="text-base font-bold text-gray-900">{{ __('borrower.layout.notifications') }}</h2>
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('site.borrower.notifications') }}" data-kf-motion="tab" @click="sheetOpen = false" class="text-xs font-semibold text-brand">{{ __('borrower.layout.view_all') }}</a>
                                        <button type="button" @click="sheetOpen = false" class="p-2 -mr-2 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Close">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex-1 overflow-y-auto overscroll-contain">
                                    <x-site.borrower-bell-items :items="$bellPreviewItems" compact />
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <a href="{{ route('site.borrower.profile') }}" class="p-1.5 rounded-lg hover:bg-brand-muted/60" title="{{ __('borrower.nav.profile') }}">
                    <div class="size-8 rounded-full bg-brand text-white grid place-items-center font-bold text-xs">
                        {{ strtoupper(substr($displayName, 0, 1)) }}
                    </div>
                </a>
            </div>
        </header>

        {{-- Validation feedback uses modal (not inline error walls) --}}
        @if ($errors->any())
            <div
                x-data
                x-init="
                    window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: {
                            title: @js(__('borrower.feedback.form_errors_title')),
                            lines: @js($errors->all()),
                            tone: 'error',
                        }
                    }));
                "
                class="sr-only"
                aria-hidden="true"
            ></div>
        @endif

        <main class="kf-chrome-page flex-1 px-4 lg:px-8 py-6 lg:py-8 {{ $hideMobileNav ? '' : 'pb-28 lg:pb-8' }} overflow-x-clip w-full min-w-0" data-kf-busy-scope>
            @if ($contentMax !== '')
                <div class="{{ $contentMax }} w-full min-w-0">
                    {{ $slot }}
                </div>
            @else
                <div class="w-full min-w-0">
                    {{ $slot }}
                </div>
            @endif
        </main>

        <footer class="px-4 lg:px-8 py-6 text-center text-xs text-gray-400 border-t border-gray-200/60 hidden lg:block">
            © {{ date('Y') }} {{ brand('legal_name') }} · <a href="{{ route('site.faq') }}" class="hover:text-brand">{{ __('borrower.layout.help') }}</a>
        </footer>
    </div>
</div>

@if (! $hideMobileNav)
<nav class="kf-mobile-bottom-nav lg:hidden fixed inset-x-0 bottom-0 z-40 bg-white/95 backdrop-blur border-t border-brand/15"
     style="padding-bottom: env(safe-area-inset-bottom, 0px)">
    <div class="grid {{ count($mobileNav) === 4 ? 'grid-cols-4' : 'grid-cols-5' }}">
        @foreach ($mobileNav as $item)
            @php $isActive = $mobileActive === $item['key']; @endphp
            @if (($item['action'] ?? null) === 'more')
                <button type="button" @click="plusMoreOpen = true"
                        class="flex flex-col items-center gap-1 px-1 pt-2 pb-1.5 text-center {{ $isActive ? 'text-brand font-bold' : 'text-brand/70' }}">
                    <span class="grid place-items-center size-11 rounded-2xl {{ $isActive ? 'bg-brand text-white' : 'bg-brand/10 text-brand' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                    </span>
                    <span class="text-[10px] leading-tight line-clamp-2 w-full">{{ $item['label'] }}</span>
                </button>
            @else
                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                   data-kf-motion="tab"
                   class="flex flex-col items-center gap-1 px-1 pt-2 pb-1.5 text-center {{ $isActive ? 'text-brand font-bold' : 'text-brand/70' }}">
                    <span class="grid place-items-center size-11 rounded-2xl {{ $isActive ? 'bg-brand text-white' : 'bg-brand/10 text-brand' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon($item['icon']) !!}</svg>
                    </span>
                    <span class="text-[10px] leading-tight line-clamp-2 w-full">{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>
</nav>
@endif

@if ($plusWorkspace)
<template x-teleport="body">
    <div x-show="plusMoreOpen" x-cloak class="fixed inset-0 z-[10058] lg:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/40" @click="plusMoreOpen = false"></div>
        <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-2xl p-5 space-y-2 shadow-[0_-8px_40px_rgba(0,0,0,0.18)]"
             style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))"
             @click.stop>
            <div class="flex justify-center pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
            <p class="font-extrabold text-gray-900">{{ __('plus.nav.more_title') }}</p>
            @foreach ($plusMoreItems as $more)
                <a href="{{ route($more['route']) }}" @click="plusMoreOpen = false"
                   class="block rounded-xl ring-1 ring-gray-200 px-4 py-3.5 hover:bg-brand-muted/40">
                    <p class="text-sm font-extrabold text-gray-900">{{ $more['label'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $more['hint'] }}</p>
                </a>
            @endforeach
            <a href="{{ route('site.borrower.dashboard') }}" class="block text-center text-sm font-bold text-brand pt-2">← {{ __('borrower.layout.back_to_kopafasta') }}</a>
        </div>
    </div>
</template>
@endif

{{-- Invitation surface after login is notifications + Mikopo, not an automatic modal. --}}
<x-site.upload-busy-overlay />
<x-site.confirm-modal name="default" />
<x-site.feedback-modal name="default" />
@if (session('show_membership_card') && $borrowerCustomer && ($borrowerCustomer->isMembershipActive() || $borrowerCustomer->isMembershipInGrace() || $borrowerCustomer->hasMembership()))
    <x-site.membership-card-modal :customer="$borrowerCustomer" />
@endif
<x-site.borrower-help-hub />
<x-site.celebration-confetti />
<x-site.document-lightbox />

@stack('scripts')
<script>
window.confirmForm = (form, detail = {}) => {
    const tone = detail.tone
        || (String(detail.confirmClass || '').includes('red') ? 'warning' : 'confirm');
    // form may be null when detail.onConfirm is provided (Alpine actions).
    window.dispatchEvent(new CustomEvent('open-confirm-default', {
        detail: { form: form || null, tone, ...detail },
    }));
};
window.confirmAction = (detail = {}) => window.confirmForm(null, detail);

document.addEventListener('alpine:init', () => {

    window.showBorrowerFeedback = (detail = {}) => {
        window.dispatchEvent(new CustomEvent('open-feedback-default', {
            detail: typeof detail === 'string' ? { message: detail } : detail,
        }));
    };
});
</script>
@vite('resources/js/alpine-init.js')
</body>
</html>
