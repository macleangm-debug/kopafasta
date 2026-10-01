@php
    $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
    $label = $isSw ? ($group['label_sw'] ?? $group['label_en']) : ($group['label_en'] ?? $group['label_sw']);
    $articles = $group['articles'] ?? [];
    $subgroups = collect($articles)->groupBy(fn ($a) => $isSw
        ? ($a['subgroup_sw'] ?? $a['subgroup_en'] ?? 'Maswali')
        : ($a['subgroup_en'] ?? $a['subgroup_sw'] ?? 'Questions'));
    $supportHome = auth()->user()?->customer
        ? route('site.borrower.support')
        : (auth()->check() ? route('site.partner.support') : route('site.faq'));
@endphp

@if (auth()->user()?->customer)
    <x-site.borrower-layout :title="brand_title($label)" active="support" content-width="narrow">
        @include('site.help._category-body', compact('label', 'articles', 'subgroups', 'supportHome', 'isSw', 'group', 'categoryKey'))
    </x-site.borrower-layout>
@elseif (auth()->check())
    <x-site.vendor-layout :title="brand_title($label)" active="support">
        @include('site.help._category-body', compact('label', 'articles', 'subgroups', 'supportHome', 'isSw', 'group', 'categoryKey'))
    </x-site.vendor-layout>
@else
    <x-site.layout :title="brand_title($label)">
        <div class="max-w-3xl mx-auto px-4 py-8">
            @include('site.help._category-body', compact('label', 'articles', 'subgroups', 'supportHome', 'isSw', 'group', 'categoryKey'))
        </div>
    </x-site.layout>
@endif
