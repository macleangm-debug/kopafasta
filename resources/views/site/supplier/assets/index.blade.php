<x-site.supplier-layout title="Supplier assets" active="assets">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ __('site.supplier_portal.nav_assets') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('site.supplier_portal.hero_blurb') }}</p>
        </div>
        <a href="{{ route('site.supplier.assets.create') }}"
           data-kf-motion="push"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
            {{ __('site.supplier_portal.cta_upload') }}
        </a>
    </div>

    @if ($assets->isEmpty())
        <x-site.empty-state
            icon="📦"
            title="No assets uploaded yet"
            description="Add your first marketplace listing so borrowers can discover and reserve it."
            :action-label="__('site.supplier_portal.cta_upload')"
            :action-url="route('site.supplier.assets.create')"
        />
    @else
        <div class="hidden sm:block glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Title</th>
                        <th class="px-4 py-3 font-semibold">{{ __('borrower.marketplace.deposit') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('borrower.marketplace.loan_amount') }}</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($assets as $asset)
                        @php
                            $quote = app(\App\Services\AssetLendingService::class)->pricingQuoteFromAssetPrice((float) $asset->asset_value);
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $asset->title }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money($quote['customer_deposit_due'] ?? $asset->customer_deposit) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money($quote['financed_amount'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('site.supplier.assets.edit', $asset) }}" class="text-brand font-semibold hover:underline mr-3">Edit</a>
                                <a href="{{ route('site.marketplace.show', $asset->slug ?: $asset->id) }}" target="_blank" rel="noopener" class="text-brand font-semibold hover:underline">{{ __('site.supplier_portal.market_view') }} →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="sm:hidden space-y-2">
            @foreach ($assets as $asset)
                @php
                    $quote = app(\App\Services\AssetLendingService::class)->pricingQuoteFromAssetPrice((float) $asset->asset_value);
                @endphp
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3">
                    <p class="font-semibold text-gray-900">{{ $asset->title }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 tabular-nums">{{ format_money($quote['customer_deposit_due'] ?? $asset->customer_deposit) }} · {{ format_money($quote['financed_amount'] ?? 0) }}</p>
                    <div class="flex gap-4 mt-2 text-sm font-semibold">
                        <a href="{{ route('site.supplier.assets.edit', $asset) }}" class="text-brand">Edit</a>
                        <a href="{{ route('site.marketplace.show', $asset->slug ?: $asset->id) }}" class="text-brand">{{ __('site.supplier_portal.market_view') }} →</a>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $assets->links() }}</div>
    @endif
</x-site.supplier-layout>
