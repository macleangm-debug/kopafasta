@props([
    'filters' => [],
    'category' => null,
    'categories' => [],
    'routeName' => 'site.borrower.marketplace',
])

@php
    $activeCount = collect([
        $filters['brand'] ?? null,
        $filters['min_price'] ?? null,
        $filters['max_price'] ?? null,
        $filters['tenure'] ?? null,
    ])->filter(fn ($v) => filled($v))->count();
    $sortOptions = [
        'title' => __('borrower.marketplace.sort_title'),
        'price_asc' => __('borrower.marketplace.sort_price_asc'),
        'price_desc' => __('borrower.marketplace.sort_price_desc'),
        'deposit_asc' => __('borrower.marketplace.sort_deposit_asc'),
    ];
    $currentSort = $filters['sort'] ?? 'title';
@endphp

<div class="mb-6" x-data="{ filtersOpen: false }" data-marketplace-filters>
    <form method="GET" action="{{ route($routeName) }}" class="flex flex-wrap items-center gap-2">
        @if ($category)
            <input type="hidden" name="category" value="{{ $category }}">
        @endif
        <input type="search"
               name="q"
               value="{{ $filters['q'] ?? '' }}"
               placeholder="{{ __('borrower.marketplace.search_assets') }}"
               class="min-w-0 flex-1 rounded-xl border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
        <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}">
        <input type="hidden" name="min_price" value="{{ $filters['min_price'] ?? '' }}">
        <input type="hidden" name="max_price" value="{{ $filters['max_price'] ?? '' }}">
        <input type="hidden" name="tenure" value="{{ $filters['tenure'] ?? '' }}">
        <select name="sort" class="hidden lg:block rounded-xl border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm bg-white" onchange="this.form.requestSubmit()">
            @foreach ($sortOptions as $value => $label)
                <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="button" @click="filtersOpen = true"
                class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
            {{ __('borrower.marketplace.filters') }}
            @if ($activeCount > 0)
                <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-brand text-white text-[10px] font-bold grid place-items-center">{{ $activeCount }}</span>
            @endif
        </button>
        <button type="submit" class="sr-only">{{ __('borrower.marketplace.search') }}</button>
    </form>

    @include('site.marketplace._category-filters', [
        'categories' => $categories,
        'category' => $category,
        'routeName' => $routeName,
        'activeClass' => $routeName === 'site.borrower.marketplace' ? 'bg-brand text-white' : 'bg-brand text-white',
        'inactiveClass' => $routeName === 'site.borrower.marketplace'
            ? 'bg-white ring-1 ring-gray-200/80 text-gray-600 hover:bg-brand-muted/40'
            : 'glass-card text-gray-600 hover:ring-brand/20',
    ])

    <x-site.action-panel :title="__('borrower.marketplace.filters')" open="filtersOpen">
        <form method="GET" action="{{ route($routeName) }}" class="grid gap-4" data-marketplace-filter-sheet>
            @if ($filters['q'] ?? null)
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
            @endif

            <div class="lg:hidden">
                <p class="text-xs font-semibold text-gray-700 mb-2">{{ __('borrower.marketplace.category_label') }}</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route($routeName, request()->except('category')) }}"
                       class="px-3 py-1.5 rounded-full text-xs font-semibold {{ empty($category) ? 'bg-brand text-white' : 'bg-gray-100 text-gray-700' }}">
                        {{ __('borrower.marketplace.all') }}
                    </a>
                    @foreach ($categories as $key => $label)
                        <a href="{{ route($routeName, array_merge(request()->except('category'), ['category' => $key])) }}"
                           class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $category === $key ? 'bg-brand text-white' : 'bg-gray-100 text-gray-700' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('borrower.marketplace.brand_model') }}</label>
                <input name="brand" value="{{ $filters['brand'] ?? '' }}" placeholder="e.g. Toyota" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-700 mb-2">{{ __('borrower.marketplace.price_range') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" inputmode="decimal" name="min_price" data-money-input="0" value="{{ \App\Support\MoneyFormat::forInput($filters['min_price'] ?? '') }}" placeholder="{{ __('borrower.marketplace.min_price') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    <input type="text" inputmode="decimal" name="max_price" data-money-input="0" value="{{ \App\Support\MoneyFormat::forInput($filters['max_price'] ?? '') }}" placeholder="{{ __('borrower.marketplace.max_price') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('borrower.marketplace.max_duration') }}</label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" name="tenure" value="{{ $filters['tenure'] ?? '' }}" maxlength="3" placeholder="e.g. 24" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="lg:hidden block text-xs font-semibold text-gray-700 mb-1">{{ __('borrower.marketplace.sort_by') }}</label>
                <select name="sort" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm bg-white lg:sr-only">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if (! empty($category))
                <input type="hidden" name="category" value="{{ $category }}">
            @endif
            <div class="sticky bottom-0 -mx-5 px-5 pt-3 pb-1 bg-white border-t border-gray-100 flex gap-2">
                <a href="{{ route($routeName, $category ? ['category' => $category] : []) }}"
                   class="flex-1 px-4 py-3 text-sm font-semibold text-center text-gray-700 rounded-xl ring-1 ring-gray-200">{{ __('borrower.marketplace.clear') }}</a>
                <button class="flex-1 bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-3 rounded-xl text-sm">{{ __('borrower.marketplace.apply_filters') }}</button>
            </div>
        </form>
    </x-site.action-panel>
</div>
