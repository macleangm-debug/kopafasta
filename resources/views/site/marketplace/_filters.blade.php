@props(['filters' => [], 'category' => null, 'routeName' => 'site.borrower.marketplace'])

@php
    $activeCount = collect([
        $filters['q'] ?? null,
        $filters['brand'] ?? null,
        $filters['min_price'] ?? null,
        $filters['max_price'] ?? null,
        $filters['tenure'] ?? null,
    ])->filter(fn ($v) => filled($v))->count()
        + ((($filters['sort'] ?? 'title') !== 'title') ? 1 : 0);
@endphp

<div class="mb-6" x-data="{ filtersOpen: false }">
    {{-- Mobile: filter trigger --}}
    <div class="lg:hidden flex items-center gap-2 mb-4">
        <button type="button" @click="filtersOpen = true"
                class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
            <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
            {{ __('borrower.marketplace.filters') }}
            @if ($activeCount > 0)
                <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-brand text-white text-[10px] font-bold grid place-items-center">{{ $activeCount }}</span>
            @endif
        </button>
        @if ($activeCount > 0)
            <a href="{{ route($routeName, $category ? ['category' => $category] : []) }}"
               class="text-sm text-gray-500 hover:text-brand font-medium">{{ __('borrower.marketplace.clear') }}</a>
        @endif
    </div>

    {{-- Desktop: inline filters --}}
    @include('site.marketplace._filters-form', [
        'filters' => $filters,
        'category' => $category,
        'routeName' => $routeName,
        'formClass' => 'hidden lg:grid sm:grid-cols-2 lg:grid-cols-6 gap-3 bg-white rounded-xl ring-1 ring-gray-200 p-4',
    ])

    {{-- Mobile: bottom sheet --}}
    <x-site.bottom-sheet :title="__('borrower.marketplace.filters')" open="filtersOpen">
        @include('site.marketplace._filters-form', [
            'filters' => $filters,
            'category' => $category,
            'routeName' => $routeName,
            'formClass' => 'grid gap-4',
            'stacked' => true,
        ])
    </x-site.bottom-sheet>
</div>
