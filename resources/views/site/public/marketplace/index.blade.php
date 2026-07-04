<x-site.layout :title="brand_title(__('borrower.marketplace.title'))">
    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-15 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 lg:py-16">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-widest text-brand-gold mb-2">{{ __('site.marketplace.browse') }}</p>
                    <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">{{ __('borrower.marketplace.title') }}</h1>
                    <p class="text-white/80 mt-3 max-w-xl">{{ __('borrower.marketplace.subtitle') }}</p>
                </div>
                @guest
                    <a href="{{ route('site.login', ['redirect' => route('site.marketplace')]) }}" class="glass-card-dark text-white font-semibold px-6 py-3 rounded-xl text-sm hover:bg-white/10 transition">
                        {{ __('borrower.marketplace.public_login_cta') }}
                    </a>
                @else
                    <a href="{{ route('site.borrower.marketplace') }}" class="bg-brand-gold hover:bg-yellow-400 text-brand font-semibold px-6 py-3 rounded-xl text-sm transition">
                        {{ __('borrower.marketplace.public_my_marketplace_cta') }}
                    </a>
                @endguest
            </div>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @include('site.marketplace._category-filters', [
            'categories' => $categories,
            'category' => $category,
            'routeName' => 'site.marketplace',
        ])

        @include('site.marketplace._filters', ['filters' => $filters, 'category' => $category, 'routeName' => 'site.marketplace'])

        @if ($assets->isEmpty())
            <div class="glass-card border-dashed p-16 text-center text-gray-500">
                <p class="text-5xl mb-4">🏷️</p>
                <p class="font-semibold text-lg">{{ __('borrower.marketplace.empty_title') }}</p>
                <p class="text-sm mt-2">{{ __('borrower.marketplace.empty_desc') }}</p>
            </div>
        @else
            <div x-data="{ ready: false }" x-init="requestAnimationFrame(() => ready = true)">
                <div x-show="!ready" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-6">
                    @for ($i = 0; $i < min(6, $assets->count()); $i++)
                        <x-site.skeleton-card />
                    @endfor
                </div>
                <div x-show="ready" x-cloak class="grid sm:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach ($assets as $asset)
                        @include('site.marketplace._asset-card', [
                            'asset' => $asset,
                            'categories' => $categories,
                            'showUrl' => route('site.marketplace.show', $asset['id']),
                            'authenticated' => false,
                        ])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-site.layout>
