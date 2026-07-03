@props([
    'variant' => 'header',
    'showLabel' => false,
])

@php
    $siteLocale = $siteLocale ?? app()->getLocale();
    $siteCountry = $siteCountry ?? 'TZ';
    $siteCountries = $siteCountries ?? [];
    $currentCountry = collect($siteCountries)->firstWhere('code', $siteCountry) ?? ['code' => 'TZ', 'name' => 'Tanzania', 'emoji' => '🇹🇿'];
    $selectClass = $variant === 'header'
        ? 'bg-white/60 border border-white/50 rounded-lg text-gray-700 font-medium py-1.5 pl-8 pr-8 text-xs focus:ring-2 focus:ring-brand/20 cursor-pointer appearance-none'
        : 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 pl-10 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10 cursor-pointer appearance-none';
    $wrapperClass = $variant === 'header' ? 'flex items-center gap-3' : 'flex flex-col gap-2';
@endphp

<div class="{{ $wrapperClass }}">
    <form method="POST" action="{{ route('site.country.update') }}" class="relative inline-flex items-center w-full">
        @csrf
        @if ($showLabel)
            <label class="sr-only">{{ __('site.locale.country') }}</label>
        @endif
        <span class="absolute left-2.5 z-10 text-base pointer-events-none" aria-hidden="true">{{ $currentCountry['emoji'] ?? '🌍' }}</span>
        <select name="country" onchange="this.form.submit()" class="{{ $selectClass }} w-full">
            @foreach ($siteCountries as $country)
                <option value="{{ $country['code'] }}" @selected($siteCountry === $country['code'])>
                    {{ ($country['emoji'] ?? '').' '.$country['name'] }}
                </option>
            @endforeach
        </select>
        <span class="absolute right-2 pointer-events-none text-gray-400">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </span>
    </form>

    <form method="POST" action="{{ route('site.locale.update') }}" class="relative inline-flex items-center w-full">
        @csrf
        <select name="locale" onchange="this.form.submit()" class="{{ $selectClass }} w-full">
            <option value="en" @selected($siteLocale === 'en')>{{ __('site.locale.english') }}</option>
            <option value="sw" @selected($siteLocale === 'sw')>{{ __('site.locale.swahili') }}</option>
        </select>
        <span class="absolute right-2 pointer-events-none text-gray-400">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </span>
    </form>
</div>
