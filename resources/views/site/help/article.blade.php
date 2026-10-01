@php
    $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
    $q = $isSw ? ($article['q_sw'] ?? $article['q_en'] ?? '') : ($article['q_en'] ?? $article['q_sw'] ?? '');
@endphp

@if (auth()->user()?->customer)
    <x-site.borrower-layout :title="brand_title($q)" active="support" content-width="narrow">
        @include('site.help._article-body')
    </x-site.borrower-layout>
@elseif (auth()->check())
    <x-site.vendor-layout :title="brand_title($q)" active="support">
        @include('site.help._article-body')
    </x-site.vendor-layout>
@else
    <x-site.layout :title="brand_title($q)">
        <div class="max-w-3xl mx-auto px-4 py-8">
            @include('site.help._article-body')
        </div>
    </x-site.layout>
@endif
