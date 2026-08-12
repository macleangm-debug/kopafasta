@props(['active' => 'overview'])

@php
    $links = [
        ['overview', __('site.about.nav.overview'), 'site.about'],
        ['founding', __('site.about.nav.founding'), 'site.about.founding'],
        ['trust', __('site.about.nav.trust'), 'site.about.trust'],
        ['impact', __('site.about.nav.impact'), 'site.about.impact'],
        ['roadmap', __('site.about.nav.roadmap'), 'site.about.roadmap'],
    ];
@endphp

<nav class="border-b border-brand/10 bg-white/90 backdrop-blur sticky top-0 z-20" aria-label="{{ __('site.about.nav.label') }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex gap-1 overflow-x-auto py-3 scrollbar-none">
            @foreach ($links as [$key, $label, $route])
                <a href="{{ route($route) }}"
                   class="shrink-0 px-3.5 py-2 rounded-xl text-sm font-semibold transition ring-1
                          {{ $active === $key
                              ? 'bg-brand text-white ring-brand'
                              : 'bg-white text-gray-600 ring-gray-200 hover:ring-brand/30 hover:text-brand' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
</nav>
