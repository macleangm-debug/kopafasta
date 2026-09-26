<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.dashboard_title'))" active="dashboard" :hero="false">
    @php
        $funnelKeys = $funnelKeys ?? ['visited', 'registered', 'applied'];
        $typeLabel = ($progress['premium'] ?? false)
            ? __('site.affiliate_portal.hero_type_premium')
            : __('site.affiliate_portal.hero_type_standard');
        $shareUrl = ($eligibility['can_share'] ?? false)
            ? route('site.affiliate.share')
            : ($attention['cta_url'] ?? route('site.affiliate.share'));
    @endphp

    <section class="kf-premium-panel rounded-3xl mb-5">
        <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <div class="relative p-5 sm:p-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ strtoupper((string) ($hero['grade_label'] ?? $typeLabel)) }}</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ $hero['greeting'] ?? $vendor->name }}</h1>
                <p class="text-sm text-white/70 mt-1 font-mono">{{ $vendor->partner_number ?? $vendor->vendor_number }}</p>
                <p class="text-sm text-white/80 mt-2 max-w-lg">{{ $standing['status_label'] ?? '' }}</p>
                @if ($vendor->affiliate_code)
                    <p class="inline-flex items-center gap-2 mt-3 rounded-full bg-white/15 ring-1 ring-white/25 px-3 py-1.5">
                        <span class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.promo_code') }}</span>
                        <span class="text-sm font-mono font-bold text-white" data-kf-promo-code>{{ $vendor->affiliate_code }}</span>
                    </p>
                @endif
                @if (($attention['kind'] ?? '') === 'profile' && ! empty($attention['cta_url']))
                    <a href="{{ $attention['cta_url'] }}"
                       class="inline-flex mt-4 justify-center bg-brand-gold text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                        {{ $attention['cta_label'] }} →
                    </a>
                @endif
            </div>
            <div class="w-full lg:max-w-sm flex flex-col items-stretch gap-3">
                @include('site.affiliate._balance-card', [
                    'available' => $available ?? 0,
                    'inProgress' => $inProgress ?? 0,
                ])
                @include('site.affiliate._withdraw-cta', [
                    'mode' => 'link',
                    'withdrawHref' => route('site.affiliate.performance', ['tab' => 'withdrawals', 'withdraw' => ($available ?? 0) >= ($minPayout ?? 0) ? 1 : 0]),
                ])
            </div>
        </div>
    </section>

    @if (($attention ?? null) && ($attention['kind'] ?? '') !== 'profile')
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

    <section class="mb-6">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('site.affiliate_portal.quick_actions_title') }}</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
            @foreach ([
                [__('site.affiliate_portal.quick_share'), $shareUrl, '🔗'],
                [__('site.affiliate_portal.quick_results'), route('site.affiliate.performance', ['tab' => 'overview']), '📊'],
                [__('site.affiliate_portal.withdraw'), route('site.affiliate.performance', ['tab' => 'withdrawals', 'withdraw' => 1]), '💰'],
                [__('site.affiliate_portal.quick_profile'), route('site.affiliate.profile'), '👤'],
            ] as [$label, $url, $icon])
                <a href="{{ $url }}"
                   class="group flex flex-col items-center gap-2 rounded-2xl glass-card px-2 py-4 text-center ring-1 ring-gray-200/80 hover:ring-brand/30 hover:shadow-sm transition">
                    <span class="text-2xl leading-none group-hover:scale-110 transition-transform" aria-hidden="true">{{ $icon }}</span>
                    <span class="text-[11px] sm:text-xs font-semibold text-gray-800 leading-tight">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5 flex items-center justify-between gap-3">
                <h2 class="font-bold text-white">{{ ($progress['premium'] ?? false) ? __('site.affiliate_portal.impact_title') : __('site.affiliate_portal.progress_title') }}</h2>
                <a href="{{ route('site.affiliate.performance', ['tab' => 'overview']) }}"
                   class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-3 py-1.5 text-xs shadow-sm">
                    {{ ($progress['premium'] ?? false) ? __('site.affiliate_portal.view_impact') : __('site.affiliate_portal.view_performance') }}
                </a>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $standing['status_label'] ?? '' }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ $progress['days_remaining'] ?? 0 }} {{ __('site.affiliate_portal.days_remaining') }}</p>
                </div>
                @if ($progress['premium'] ?? false)
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach ([
                            'visited' => __('site.affiliate_portal.impact_visited'),
                            'registered' => __('site.affiliate_portal.impact_registered'),
                            'applied' => __('site.affiliate_portal.impact_applied'),
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
                            @if (($kpi['enabled'] ?? false) && ! (($kpi['key'] ?? '') === 'qualified_referrals' && collect($standing['kpi_results'] ?? [])->contains(fn ($row) => ($row['key'] ?? '') === 'paying_members' && ($row['enabled'] ?? false))))
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
            </div>
        </section>

        <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5 flex items-center justify-between gap-3">
                <h2 class="font-bold text-white">{{ $monthlyCard['title'] ?? __('site.affiliate_portal.funnel_title') }}</h2>
                <a href="{{ $monthlyCard['report_url'] ?? route('site.affiliate.reports') }}"
                   class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-3 py-1.5 text-xs shadow-sm">
                    {{ __('site.affiliate_portal.view_monthly_report') }} →
                </a>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    @foreach ($funnelKeys as $key)
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.funnel_'.$key) }}</p>
                            <p class="text-xl font-bold tabular-nums mt-1">{{ $funnel[$key] ?? 0 }}</p>
                        </div>
                    @endforeach
                    <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                        <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.funnel_earned') }}</p>
                        <p class="text-xl font-bold tabular-nums mt-1">{{ format_money($funnel['earned'] ?? 0) }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white mb-6">
        <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5 flex items-center justify-between gap-3">
            <h2 class="font-bold text-white">{{ __('site.affiliate_portal.recent_activity') }}</h2>
            <a href="{{ route('site.affiliate.performance', ['tab' => 'commissions']) }}"
               class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-3 py-1.5 text-xs shadow-sm">
                {{ __('site.affiliate_portal.tab_commissions') }}
            </a>
        </div>
        <div class="p-5 space-y-3">
            @forelse ($activity as $item)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <p class="text-gray-800">{{ $item['label'] }}</p>
                    <p class="text-xs text-gray-400 shrink-0">{{ $item['date']?->diffForHumans() }}</p>
                </div>
            @empty
                <x-site.empty-state
                    class="!shadow-none !ring-0"
                    compact
                    icon="📋"
                    :title="__('site.affiliate_portal.no_activity')"
                    :description="__('site.affiliate_portal.no_referrals_body')"
                    :action-label="__('site.affiliate_portal.nav_share')"
                    :action-url="$shareUrl"
                />
            @endforelse
        </div>
    </section>

</x-site.affiliate-layout>
