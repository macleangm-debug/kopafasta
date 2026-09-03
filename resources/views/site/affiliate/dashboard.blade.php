<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.dashboard_title'))" active="dashboard">

    <x-site.borrower-dashboard-hero :hero="$hero" />

    @if ($attention ?? null)
        <section class="glass-card p-5 mb-6 ring-1 ring-amber-200 bg-amber-50/70">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-amber-800 font-semibold">{{ __('site.affiliate_portal.needs_attention') }}</p>
                    <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $attention['title'] }}</h2>
                    <p class="text-sm text-gray-700 mt-1">{{ $attention['body'] }}</p>
                </div>
                <a href="{{ $attention['cta_url'] }}" class="inline-flex justify-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm shrink-0">
                    {{ $attention['cta_label'] }} →
                </a>
            </div>
        </section>
    @endif

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <section class="glass-card p-6 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-gray-500">
                        {{ ($progress['premium'] ?? false) ? __('site.affiliate_portal.impact_title') : __('site.affiliate_portal.progress_title') }}
                    </p>
                    <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $standing['status_label'] ?? '' }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ $progress['days_remaining'] ?? 0 }} {{ __('site.affiliate_portal.days_remaining') }}</p>
                </div>
                <a href="{{ route('site.affiliate.performance') }}" class="text-sm font-semibold text-brand hover:underline">
                    {{ ($progress['premium'] ?? false) ? __('site.affiliate_portal.view_impact') : __('site.affiliate_portal.view_performance') }} →
                </a>
            </div>
            @if ($progress['premium'] ?? false)
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ([
                        'visited' => __('site.affiliate_portal.impact_visited'),
                        'registered' => __('site.affiliate_portal.impact_registered'),
                        'applied' => __('site.affiliate_portal.impact_applied'),
                        'qualifying' => __('site.affiliate_portal.impact_qualifying'),
                    ] as $key => $label)
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                            <p class="text-xs text-gray-500">{{ $label }}</p>
                            <p class="text-lg font-bold tabular-nums">{{ $impact[$key] ?? 0 }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($standing['kpi_results'] ?? [] as $kpi)
                        @if ($kpi['enabled'] ?? false)
                            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                                <p class="text-xs text-gray-500">{{ $kpi['label'] }}</p>
                                <p class="text-lg font-bold tabular-nums">
                                    {{ $kpi['key'] === 'conversion' ? number_format($kpi['actual'], 1).'%' : number_format($kpi['actual'], 0) }}
                                    <span class="text-sm font-medium text-gray-500">/ {{ $kpi['key'] === 'conversion' ? number_format($kpi['target'], 0).'%' : number_format($kpi['target'], 0) }}</span>
                                    <span class="text-sm">{{ $kpi['met'] ? '✓' : '' }}</span>
                                </p>
                                @if (! $kpi['met'] && ($kpi['target'] ?? 0) > ($kpi['actual'] ?? 0))
                                    <p class="text-xs text-gray-500 mt-1">{{ __('site.affiliate_portal.more_needed', ['count' => (int) ceil($kpi['target'] - $kpi['actual'])]) }}</p>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        <section class="glass-card p-6 space-y-4">
            <p class="text-[11px] uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.funnel_title') }}</p>
            <div class="grid grid-cols-2 gap-3 text-sm">
                @foreach ([
                    'visited' => __('site.affiliate_portal.funnel_visited'),
                    'registered' => __('site.affiliate_portal.funnel_registered'),
                    'applied' => __('site.affiliate_portal.funnel_applied'),
                    'approved' => __('site.affiliate_portal.funnel_approved'),
                    'qualifying' => __('site.affiliate_portal.funnel_qualifying'),
                    'commission' => __('site.affiliate_portal.funnel_commission'),
                ] as $key => $label)
                    <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                        <p class="text-xl font-bold tabular-nums mt-1">{{ $funnel[$key] ?? 0 }}</p>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('site.affiliate.referrals') }}" class="inline-flex text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.view_referrals') }} →</a>
        </section>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <section class="glass-card p-6 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.recent_referrals') }}</h2>
                <a href="{{ route('site.affiliate.referrals') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.view_referrals') }}</a>
            </div>
            @forelse ($recentReferrals as $referral)
                <div class="flex items-center justify-between gap-3 text-sm border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $referral['name'] }}</p>
                        <p class="text-xs text-gray-500">{{ $referral['stage'] }}</p>
                    </div>
                    <p class="text-xs text-gray-400">{{ $referral['date']?->format('d M') }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('site.affiliate_portal.no_referrals_body') }}</p>
            @endforelse
        </section>

        <section class="glass-card p-6 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.wallet_activity') }}</h2>
                <a href="{{ route('site.affiliate.wallet') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.view_wallet') }}</a>
            </div>
            @forelse ($walletActivity as $item)
                <div class="flex items-center justify-between gap-3 text-sm border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $item['label'] }}</p>
                        <p class="text-xs text-gray-500 capitalize">{{ $item['status'] }}</p>
                    </div>
                    <p class="font-semibold tabular-nums">{{ format_money($item['amount']) }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('site.affiliate_portal.no_payments') }}</p>
            @endforelse
        </section>
    </div>

    <section class="glass-card p-6 space-y-3">
        <p class="text-[11px] uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.recent_activity') }}</p>
        @forelse ($activity as $item)
            <div class="flex items-center justify-between gap-3 text-sm">
                <p class="text-gray-800">{{ $item['label'] }}</p>
                <p class="text-xs text-gray-400 shrink-0">{{ $item['date']?->diffForHumans() }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-500">{{ __('site.affiliate_portal.no_activity') }}</p>
        @endforelse
    </section>

</x-site.affiliate-layout>
