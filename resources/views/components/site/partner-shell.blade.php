@props([
    'title' => null,
    'active' => 'dashboard',
    'contentWidth' => 'wide',
    'nav' => [],
    'homeRoute' => 'site.home',
    'portalLabel' => 'Partner portal',
    'displayName' => null,
    'subtitle' => null,
    'banner' => null,
    'profileLinks' => [],
    'notificationsRoute' => 'site.partner.notifications',
])

@php
    $pageTitle = $title ?? brand_title($portalLabel);
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

    $name = $displayName ?? auth()->user()?->name ?? 'Partner';
    $navService = app(\App\Services\PartnerPortalNavService::class);
    $profileLinks = $profileLinks ?: [
        ['label' => 'Profile', 'route' => 'site.partner.profile'],
    ];
    $notificationsHref = \Illuminate\Support\Facades\Route::has($notificationsRoute)
        ? route($notificationsRoute)
        : route('site.partner.notifications');
    $partnerNotificationsQuery = \App\Models\NotificationLog::query()
        ->when(
            \Illuminate\Support\Facades\Schema::hasColumn('notification_logs', 'user_id'),
            fn ($q) => $q->where('user_id', auth()->id()),
            fn ($q) => $q->where(function ($inner) {
                $inner->where('recipient', auth()->user()?->email)
                    ->orWhere('recipient', auth()->user()?->phone);
            })
        );
    $partnerUnread = (clone $partnerNotificationsQuery)->whereNull('read_at')->count();
    $partnerPreview = (clone $partnerNotificationsQuery)->latest()->limit(8)->get();
    $shellVendor = \App\Models\Vendor::query()->where('user_id', auth()->id())->first();
    $mobileNav = $navService->mobilePrimaryNav($nav, $shellVendor);
    $mobileNavKeys = array_column($mobileNav, 'key');
    $overflowNav = array_values(array_filter($nav, fn (array $item) => ! in_array($item['key'], $mobileNavKeys, true)));
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $siteLocale) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Permissions-Policy" content="camera=(self), microphone=(), geolocation=(), notifications=(), push=()">
    <title>{{ $pageTitle }}</title>
    <link rel="icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-[#faf8f5] text-gray-900 antialiased" x-data="{open:false, profileSheet:false}">
<x-site.kopafasta-launcher />

@if (session('status') || session('warning') || session('error'))
    <div class="sr-only" aria-hidden="true"
         x-data
         x-init="
            $nextTick(() => {
                @if (session('error'))
                    window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: { tone: 'error', title: @js(brand_name()), message: @js(session('error')), lines: [] }
                    }));
                @elseif (session('warning'))
                    window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: { tone: 'warning', title: @js(brand_name()), message: @js(session('warning')), lines: [] }
                    }));
                @elseif (session('status'))
                    window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: { tone: 'success', title: @js(brand_name()), message: @js(session('status')), lines: [] }
                    }));
                @endif
            });
         "></div>
@endif

