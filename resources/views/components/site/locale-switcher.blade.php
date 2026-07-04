@props([
    'variant' => 'header',
])

@php
    $siteLocale = $siteLocale ?? app()->getLocale();
    $siteCountry = $siteCountry ?? 'TZ';
    $siteCountries = $siteCountries ?? collect(app(\App\Services\CountrySettingsService::class)->codes())
        ->map(fn (string $code) => app(\App\Services\CountrySettingsService::class)->forCode($code))
        ->values()
        ->all();
    $currentCountry = collect($siteCountries)->firstWhere('code', $siteCountry)
        ?? ['code' => 'TZ', 'name' => 'Tanzania', 'emoji' => '🇹🇿'];
    $isHeader = $variant === 'header';
    $isCompact = $variant === 'compact';
@endphp

@if ($isCompact)
<div class="relative" x-data="{ localeOpen: false }" @keydown.escape.window="localeOpen = false">
    <button type="button" @click="localeOpen = !localeOpen"
            class="inline-flex items-center gap-1 rounded-lg border border-gray-200/80 bg-white/80 px-2 py-1.5 text-xs font-semibold text-gray-700 hover:bg-white shadow-sm"
            title="{{ __('site.locale.country') }} / {{ $siteLocale === 'sw' ? __('site.locale.swahili') : __('site.locale.english') }}">
        <span>{{ $siteLocale === 'sw' ? '🇹🇿' : '🇬🇧' }}</span>
        <span class="uppercase">{{ $siteLocale }}</span>
    </button>
    <div x-cloak x-show="localeOpen" @click.outside="localeOpen = false" x-transition
         class="absolute right-0 top-full mt-1 w-40 z-50 rounded-xl border border-gray-200 bg-white shadow-xl py-1">
        @foreach (['en' => ['label' => __('site.locale.english'), 'flag' => '🇬🇧'], 'sw' => ['label' => __('site.locale.swahili'), 'flag' => '🇹🇿']] as $code => $meta)
            <form method="POST" action="{{ route('site.locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $code }}">
                <button type="submit"
                        class="w-full flex items-center gap-2 px-3 py-2 text-left text-sm hover:bg-brand-muted transition {{ $siteLocale === $code ? 'bg-brand-muted/60 text-brand font-semibold' : 'text-gray-700' }}">
                    <span>{{ $meta['flag'] }}</span>
                    <span>{{ $meta['label'] }}</span>
                </button>
            </form>
        @endforeach
    </div>
</div>
@else
<div class="{{ $isHeader ? 'flex items-center gap-2' : 'flex flex-col gap-3' }}"
     x-data="{ countryOpen: false, localeOpen: false }"
     @keydown.escape.window="countryOpen = false; localeOpen = false">

    {{-- Country --}}
    <div class="relative {{ $isHeader ? '' : 'w-full' }}">
        <button type="button" @click="countryOpen = !countryOpen; localeOpen = false"
                class="{{ $isHeader
                    ? 'inline-flex items-center gap-2 rounded-lg border border-gray-200/80 bg-white/80 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-white shadow-sm min-w-[9rem]'
                    : 'w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30' }}">
            <span class="text-lg leading-none">{{ $currentCountry['emoji'] ?: '🌍' }}</span>
            <span class="truncate">{{ $currentCountry['name'] ?? $siteCountry }}</span>
            <svg class="w-4 h-4 ml-auto text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>
        <div x-cloak x-show="countryOpen" @click.outside="countryOpen = false" x-transition
             class="absolute {{ $isHeader ? 'right-0 top-full mt-1 w-56' : 'left-0 right-0 top-full mt-1' }} z-50 rounded-xl border border-gray-200 bg-white shadow-xl py-1 max-h-64 overflow-y-auto">
            @foreach ($siteCountries as $country)
                <form method="POST" action="{{ route('site.country.update') }}">
                    @csrf
                    <input type="hidden" name="country" value="{{ $country['code'] }}">
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm hover:bg-brand-muted transition {{ $siteCountry === $country['code'] ? 'bg-brand-muted/60 text-brand font-semibold' : 'text-gray-700' }}">
                        <span class="text-xl leading-none w-7 text-center">{{ $country['emoji'] ?: '🌍' }}</span>
                        <span>
                            <span class="block">{{ $country['name'] }}</span>
                            <span class="block text-[10px] uppercase tracking-wider text-gray-400">{{ $country['code'] }}</span>
                        </span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>

    {{-- Language --}}
    <div class="relative {{ $isHeader ? '' : 'w-full' }}">
        <button type="button" @click="localeOpen = !localeOpen; countryOpen = false"
                class="{{ $isHeader
                    ? 'inline-flex items-center gap-2 rounded-lg border border-gray-200/80 bg-white/80 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-white shadow-sm min-w-[7rem]'
                    : 'w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30' }}">
            <span>{{ $siteLocale === 'sw' ? '🇹🇿' : '🇬🇧' }}</span>
            <span>{{ $siteLocale === 'sw' ? __('site.locale.swahili') : __('site.locale.english') }}</span>
            <svg class="w-4 h-4 ml-auto text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>
        <div x-cloak x-show="localeOpen" @click.outside="localeOpen = false" x-transition
             class="absolute {{ $isHeader ? 'right-0 top-full mt-1 w-44' : 'left-0 right-0 top-full mt-1' }} z-50 rounded-xl border border-gray-200 bg-white shadow-xl py-1">
            @foreach (['en' => ['label' => __('site.locale.english'), 'flag' => '🇬🇧'], 'sw' => ['label' => __('site.locale.swahili'), 'flag' => '🇹🇿']] as $code => $meta)
                <form method="POST" action="{{ route('site.locale.update') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm hover:bg-brand-muted transition {{ $siteLocale === $code ? 'bg-brand-muted/60 text-brand font-semibold' : 'text-gray-700' }}">
                        <span class="text-lg">{{ $meta['flag'] }}</span>
                        <span>{{ $meta['label'] }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</div>
@endif
