<x-site.supplier-layout :title="__('site.supplier_portal.dashboard_title')" active="dashboard">
    @php
        $hasAssignedRequests = (int) ($stats['requests'] ?? 0) > 0;
        $hasPendingPay = (float) ($stats['pending_pay'] ?? 0) > 0;
        $statCards = [];
        if ((int) ($stats['assets'] ?? 0) > 0) {
            $statCards[] = [__('site.supplier_portal.stat_assets'), $stats['assets'], route('site.supplier.assets'), 'text-brand'];
        }
        if ((int) ($stats['reservations'] ?? 0) > 0) {
            $statCards[] = [__('site.supplier_portal.stat_reservations'), $stats['reservations'], route('site.supplier.reservations'), 'text-brand'];
        }
        if ($hasAssignedRequests) {
            $statCards[] = [__('site.supplier_portal.stat_requests'), $stats['requests'], route('site.supplier.requests'), 'text-amber-600'];
        }
        if ($hasPendingPay) {
            $statCards[] = [__('site.supplier_portal.stat_pending_pay'), 'TZS '.format_number($stats['pending_pay']), route('site.supplier.settlements'), 'text-brand'];
        }
    @endphp

    <section class="kf-premium-panel rounded-3xl mb-6">
        <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <div class="relative p-6 sm:p-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.supplier_portal.title') }}</p>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mt-1">{{ $vendor->name }}</h1>
                <p class="text-sm text-white/70 mt-2 font-mono">{{ $vendor->vendor_number ?? $vendor->partner_number ?? 'PTR' }}</p>
                <p class="text-sm text-white/80 mt-3 max-w-lg">{{ __('site.supplier_portal.hero_blurb') }}</p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a href="{{ route('site.supplier.assets.create') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold px-5 py-3 hover:bg-yellow-400 shadow-md text-sm">
                    {{ __('site.supplier_portal.cta_upload') }}
                </a>
                <a href="{{ route('site.supplier.reservations') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-5 py-3 text-sm">
                    {{ __('site.supplier_portal.cta_reservations') }}
                </a>
                <a href="{{ route('site.supplier.settlements') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-5 py-3 text-sm">
                    {{ __('site.supplier_portal.cta_settlements') }}
                </a>
            </div>
        </div>
    </section>

    @if (count($statCards) > 0)
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            @foreach ($statCards as [$label, $value, $url, $color])
                <a href="{{ $url }}" class="glass-card rounded-2xl ring-1 ring-brand/15 p-5 hover:ring-brand/30 transition">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                    <p class="text-3xl font-extrabold {{ $color }} tabular-nums mt-1">{{ $value }}</p>
                </a>
            @endforeach
        </div>
    @endif

    @if (! $hasAssignedRequests)
        <div class="mb-6 rounded-2xl bg-gray-50 ring-1 ring-gray-100 px-4 py-6 text-center">
            <p class="text-sm text-gray-700 font-semibold">{{ __('site.supplier_portal.no_assigned_tasks') }}</p>
        </div>
    @endif

    @if (! $hasPendingPay && count($statCards) > 0)
        <div class="mb-6 rounded-2xl bg-gray-50 ring-1 ring-gray-100 px-4 py-4 text-center">
            <p class="text-sm text-gray-600">{{ __('site.supplier_portal.no_pending_payouts') }}</p>
        </div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            [__('site.supplier_portal.quick_upload'), route('site.supplier.assets.create'), __('site.supplier_portal.quick_upload_hint')],
            [__('site.supplier_portal.quick_payouts'), route('site.supplier.applications'), __('site.supplier_portal.quick_payouts_hint')],
            [__('site.supplier_portal.quick_requests'), route('site.supplier.requests'), __('site.supplier_portal.quick_requests_hint')],
            [__('site.supplier_portal.quick_profile'), route('site.supplier.profile'), __('site.supplier_portal.quick_profile_hint')],
        ] as [$label, $url, $hint])
            <a href="{{ $url }}" class="rounded-2xl bg-white ring-1 ring-gray-200 hover:ring-brand/30 px-4 py-4 transition">
                <p class="font-semibold text-sm text-gray-900">{{ $label }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $hint }}</p>
            </a>
        @endforeach
    </div>
</x-site.supplier-layout>
