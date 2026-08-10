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
        @guest
        <div class="mb-8 glass-card overflow-hidden" x-data="{ requestOpen: false }">
            <button type="button" @click="requestOpen = !requestOpen" class="w-full text-left p-6 flex items-center justify-between gap-4 hover:bg-brand-muted/20 transition">
                <div class="flex items-start gap-3">
                    <span class="text-2xl" aria-hidden="true">🔍</span>
                    <div>
                        <h2 class="font-semibold text-lg">{{ __('borrower.marketplace.request_collapsed_title') }}</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ __('borrower.marketplace.find_subtitle') }}</p>
                    </div>
                </div>
                <span class="shrink-0 text-sm font-semibold text-brand" x-text="requestOpen ? '−' : '+'"></span>
            </button>
            <form x-show="requestOpen" x-cloak method="POST" action="{{ route('site.marketplace.request') }}" class="px-6 pb-6 grid gap-4 border-t border-gray-100/80 pt-4">
                @csrf
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/15 px-4 py-3 text-sm text-brand">
                    {{ __('borrower.marketplace.request_signup_hint') }}
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.asset_name') }}</label>
                    <input name="asset_name" required placeholder="e.g. Toyota Hilux 2019" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand">
                </div>
                <div>
                    <button class="w-full sm:w-auto bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('borrower.marketplace.request_signup_cta') }}</button>
                </div>
            </form>
        </div>
        @endguest

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
