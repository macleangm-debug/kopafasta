<x-site.borrower-layout :title="brand_title(__('borrower.marketplace.title'))" active="marketplace" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.nav.marketplace')"
        :title="__('borrower.marketplace.title')"
        :subtitle="__('borrower.marketplace.subtitle')"
    />

    {{-- Find what you need (collapsed) --}}
    @php
        $pendingRequest = session('pending_asset_request', []);
        $openRequest = request()->boolean('request') || filled($pendingRequest);
    @endphp
    <div class="mb-8 kf-premium-panel rounded-2xl overflow-hidden" x-data="{ requestOpen: @js($openRequest) }">
        <button type="button" @click="requestOpen = !requestOpen" class="relative w-full text-left p-5 sm:p-6 flex items-center justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('borrower.nav.marketplace') }}</p>
                <h2 class="mt-2 text-lg sm:text-xl font-extrabold tracking-tight text-white">{{ __('borrower.marketplace.request_collapsed_title') }}</h2>
                <p class="text-sm text-white/80 mt-1 max-w-xl">{{ __('borrower.marketplace.find_subtitle') }}</p>
            </div>
            <span class="relative shrink-0 size-10 rounded-full bg-white/10 ring-1 ring-white/25 text-brand-gold text-xl font-bold grid place-items-center" x-text="requestOpen ? '−' : '+'"></span>
        </button>
        <form x-show="requestOpen" x-cloak method="POST" action="{{ route('site.borrower.marketplace.request') }}" class="relative mx-5 sm:mx-6 mb-5 grid gap-4 rounded-2xl bg-white p-4 sm:p-5">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.asset_name') }}</label>
                <input name="asset_name" required value="{{ old('asset_name', $pendingRequest['asset_name'] ?? '') }}" placeholder="e.g. Toyota Hilux 2019" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand">
            </div>
            <div>
                <button class="bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">{{ __('borrower.marketplace.submit_request') }}</button>
            </div>
        </form>
    </div>

    @include('site.marketplace._category-filters', [
        'categories' => $categories,
        'category' => $category,
        'routeName' => 'site.borrower.marketplace',
        'activeClass' => 'bg-brand text-white',
        'inactiveClass' => 'bg-white ring-1 ring-gray-200/80 text-gray-600 hover:bg-brand-muted/40',
    ])

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
