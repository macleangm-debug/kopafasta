<x-site.supplier-layout :title="__('site.supplier_portal.dashboard_title')" active="dashboard">
    @php
        $stats = $stats ?? ['assets' => 0, 'active_financed' => 0, 'available' => 0, 'pending' => 0];
        $attention = $attention ?? [];
        $recentPayments = $recentPayments ?? collect();
        $assetActivity = $assetActivity ?? collect();
    @endphp

    <section class="kf-premium-panel rounded-3xl mb-5">
        <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <div class="relative p-5 sm:p-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.supplier_portal.title') }}</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ $vendor->name }}</h1>
                <p class="text-sm text-white/70 mt-1 font-mono">{{ $vendor->vendor_number ?? $vendor->partner_number ?? 'PTR' }}</p>
                <p class="text-sm text-white/80 mt-2 max-w-lg">{{ __('site.supplier_portal.hero_blurb') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('site.supplier.assets.create') }}"
                       class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold px-4 py-2.5 hover:bg-yellow-400 shadow-md text-sm">
                        {{ __('site.supplier_portal.cta_upload') }}
                    </a>
                    <a href="{{ route('site.supplier.profile', ['section' => 'card']) }}"
                       class="inline-flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 text-sm">
                        {{ __('site.supplier_portal.nav_card') }}
                    </a>
                    <a href="{{ route('site.supplier.settlements') }}"
                       class="inline-flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 text-sm">
                        {{ __('site.supplier_portal.cta_settlements') }}
                    </a>
                </div>
            </div>
            <a href="{{ route('site.supplier.settlements') }}"
               class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-5 py-4 min-w-[12rem] shrink-0">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.partner_portal.wallet_available') }}</p>
                <p class="text-2xl font-extrabold tabular-nums mt-1">{{ format_money($stats['available'] ?? 0) }}</p>
                <p class="text-xs text-white/70 mt-1">{{ __('site.partner_portal.wallet_withdraw_hint') }}</p>
            </a>
        </div>
    </section>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach ([
            [__('site.supplier_portal.stat_assets'), (int) $stats['assets'], route('site.supplier.assets')],
            [__('site.supplier_portal.stat_active_financed'), (int) $stats['active_financed'], route('site.supplier.requests')],
            [__('site.supplier_portal.stat_available'), format_money($stats['available'] ?? 0), route('site.supplier.settlements')],
            [__('site.supplier_portal.stat_pending'), format_money($stats['pending'] ?? 0), route('site.supplier.settlements')],
        ] as [$label, $value, $url])
            <a href="{{ $url }}" class="glass-card rounded-2xl ring-1 ring-brand/15 p-4 hover:ring-brand/30 transition">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-brand tabular-nums mt-1">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <section class="mb-6">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('site.supplier_portal.quick_actions_title') }}</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
            @foreach ([
                [__('site.supplier_portal.quick_upload'), route('site.supplier.assets.create'), '➕'],
                [__('site.supplier_portal.quick_buyers'), route('site.supplier.requests'), '👥'],
                [__('site.supplier_portal.quick_payments'), route('site.supplier.settlements'), '💸'],
                [__('site.supplier_portal.quick_profile'), route('site.supplier.profile'), '👤'],
            ] as [$label, $url, $icon])
                <a href="{{ $url }}"
                   class="group flex flex-col items-center gap-2 rounded-2xl glass-card px-2 py-4 text-center ring-1 ring-gray-200/80 hover:ring-brand/30 hover:shadow-sm transition">
                    <span class="text-2xl leading-none group-hover:scale-110 transition-transform" aria-hidden="true">{{ $icon }}</span>
                    <span class="text-[11px] sm:text-xs font-semibold text-gray-800 leading-tight">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($attention !== [])
        <section class="kf-premium-panel rounded-3xl mb-6">
            <div class="relative p-5 sm:p-6 space-y-3">
                @foreach ($attention as $item)
                    <a href="{{ $item['url'] }}"
                       class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl bg-white/10 ring-1 ring-white/20 px-4 py-3 hover:bg-white/15 transition">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-white">{{ $item['title'] }}</p>
                            <p class="text-xs text-white/75 mt-0.5">{{ $item['body'] }}</p>
                        </div>
                        <span class="inline-flex items-center justify-center rounded-lg bg-brand-gold text-brand font-bold px-3 py-1.5 text-xs shrink-0">
                            {{ $item['cta'] }} →
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $recentPayments = $recentPayments->take(5);
        $assetActivity = $assetActivity->take(5);
    @endphp
    <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-1 -mx-1 px-1 scrollbar-none lg:grid lg:grid-cols-2 lg:overflow-visible lg:pb-0 lg:mx-0 lg:px-0"
         data-kf-supplier-home-rail>
        <section class="mb-2 min-w-[85%] snap-center shrink-0 lg:min-w-0 lg:mb-0 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5 flex items-center justify-between gap-3">
                <h2 class="font-bold text-white">{{ __('site.supplier_portal.recent_payments_title') }}</h2>
                <a href="{{ route('site.supplier.settlements') }}"
                   class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-3 py-1.5 text-xs shadow-sm">
                    {{ __('site.supplier_portal.see_all') }}
                </a>
            </div>
            @if ($recentPayments->isEmpty())
                <x-site.empty-state
                    class="!shadow-none !ring-0"
                    compact
                    icon="💸"
                    :title="__('site.supplier_portal.recent_payments_empty_title')"
                    :description="__('site.supplier_portal.recent_payments_empty_desc')"
                />
            @else
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                            <tr>
                                <th class="px-5 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_date') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_asset') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_reference') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_principal') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($recentPayments as $payment)
                                <tr>
                                    <td class="px-5 py-3 tabular-nums">{{ optional($payment->created_at)->format('d M Y') }}</td>
                                    <td class="px-5 py-3">{{ $payment->description ?: '—' }}</td>
                                    <td class="px-5 py-3 font-mono text-xs">{{ $payment->invoice_number }}</td>
                                    <td class="px-5 py-3 font-semibold tabular-nums">{{ format_money($payment->amount) }}</td>
                                    <td class="px-5 py-3">{{ ucfirst($payment->status) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="sm:hidden divide-y divide-gray-100">
                    @foreach ($recentPayments as $payment)
                        <div class="px-4 py-3">
                            <p class="text-sm font-semibold text-gray-900">{{ format_money($payment->amount) }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ optional($payment->created_at)->format('d M Y') }} · {{ ucfirst($payment->status) }}</p>
                            <p class="text-xs text-gray-600 mt-1">{{ $payment->description ?: $payment->invoice_number }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="mb-2 min-w-[85%] snap-center shrink-0 lg:min-w-0 lg:mb-0 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5 flex items-center justify-between gap-3">
                <h2 class="font-bold text-white">{{ __('site.supplier_portal.asset_activity_title') }}</h2>
                <a href="{{ route('site.supplier.assets') }}"
                   class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-3 py-1.5 text-xs shadow-sm">
                    {{ __('site.supplier_portal.see_all') }}
                </a>
            </div>
            @if ($assetActivity->isEmpty())
                <x-site.empty-state
                    class="!shadow-none !ring-0"
                    compact
                    icon="📦"
                    :title="__('site.supplier_portal.asset_activity_empty_title')"
                    :description="__('site.supplier_portal.asset_activity_empty_desc')"
                    :action-label="__('site.supplier_portal.cta_upload')"
                    :action-url="route('site.supplier.assets.create')"
                />
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($assetActivity as $asset)
                        <a href="{{ route('site.supplier.assets.show', $asset) }}"
                           class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 hover:bg-brand-muted/20">
                            <p class="font-semibold text-sm text-gray-900 truncate">{{ $asset->title }}</p>
                            <p class="text-xs text-gray-500 shrink-0">
                                {{ __('site.supplier_portal.asset_activity_meta', [
                                    'requests' => (int) ($asset->request_count ?? 0),
                                    'active' => (int) ($asset->active_count ?? 0),
                                ]) }}
                            </p>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-site.supplier-layout>