<div class="min-h-screen flex">

    <aside class="kf-chrome-sidebar hidden lg:flex w-64 shrink-0 flex-col bg-brand text-white sticky top-0 h-screen shadow-xl">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <a href="{{ Route::has($homeRoute) ? route($homeRoute) : route('site.home') }}" class="relative block px-5 py-4 border-b border-white/15">
            <x-site.brand-mark size="md" variant="light" :portal="$portalLabel" />
        </a>
        <nav class="relative flex-1 overflow-y-auto py-4">
            @foreach ($nav as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}"
                   data-kf-motion="tab"
                   class="flex items-center gap-3 mx-3 my-0.5 px-3 py-2.5 text-sm rounded-xl transition
                          {{ $isActive ? 'bg-brand-gold text-brand font-bold shadow-sm'
                                       : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        {!! $navService->iconSvg($item['icon'] ?? 'home') !!}
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="relative p-4 border-t border-white/15 text-[11px] text-white/60">
            {{ __('site.affiliate_portal.signed_in_as', ['name' => $name]) }}
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen min-w-0">

        <header class="kf-chrome-topbar-desktop hidden lg:flex sticky top-0 z-20 glass-nav items-center justify-between gap-4 px-6 lg:px-8 h-16">
            <a href="{{ route('site.home') }}" class="text-xs font-medium text-gray-500 hover:text-brand transition">
                ← {{ brand_name() }}
            </a>
            <div class="flex items-center gap-3">
                <x-site.locale-switcher variant="header" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="relative p-2 rounded-lg text-gray-600 hover:bg-brand-muted hover:text-brand" title="{{ __('site.partner_portal.nav_notifications') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $navService->iconSvg('bell') !!}</svg>
                        @if ($partnerUnread > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $partnerUnread > 9 ? '9+' : $partnerUnread }}</span>
                        @endif
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-96 max-w-[calc(100vw-2rem)] rounded-2xl glass-card overflow-hidden z-50 bg-white/95 shadow-xl">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">{{ __('site.partner_portal.nav_notifications') }}</p>
                            <a href="{{ $notificationsHref }}" data-kf-motion="tab" class="text-xs font-semibold text-brand hover:underline">{{ __('site.partner_portal.view_all') }}</a>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            @forelse ($partnerPreview as $n)
                                <div class="px-4 py-3 border-b border-gray-50 hover:bg-brand-muted/30 {{ $n->read_at ? '' : 'bg-brand-muted/40' }}">
                                    <p class="text-[11px] font-bold uppercase tracking-widest text-brand">{{ $n->category ?: 'general' }}</p>
                                    <p class="text-sm text-gray-800 mt-0.5">{{ $n->message ?: $n->template }}</p>
                                    <p class="text-[11px] text-gray-400 mt-1">{{ $n->created_at?->diffForHumans() }}</p>
                                </div>
                            @empty
                                <p class="px-4 py-8 text-sm text-gray-500 text-center">{{ __('site.partner_portal.no_notifications') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="relative" x-data="{ profileOpen: false }">
                    <button type="button" @click="profileOpen = !profileOpen"
                            class="flex items-center gap-3 rounded-xl hover:bg-brand-muted/60 px-2 py-1.5 transition">
                        <div class="text-right leading-tight hidden sm:block">
                            <p class="text-sm font-semibold text-gray-900">{{ $name }}</p>
                            <p class="text-xs text-gray-500">{{ $subtitle ?? Auth::user()?->email }}</p>
                        </div>
                        <div class="size-9 rounded-full bg-brand text-white grid place-items-center font-bold text-sm">
                            {{ strtoupper(substr($name, 0, 1)) }}
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                         class="absolute right-0 mt-2 w-56 rounded-2xl glass-card overflow-hidden z-50 py-1 bg-white/95">
                        @foreach ($profileLinks as $link)
                            <a href="{{ route($link['route']) }}" data-kf-motion="tab" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ $link['label'] }}</a>
                        @endforeach
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="{{ route('site.logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">{{ __('borrower.layout.sign_out') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <header class="kf-chrome-topbar-mobile lg:hidden sticky top-0 z-30 glass-nav flex items-center justify-between px-3 h-14 gap-2">
            <a href="{{ Route::has($homeRoute) ? route($homeRoute) : route('site.home') }}" class="flex items-center gap-2 shrink-0">
                <x-site.brand-mark size="sm" />
            </a>
            <div class="flex items-center gap-0.5 shrink-0">
                <x-site.locale-switcher variant="compact" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                <a href="{{ $notificationsHref }}" data-kf-motion="tab" class="relative p-2 text-gray-600 hover:text-brand" title="{{ __('site.partner_portal.nav_notifications') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $navService->iconSvg('bell') !!}</svg>
                    @if (($partnerUnread ?? 0) > 0)
                        <span class="absolute top-1 right-1 min-w-[1rem] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $partnerUnread > 9 ? '9+' : $partnerUnread }}</span>
                    @endif
                </a>
                <button type="button" @click="profileSheet = true" class="p-1.5 rounded-lg hover:bg-brand-muted/60" title="{{ __('site.partner_portal.nav_profile') }}">
                    <div class="size-8 rounded-full bg-brand text-white grid place-items-center font-bold text-xs">
                        {{ strtoupper(substr($name, 0, 1)) }}
                    </div>
                </button>
            </div>
        </header>

        <template x-teleport="body">
            <div x-show="profileSheet" x-cloak class="fixed inset-0 z-[10056] lg:hidden" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40" @click="profileSheet = false" x-transition.opacity></div>
                <div class="absolute inset-x-0 bottom-0 bg-white shadow-[0_-8px_40px_rgba(0,0,0,0.18)] rounded-t-2xl flex flex-col"
                     style="padding-bottom: env(safe-area-inset-bottom, 0px)"
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
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                        <h2 class="text-base font-bold text-gray-900">{{ $name }}</h2>
                        <button type="button" @click="profileSheet = false" class="p-2 -mr-2 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Close">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <nav class="px-2 py-2 max-h-[50vh] overflow-y-auto">
                        @foreach ($profileLinks as $link)
                            <a href="{{ route($link['route']) }}" data-kf-motion="tab" class="block px-4 py-3.5 text-sm font-medium text-gray-800 rounded-xl hover:bg-brand-muted">{{ $link['label'] }}</a>
                        @endforeach
                        @foreach ($overflowNav as $item)
                            <a href="{{ route($item['route']) }}" data-kf-motion="tab" class="block px-4 py-3.5 text-sm font-medium text-gray-800 rounded-xl hover:bg-brand-muted">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                    <div class="px-4 pb-4 pt-1 border-t border-gray-100">
                        <form method="POST" action="{{ route('site.logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-red-50 text-red-600 text-sm font-semibold py-3.5">{{ __('borrower.layout.sign_out') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </template>

        @if ($banner)
            <div class="mx-4 lg:mx-8 mt-4 px-4 py-3 rounded-xl bg-brand-muted/60 ring-1 ring-brand/15 text-sm text-brand">
                {{ $banner }}
            </div>
        @endif

        @if ($errors->any())
            <div
                x-data
                x-init="
                    $nextTick(() => window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: {
                            title: @js(__('borrower.feedback.form_errors_title')),
                            lines: @js($errors->all()),
                            tone: 'error',
                        }
                    })));
                "
                class="sr-only"
                aria-hidden="true"
            ></div>
        @endif

        <main class="kf-chrome-page flex-1 px-4 lg:px-8 py-6 lg:py-8 pb-28 lg:pb-8 overflow-x-clip" data-kf-busy-scope>
            <div class="{{ $contentMax }} w-full mx-auto min-w-0">
                {{ $slot }}
            </div>
        </main>

        <footer class="px-4 lg:px-8 py-6 text-center text-xs text-gray-400 border-t border-gray-200/60 hidden lg:block">
            © {{ date('Y') }} {{ brand('legal_name') }} · <a href="{{ route('site.faq') }}" class="hover:text-brand">{{ __('borrower.layout.help') }}</a>
        </footer>
    </div>
</div>

<nav class="kf-mobile-bottom-nav lg:hidden fixed inset-x-0 bottom-0 z-40 bg-white/95 backdrop-blur border-t border-brand/15"
     style="padding-bottom: env(safe-area-inset-bottom, 0px)">
    <div class="grid grid-cols-5">
        @foreach ($mobileNav as $item)
            @php $isActive = $active === $item['key']; @endphp
            <a href="{{ route($item['route']) }}"
               data-kf-motion="tab"
               class="flex flex-col items-center gap-1 px-1 pt-2 pb-1.5 text-center {{ $isActive ? 'text-brand font-bold' : 'text-brand/70' }}">
                <span class="grid place-items-center size-11 rounded-2xl {{ $isActive ? 'bg-brand text-white' : 'bg-brand/10 text-brand' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $navService->iconSvg($item['icon'] ?? 'home') !!}</svg>
                </span>
                <span class="text-[10px] leading-tight line-clamp-2 w-full">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>

<x-site.upload-busy-overlay />
<x-site.confirm-modal name="default" />
<x-site.feedback-modal name="default" />
<x-site.celebration-confetti />
<x-site.document-lightbox />

@stack('scripts')
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
</script>
@vite('resources/js/alpine-init.js')
</body>
</html>
