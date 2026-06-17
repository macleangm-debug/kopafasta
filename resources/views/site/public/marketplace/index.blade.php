<x-site.layout :title="brand_title(__('borrower.marketplace.title'))">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.marketplace.public_eyebrow', ['brand' => brand_name()]) }}</p>
                <h1 class="text-3xl font-bold tracking-tight">{{ __('borrower.marketplace.title') }}</h1>
                <p class="text-sm text-gray-500 mt-2">{{ __('borrower.marketplace.subtitle') }}</p>
            </div>
            @guest
                <a href="{{ route('site.login', ['redirect' => route('site.marketplace')]) }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.marketplace.public_login_cta') }}
                </a>
            @else
                <a href="{{ route('site.borrower.marketplace') }}" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.marketplace.public_my_marketplace_cta') }}
                </a>
            @endguest
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <a href="{{ route('site.marketplace', request()->except('category')) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ empty($category) ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
                {{ __('borrower.marketplace.all') }}
            </a>
            @foreach ($categories as $key => $label)
                <a href="{{ route('site.marketplace', array_merge(request()->except('category'), ['category' => $key])) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $category === $key ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @include('site.marketplace._filters', ['filters' => $filters, 'category' => $category, 'routeName' => 'site.marketplace'])

        @if ($assets->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 p-12 text-center text-gray-500">
                <p class="text-4xl mb-3">🏷️</p>
                <p class="font-semibold">{{ __('borrower.marketplace.empty_title') }}</p>
                <p class="text-sm mt-1">{{ __('borrower.marketplace.empty_desc') }}</p>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($assets as $asset)
                    @include('site.marketplace._asset-card', [
                        'asset' => $asset,
                        'categories' => $categories,
                        'showUrl' => route('site.marketplace.show', $asset['id']),
                        'authenticated' => false,
                    ])
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>
