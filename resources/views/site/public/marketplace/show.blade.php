<x-site.layout :title="brand_title($asset['title'])">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        <a href="{{ route('site.marketplace') }}" class="text-sm font-semibold text-brand hover:underline inline-flex items-center gap-1">
            ← {{ __('borrower.marketplace.back_to_marketplace') }}
        </a>

        <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 mt-6">
            <div>
                @include('site.marketplace._photo-slider', ['photos' => $asset['photos'] ?? [], 'category' => $asset['category'] ?? 'other', 'zoom' => true])
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-bold">{{ config('asset_marketplace.categories.'.$asset['category']) }}</p>
                <h1 class="text-3xl sm:text-4xl font-bold mt-2 text-gray-900 tracking-tight">{{ $asset['title'] }}</h1>
                @if (! empty($asset['vendor']))
                    <p class="text-sm text-gray-700 mt-3">
                        {{ __('borrower.marketplace.supplier') }}: <span class="font-semibold text-gray-900">{{ $asset['vendor'] }}</span>
                        @if (! empty($asset['supplier_region']))
                            <span class="text-gray-600">· {{ $asset['supplier_region'] }}</span>
                        @endif
                    </p>
                @endif

                @if (! empty($asset['description']))
                    <div class="mt-6 glass-card p-5">
                        <h2 class="text-xs uppercase tracking-widest text-brand font-bold mb-2">{{ __('site.product_detail.overview') }}</h2>
                        <p class="text-sm text-gray-800 leading-relaxed">{{ $asset['description'] }}</p>
                    </div>
                @endif

                <div class="mt-6">
                    <h2 class="text-xs uppercase tracking-widest text-brand font-bold mb-3">{{ __('site.marketplace.financing') }}</h2>
                    <div class="glass-card divide-y divide-gray-200 overflow-hidden ring-1 ring-brand/10">
                        <div class="grid grid-cols-2 divide-x divide-gray-200">
                            <div class="p-4">
                                <p class="text-[11px] uppercase tracking-wide text-gray-700 font-semibold">{{ __('borrower.marketplace.asset_value') }}</p>
                                <p class="text-lg font-bold mt-1.5 tabular-nums text-gray-900">{{ format_money($asset['asset_value'] ?? 0, false, 0) }}</p>
                            </div>
                            <div class="p-4">
                                <p class="text-[11px] uppercase tracking-wide text-gray-700 font-semibold">{{ __('borrower.marketplace.deposit') }}</p>
                                <p class="text-lg font-bold mt-1.5 text-brand tabular-nums">{{ format_money($asset['deposit'], false, 0) }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 divide-x divide-gray-200">
                            <div class="p-4">
                                <p class="text-[11px] uppercase tracking-wide text-gray-700 font-semibold">{{ __('borrower.marketplace.loan_amount') }}</p>
                                <p class="text-lg font-bold mt-1.5 tabular-nums text-gray-900">{{ format_money($asset['remaining_loan'] ?? 0, false, 0) }}</p>
                            </div>
                            <div class="p-4 bg-brand-muted/40">
                                <p class="text-[11px] uppercase tracking-wide text-brand font-semibold">{{ __('borrower.marketplace.weekly_installment') }}</p>
                                <p class="text-lg font-bold mt-1.5 tabular-nums text-gray-900">{{ format_money($asset['weekly_installment'], false, 0) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                @if (! empty($asset['max_tenure_months']) || ! empty($asset['serial_number']) || ! empty($asset['chassis_number']))
                    <div class="mt-6 glass-card p-5">
                        <h2 class="text-xs uppercase tracking-widest text-brand font-bold mb-3">{{ __('site.marketplace.specifications') }}</h2>
                        <dl class="space-y-2.5 text-sm">
                            @if (! empty($asset['max_tenure_months']))
                                <div class="flex justify-between gap-3">
                                    <dt class="text-gray-700 font-medium">{{ __('borrower.marketplace.duration_range_label') }}</dt>
                                    <dd class="font-bold text-gray-900">{{ __('borrower.marketplace.duration_range', ['min' => 1, 'max' => (int) $asset['max_tenure_months']]) }}</dd>
                                </div>
                            @endif
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
