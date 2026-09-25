<x-site.supplier-layout :title="$asset->title" active="assets" :hero="false">
    @php
        $buyers = (int) ($asset->request_count ?? 0);
        $active = (int) ($asset->active_count ?? 0);
        $listed = (bool) ($asset->is_active ?? false);
    @endphp

    <section class="kf-premium-panel rounded-3xl mb-5">
        <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <div class="relative p-5 sm:p-6">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.supplier_portal.asset_summary_eyebrow') }}</p>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ $asset->title }}</h1>
                    <p class="text-sm text-white/70 mt-1 font-mono">{{ $asset->asset_number ?: ($asset->slug ?: '—') }}</p>
                    <p class="text-sm text-white/80 mt-2 max-w-lg">{{ __('site.supplier_portal.asset_summary_blurb') }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('site.supplier.assets.edit', $asset->id) }}"
                           class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold px-4 py-2.5 hover:bg-yellow-400 shadow-md text-sm">
                            {{ __('site.supplier_portal.asset_edit') }}
                        </a>
                        <a href="{{ route('site.supplier.requests') }}"
                           class="inline-flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 text-sm">
                            {{ __('site.supplier_portal.asset_view_buyers') }}
                        </a>
                        <a href="{{ route('site.marketplace.show', $asset->slug ?: $asset->id) }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 text-sm">
                            {{ __('site.supplier_portal.market_view') }}
                        </a>
                    </div>
                </div>
                <div class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-5 py-4 min-w-[12rem] shrink-0">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.supplier_portal.col_activity') }}</p>
                    <p class="text-lg font-extrabold mt-1">
                        {{ __('site.supplier_portal.asset_activity_meta', [
                            'requests' => $buyers,
                            'active' => $active,
                        ]) }}
                    </p>
                    <p class="text-xs text-white/70 mt-1">
                        {{ $listed ? __('site.supplier_portal.asset_listed_live') : __('site.supplier_portal.asset_listed_hidden') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white p-3 sm:p-4">
            @if (marketplace_photo_urls($asset->photos ?? []) !== [])
                @include('site.marketplace._photo-slider', ['photos' => $asset->photos ?? [], 'category' => $asset->category ?? 'other'])
            @else
                <x-site.empty-state
                    compact
                    icon="📷"
                    :title="__('site.supplier_portal.asset_photos_empty_title')"
                    :description="__('site.supplier_portal.asset_photos_empty_desc')"
                    :action-label="__('site.supplier_portal.asset_edit')"
                    :action-url="route('site.supplier.assets.edit', $asset->id)"
                />
            @endif
        </div>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    [__('borrower.marketplace.deposit'), format_money($quote['customer_deposit_due'] ?? $asset->customer_deposit)],
                    [__('borrower.marketplace.loan_amount'), format_money($quote['financed_amount'] ?? 0)],
                    [__('site.supplier_portal.stat_assets'), $listed ? __('site.supplier_portal.asset_listed_live') : __('site.supplier_portal.asset_listed_hidden')],
                    [__('site.supplier_portal.quick_buyers'), (string) $buyers],
                ] as [$label, $value])
                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 p-4">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                        <p class="text-lg font-extrabold text-brand tabular-nums mt-1">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
            <section class="kf-premium-panel rounded-2xl">
                <div class="relative p-5">
                    <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.supplier_portal.asset_next_title') }}</p>
                    <p class="text-sm text-white/85 mt-2 leading-relaxed">
                        {{ $buyers > 0
                            ? __('site.supplier_portal.asset_next_has_buyers', ['count' => $buyers])
                            : __('site.supplier_portal.asset_next_no_buyers') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($buyers > 0)
                            <a href="{{ route('site.supplier.requests') }}"
                               class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-3 py-1.5 text-xs">
                                {{ __('site.supplier_portal.asset_view_buyers') }}
                            </a>
                        @else
                            <a href="{{ route('site.supplier.assets.edit', $asset->id) }}"
                               class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-3 py-1.5 text-xs">
                                {{ __('site.supplier_portal.asset_edit') }}
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-site.supplier-layout>
