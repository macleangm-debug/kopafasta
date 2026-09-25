<x-site.supplier-layout title="Supplier assets" active="assets">
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
                        <th class="px-4 py-3 font-semibold">{{ __('borrower.marketplace.asset_id') }}</th>
                        <th class="px-4 py-3 font-semibold">Title</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.col_activity') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('borrower.marketplace.deposit') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('borrower.marketplace.loan_amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($assets as $asset)
                        @php
                            $quote = app(\App\Services\AssetLendingService::class)->pricingQuoteFromAssetPrice((float) $asset->asset_value);
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $asset->asset_number ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('site.supplier.assets.show', $asset->id) }}" class="font-semibold text-brand hover:underline">{{ $asset->title }}</a>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                                {{ __('site.supplier_portal.asset_activity_meta', [
                                    'requests' => (int) ($asset->request_count ?? 0),
                                    'active' => (int) ($asset->active_count ?? 0),
                                ]) }}
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money($quote['customer_deposit_due'] ?? $asset->customer_deposit) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money($quote['financed_amount'] ?? 0) }}</td>
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
                <a href="{{ route('site.supplier.assets.show', $asset->id) }}" class="block rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3">
                    <p class="font-semibold text-gray-900">{{ $asset->title }}</p>
                    @if ($asset->asset_number)
                        <p class="text-[11px] font-mono text-gray-500 mt-0.5">{{ $asset->asset_number }}</p>
                    @endif
                    <p class="text-xs text-gray-600 mt-1">
                        {{ __('site.supplier_portal.asset_activity_meta', [
                            'requests' => (int) ($asset->request_count ?? 0),
                            'active' => (int) ($asset->active_count ?? 0),
                        ]) }}
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5 tabular-nums">{{ format_money($quote['customer_deposit_due'] ?? $asset->customer_deposit) }} · {{ format_money($quote['financed_amount'] ?? 0) }}</p>
                    <div class="flex gap-4 mt-2 text-sm font-semibold">
                        <span class="text-brand">{{ __('site.supplier_portal.asset_summary_open') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $assets->links() }}</div>
    @endif
</x-site.supplier-layout>
