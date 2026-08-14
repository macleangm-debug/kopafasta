@props([
    'categories' => [],
    'category' => null,
    'routeName' => 'site.marketplace',
    'activeClass' => 'bg-brand text-white',
    'inactiveClass' => 'glass-card text-gray-600 hover:ring-brand/20',
])

@php
    $baseParams = request()->except('category');
    $activeLabel = $category ? ($categories[$category] ?? $category) : __('borrower.marketplace.all');
@endphp

<div class="mb-6" x-data="{ categoriesOpen: false }">
    {{-- Mobile: category picker --}}
    <div class="lg:hidden flex items-center gap-2 mb-4">
        <button type="button" @click="categoriesOpen = true"
                class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition min-w-0 flex-1">
            <svg class="w-4 h-4 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h6"/></svg>
            <span class="truncate">{{ $activeLabel }}</span>
            <svg class="w-4 h-4 text-gray-400 shrink-0 ml-auto" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>
    </div>

    {{-- Desktop: inline pills --}}
    <div class="hidden lg:flex flex-wrap gap-2">
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

    <x-site.bottom-sheet :title="__('borrower.marketplace.categories')" open="categoriesOpen">
        <div class="grid gap-2">
            <a href="{{ route($routeName, $baseParams) }}" data-kf-motion="tab" @click="categoriesOpen = false"
               class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition {{ empty($category) ? 'bg-brand text-white' : 'bg-gray-50 text-gray-800 hover:bg-brand-muted/40' }}">
                {{ __('borrower.marketplace.all') }}
                @if (empty($category))
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                @endif
            </a>
            @foreach ($categories as $key => $label)
                <a href="{{ route($routeName, array_merge($baseParams, ['category' => $key])) }}" data-kf-motion="tab" @click="categoriesOpen = false"
                   class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition {{ $category === $key ? 'bg-brand text-white' : 'bg-gray-50 text-gray-800 hover:bg-brand-muted/40' }}">
                    {{ $label }}
                    @if ($category === $key)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                    @endif
                </a>
            @endforeach
        </div>
    </x-site.bottom-sheet>
</div>
