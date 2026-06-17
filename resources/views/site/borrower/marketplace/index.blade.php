<x-site.borrower-layout :title="brand_title(__('borrower.marketplace.title'))" active="marketplace" content-width="wide">

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.marketplace.title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ __('borrower.marketplace.subtitle') }}</p>

    {{-- Find what you need (collapsed) --}}
    <div class="mb-8 bg-gradient-to-br from-amber-50 to-white rounded-2xl border border-amber-100 overflow-hidden" x-data="{ requestOpen: false }">
        <button type="button" @click="requestOpen = !requestOpen" class="w-full text-left p-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-lg">{{ __('borrower.marketplace.request_collapsed_title') }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ __('borrower.marketplace.find_subtitle') }}</p>
            </div>
            <span class="shrink-0 text-sm font-semibold text-amber-700" x-text="requestOpen ? '−' : '+'"></span>
        </button>
        <form x-show="requestOpen" x-cloak method="POST" action="{{ route('site.borrower.marketplace.request') }}" enctype="multipart/form-data" class="px-6 pb-6 grid sm:grid-cols-2 gap-4 border-t border-amber-100 pt-4">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.asset_name') }}</label>
                <input name="asset_name" required placeholder="e.g. Toyota Hilux 2019" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.budget') }}</label>
                <input type="number" name="budget" min="0" step="1000" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.tenure') }}</label>
                <input type="number" name="preferred_tenure_months" min="1" max="120" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.photo') }}</label>
                <input type="file" name="photo" accept="image/*" capture="environment" class="w-full text-sm">
            </div>
            <div class="sm:col-span-2">
                <button class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.marketplace.submit_request') }}</button>
            </div>
        </form>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('site.borrower.marketplace', request()->except('category')) }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium {{ empty($category) ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
            {{ __('borrower.marketplace.all') }}
        </a>
        @foreach ($categories as $key => $label)
            <a href="{{ route('site.borrower.marketplace', array_merge(request()->except('category'), ['category' => $key])) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $category === $key ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @include('site.marketplace._filters', ['filters' => $filters, 'category' => $category, 'routeName' => 'site.borrower.marketplace'])

    @if ($assets->isEmpty())
        <x-site.empty-state icon="🏷️" :title="__('borrower.marketplace.empty_title')" :description="__('borrower.marketplace.empty_desc')" />
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($assets as $asset)
                @include('site.marketplace._asset-card', [
                    'asset' => $asset,
                    'categories' => $categories,
                    'showUrl' => route('site.borrower.marketplace.show', $asset['id']),
                    'applyUrl' => route('site.borrower.marketplace.show', $asset['id']).'#apply',
                    'authenticated' => true,
                ])
            @endforeach
        </div>
    @endif

</x-site.borrower-layout>
