<x-site.layout :title="brand_title($asset['title'])">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        <a href="{{ route('site.marketplace') }}" class="text-sm font-semibold text-brand hover:underline inline-flex items-center gap-1">
            ← {{ __('borrower.marketplace.back_to_marketplace') }}
        </a>

        <div class="w-full max-w-full space-y-4 lg:grid lg:grid-cols-12 lg:gap-10 lg:space-y-0 mt-6">
            <div class="w-full min-w-0 lg:col-span-7">
                @include('site.marketplace._photo-slider', [
                    'photos' => $asset['photos'] ?? [],
                    'category' => $asset['category'] ?? 'other',
                    'zoom' => true,
                    'share' => ! empty($asset['id']) ? 'kf-mp-'.$asset['id'] : null,
                    'durationMonths' => $asset['max_tenure_months'] ?? null,
                    'priceAmount' => $asset['asset_value'] ?? null,
                ])
            </div>
            <div class="w-full min-w-0 lg:col-span-5">
                <div class="kf-premium-panel rounded-2xl p-4 sm:p-5 mb-5">
                    <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ $asset['category_label'] ?? marketplace_category_label($asset['category'] ?? null) }}</p>
                    <h1 class="text-2xl sm:text-3xl font-bold mt-1 tracking-tight">{{ marketplace_plain($asset['title'] ?? '') }}</h1>
                    @if (! empty($asset['asset_number']))
                        <p class="text-xs font-mono text-white/70 mt-1">{{ __('borrower.marketplace.asset_id') }} {{ $asset['asset_number'] }}</p>
                    @endif
                    @if (! empty($asset['vendor']))
                        <p class="text-sm text-white/80 mt-2">
                            <span class="font-semibold text-white">{{ marketplace_plain($asset['vendor']) }}</span>
                            @if (! empty($asset['supplier_region']) || ! empty($asset['city']))
                                <span> · {{ marketplace_plain($asset['city'] ?? $asset['supplier_region']) }}</span>
                            @endif
                        </p>
                    @endif
                </div>

                @if (! empty($asset['description']))
                    <div class="mt-6 glass-card p-5">
                        <h2 class="text-xs uppercase tracking-widest text-brand font-bold mb-2">{{ __('site.product_detail.overview_heading') }}</h2>
                        <p class="text-sm text-gray-800 leading-relaxed">{{ marketplace_plain($asset['description'] ?? '') }}</p>
                    </div>
                @endif

                <div class="mt-6">
                    <h2 class="text-xs uppercase tracking-widest text-brand font-bold mb-3">{{ __('site.marketplace.financing') }}</h2>
                    @include('site.marketplace._financing-summary', ['asset' => $asset])
                </div>

                @if (! empty($asset['serial_number']) || ! empty($asset['chassis_number']))
                    <div class="mt-6 glass-card p-5">
                        <h2 class="text-xs uppercase tracking-widest text-brand font-bold mb-3">{{ __('site.marketplace.specifications') }}</h2>
                        <dl class="space-y-2.5 text-sm">
                            @if (! empty($asset['serial_number']))
                                <div class="flex justify-between gap-3"><dt class="text-gray-700 font-medium">Serial</dt><dd class="font-mono text-xs font-semibold text-gray-900">{{ $asset['serial_number'] }}</dd></div>
                            @endif
                            @if (! empty($asset['chassis_number']))
                                <div class="flex justify-between gap-3"><dt class="text-gray-700 font-medium">Chassis</dt><dd class="font-mono text-xs font-semibold text-gray-900">{{ $asset['chassis_number'] }}</dd></div>
                            @endif
                        </dl>
                    </div>
                @endif

                <div class="mt-8 glass-card p-6 ring-2 ring-brand/15">
                    <p class="text-sm text-gray-800 mb-4 leading-relaxed">{{ __('borrower.marketplace.public_apply_hint') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ $loginUrl }}" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-xl text-sm transition">
                            {{ __('borrower.marketplace.public_apply_login') }}
                        </a>
                        <a href="{{ $registerUrl }}" class="inline-flex ring-1 ring-brand/30 text-brand font-semibold px-6 py-3 rounded-xl text-sm hover:bg-brand-muted transition">
                            {{ __('borrower.marketplace.public_apply_register') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @include('site.marketplace._related-assets', ['assets' => $relatedAssets ?? collect(), 'authenticated' => false])
    </div>
</x-site.layout>
