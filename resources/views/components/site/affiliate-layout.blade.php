@props(['title' => null, 'active' => 'dashboard', 'contentWidth' => 'wide'])

@php
    $pageTitle = $title ?? brand_title(__('site.affiliate_portal.title'));
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

    $vendor = auth()->user()
        ? \App\Models\Vendor::query()->where('user_id', auth()->id())->first()
        : null;
    $displayName = $vendor?->name ?? auth()->user()?->name ?? 'Partner';

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

    <aside class="hidden lg:flex w-64 shrink-0 flex-col bg-brand text-white sticky top-0 h-screen shadow-xl">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <a href="{{ route('site.affiliate.dashboard') }}" class="relative flex items-center gap-2 px-5 h-16 border-b border-white/15">
            <x-site.brand-mark size="sm" variant="light" />
            <div class="leading-tight ml-1">
                <div class="text-[11px] text-white/70">{{ __('site.affiliate_portal.title') }}</div>
            </div>
        </a>
        <nav class="relative flex-1 overflow-y-auto py-4">
            @foreach ($nav as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}"
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
            {{ __('site.affiliate_portal.signed_in_as', ['name' => $displayName]) }}
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen min-w-0">

        <header class="hidden lg:flex sticky top-0 z-20 glass-nav items-center justify-between gap-4 px-6 lg:px-8 h-16">
            <a href="{{ route('site.home') }}" class="text-xs font-medium text-gray-500 hover:text-brand transition">
                ← {{ brand_name() }}
            </a>
            <div class="flex items-center gap-3">
                <x-site.locale-switcher variant="header" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                <div class="relative" x-data="{ profileOpen: false }">
                    <button type="button" @click="profileOpen = !profileOpen"
                            class="flex items-center gap-3 rounded-xl hover:bg-brand-muted/60 px-2 py-1.5 transition">
                        <div class="text-right leading-tight hidden sm:block">
                            <p class="text-sm font-semibold text-gray-900">{{ $displayName }}</p>
                            <p class="text-xs text-gray-500">{{ $vendor?->partner_number ?? Auth::user()->email }}</p>
                        </div>
                        <div class="size-9 rounded-full bg-brand text-white grid place-items-center font-bold text-sm">
                            {{ strtoupper(substr($displayName, 0, 1)) }}
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                         class="absolute right-0 mt-2 w-56 rounded-2xl glass-card overflow-hidden z-50 py-1 bg-white/95">
                        <a href="{{ route('site.affiliate.profile') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('site.affiliate_portal.nav_profile') }}</a>
                        <a href="{{ route('site.affiliate.wallet') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('site.affiliate_portal.nav_wallet') }}</a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="{{ route('site.logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">{{ __('borrower.layout.sign_out') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <header class="lg:hidden sticky top-0 z-30 glass-nav flex items-center justify-between px-3 h-14 gap-2">
            <a href="{{ route('site.affiliate.dashboard') }}" class="flex items-center gap-2 shrink-0">
                <x-site.brand-mark size="sm" />
            </a>
            <div class="flex items-center gap-0.5 shrink-0">
                <x-site.locale-switcher variant="compact" :siteCountries="$siteCountries" :siteCountry="$siteCountry" :siteLocale="$siteLocale" />
                <div class="relative" x-data="{ profileOpen: false }">
                    <button type="button" @click="profileOpen = !profileOpen" class="p-1.5 rounded-lg hover:bg-brand-muted/60">
                        <div class="size-8 rounded-full bg-brand text-white grid place-items-center font-bold text-xs">
                            {{ strtoupper(substr($displayName, 0, 1)) }}
                        </div>
                    </button>
                    <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                         class="absolute right-0 mt-2 w-56 rounded-2xl glass-card overflow-hidden z-50 py-1 bg-white/95 shadow-xl">
                        <a href="{{ route('site.affiliate.profile') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('site.affiliate_portal.nav_profile') }}</a>
                        <a href="{{ route('site.affiliate.wallet') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">{{ __('site.affiliate_portal.nav_wallet') }}</a>
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
                        <a href="{{ route($item['route']) }}"
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
<x-site.celebration-confetti />

@stack('scripts')
<script>
document.addEventListener('alpine:init', () => {
    window.confirmForm = (form, detail = {}) => {
        window.dispatchEvent(new CustomEvent('open-confirm-default', {
            detail: { form, ...detail },
        }));
    };
});
</script>
@vite('resources/js/alpine-init.js')
</body>
</html>
