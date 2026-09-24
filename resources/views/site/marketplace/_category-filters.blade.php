@props([
    'categories' => [],
    'category' => null,
    'routeName' => 'site.marketplace',
    'activeClass' => 'bg-brand text-white',
    'inactiveClass' => 'glass-card text-gray-600 hover:ring-brand/20',
])

@php
    $baseParams = request()->except('category');
@endphp

<div class="hidden lg:flex flex-wrap gap-2 mt-4" data-marketplace-category-chips>
    <a href="{{ route($routeName, $baseParams) }}"
       data-kf-motion="tab"
       class="px-4 py-2 rounded-full text-sm font-medium transition {{ empty($category) ? $activeClass : $inactiveClass }}">
        {{ __('borrower.marketplace.all') }}
    </a>
    @foreach ($categories as $key => $label)
        <a href="{{ route($routeName, array_merge($baseParams, ['category' => $key])) }}"
           data-kf-motion="tab"
           class="px-4 py-2 rounded-full text-sm font-medium transition {{ $category === $key ? $activeClass : $inactiveClass }}">
            {{ $label }}
        </a>
    @endforeach
</div>
