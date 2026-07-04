@props([
    'filters' => [],
    'category' => null,
    'routeName' => 'site.borrower.marketplace',
    'formClass' => '',
    'stacked' => false,
])

<form method="GET" action="{{ route($routeName) }}" class="{{ $formClass }}">
    @if ($category)
        <input type="hidden" name="category" value="{{ $category }}">
    @endif
    <div class="{{ $stacked ? '' : 'lg:col-span-2' }}">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.search') }}</label>
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search title, supplier…" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Brand / model</label>
        <input name="brand" value="{{ $filters['brand'] ?? '' }}" placeholder="e.g. Toyota" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.min_price') }}</label>
        <input type="text" inputmode="decimal" name="min_price" data-money-input="0" value="{{ \App\Support\MoneyFormat::forInput($filters['min_price'] ?? '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.max_price') }}</label>
        <input type="text" inputmode="decimal" name="max_price" data-money-input="0" value="{{ \App\Support\MoneyFormat::forInput($filters['max_price'] ?? '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.max_tenure') }}</label>
        <input type="text" inputmode="numeric" pattern="[0-9]*" name="tenure" value="{{ $filters['tenure'] ?? '' }}" maxlength="3" placeholder="e.g. 24" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Sort by</label>
        <select name="sort" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            @foreach (['title' => 'Title', 'price_asc' => 'Price (low)', 'price_desc' => 'Price (high)', 'deposit_asc' => 'Deposit (low)'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['sort'] ?? 'title') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="{{ $stacked ? 'flex flex-col gap-2 pt-2' : 'sm:col-span-2 lg:col-span-6 flex gap-2' }}">
        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-3 rounded-xl text-sm {{ $stacked ? 'w-full' : '' }}">{{ __('borrower.marketplace.apply_filters') }}</button>
        <a href="{{ route($routeName, $category ? ['category' => $category] : []) }}" class="px-4 py-3 text-sm text-center text-gray-600 hover:underline {{ $stacked ? '' : '' }}">{{ __('borrower.marketplace.clear') }}</a>
    </div>
</form>
