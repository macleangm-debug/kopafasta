<x-site.borrower-layout :title="brand_title($asset['title'])" active="marketplace" content-width="wide">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace') }}" data-kf-motion="pop" class="text-xs text-gray-500 hover:text-gray-700">{{ __('borrower.marketplace.back_to_marketplace') }}</a>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid lg:grid-cols-12 gap-6 lg:gap-10">
        <div class="lg:col-span-7">
            @include('site.marketplace._photo-slider', [
                'photos' => $asset['photos'] ?? [],
                'category' => $asset['category'] ?? 'other',
                'zoom' => true,
                'share' => ! empty($asset['id']) ? 'kf-mp-'.$asset['id'] : null,
            ])
        </div>

        <div class="lg:col-span-5">
            <div class="kf-premium-panel rounded-2xl p-4 sm:p-5 mb-5">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ config('asset_marketplace.categories.'.$asset['category']) }}</p>
                <h1 class="text-2xl font-bold mt-1 tracking-tight">{{ $asset['title'] }}</h1>
                @if (! empty($asset['vendor']))
                    <p class="text-sm text-white/80 mt-2">
                        <span class="font-semibold text-white">{{ $asset['vendor'] }}</span>
                        @if (! empty($asset['supplier_region']) || ! empty($asset['city']))
                            <span> · {{ $asset['city'] ?? $asset['supplier_region'] }}</span>
                        @endif
                    </p>
                @endif
            </div>
            @if (! empty($asset['description']))
                <p class="text-sm text-gray-800 mt-4 leading-relaxed">{{ $asset['description'] }}</p>
            @endif

            <div class="mt-6">
                @include('site.marketplace._financing-summary', ['asset' => $asset])
            </div>
            <div class="mt-4" id="apply">
                @if ($reservation)
                    <a href="{{ route('site.borrower.marketplace.reserve', $asset['id']) }}"
                       class="flex w-full items-center justify-center bg-brand-gold hover:brightness-95 text-brand font-semibold px-6 py-3 rounded-xl text-sm shadow-sm">
                        {{ __('borrower.marketplace.continue_application') }}
                    </a>
                @else
                    <form method="POST" action="{{ route('site.borrower.marketplace.apply', $asset['id']) }}" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <button type="submit" :disabled="submitting"
                                class="w-full bg-brand-gold hover:brightness-95 disabled:opacity-70 text-brand font-semibold px-6 py-3 rounded-xl text-sm shadow-sm">
                            <span x-show="!submitting">{{ __('borrower.marketplace.request_expand') }}</span>
                            <span x-show="submitting" x-cloak>{{ __('borrower.marketplace.request_expand') }}…</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @include('site.marketplace._related-assets', ['assets' => $relatedAssets ?? collect(), 'authenticated' => true])

</x-site.borrower-layout>
