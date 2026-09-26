<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.impact_title'))" active="performance" :hero="false">
    @php
        $overviewKeys = $overviewKeys ?? app(\App\Services\AffiliatePortalPresenter::class)->overviewFunnelKeys();
        $pipeline = $pipeline ?? collect();
        $tab = request()->query('tab', 'overview');
        if (! in_array($tab, ['overview', 'commissions', 'withdrawals'], true)) {
            $tab = 'overview';
        }
        $openWithdraw = $errors->has('amount') || $errors->has('notes') || request()->boolean('withdraw');
    @endphp

    <div x-data="{
        tab: @js($tab),
        withdrawing: {{ $openWithdraw && ($available ?? 0) >= ($minPayout ?? 0) && ($hasPayoutAccount ?? false) ? 'true' : 'false' }},
        withdrawHint: {{ $openWithdraw && (($available ?? 0) < ($minPayout ?? 0) || ! ($hasPayoutAccount ?? false)) ? 'true' : 'false' }},
        belowMinimum: {{ ($available ?? 0) < ($minPayout ?? 0) ? 'true' : 'false' }},
        needsAccount: {{ ($hasPayoutAccount ?? false) ? 'false' : 'true' }},
        step: 'form',
        infoSheet: false,
        infoMenu: false,
        pop: { top: 0, left: 0 },
        place() {
            const r = this.$refs.infoBtn?.getBoundingClientRect();
            if (! r) return;
            this.pop = { top: r.bottom + 8, left: Math.max(12, r.right - 320) };
        }
    }">
        <section class="relative mb-6">
            <div class="kf-premium-panel rounded-2xl p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute inset-0 overflow-hidden rounded-2xl pointer-events-none opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
                <div class="relative grid gap-6 lg:grid-cols-2 lg:items-end pr-14">
                    <div>
                        @if ($premium)
                            <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.premium_badge') }}</p>
                        @endif
                        <h1 class="text-2xl sm:text-3xl font-bold mt-2">{{ __('site.affiliate_portal.impact_title') }}</h1>
                        <p class="text-sm text-white/80 mt-1">{{ __('site.affiliate_portal.impact_hero') }}</p>
                        <p class="text-xs uppercase tracking-widest text-white/70 font-semibold mt-3">{{ __('site.affiliate_portal.progress_title') }}</p>
                        @if (! $premium && ! empty($kpiCard))
                            <p class="text-lg sm:text-xl font-semibold text-white mt-2">{{ __('site.affiliate_portal.kpi_of', ['achieved' => rtrim(rtrim(number_format($kpiCard['achieved'], 1, '.', ''), '0'), '.'), 'target' => rtrim(rtrim(number_format($kpiCard['target'], 1, '.', ''), '0'), '.')]) }}</p>
                            <p class="text-xs text-white/75 mt-1">
                                {{ __('site.affiliate_portal.kpi_percent', ['percent' => $kpiCard['percent']]) }}
                                @if (($kpiCard['remaining'] ?? 0) > 0)
                                    · {{ __('site.affiliate_portal.more_needed', ['count' => (int) ceil($kpiCard['remaining'])]) }}
                                @endif
                            </p>
                            <div class="mt-3 h-3 max-w-md rounded-full bg-white/15 overflow-hidden" role="progressbar" aria-valuenow="{{ $kpiCard['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-brand-gold" style="width: {{ $kpiCard['percent'] }}%"></div>
                            </div>
                        @else
                            <p class="text-sm text-white/80 mt-2 max-w-2xl">{{ $standing['status_label'] ?? '' }}</p>
                        @endif
                    </div>

                    <div class="lg:justify-self-end w-full lg:max-w-sm">
                        <div class="flex flex-col items-stretch gap-3">
                            @include('site.affiliate._balance-card', [
                                'available' => $available ?? 0,
                                'inProgress' => $inProgress ?? 0,
                            ])
                            @include('site.affiliate._withdraw-cta', [
                                'mode' => 'button',
                                'hasPayoutAccount' => $hasPayoutAccount ?? false,
                                'available' => $available ?? 0,
                                'minPayout' => $minPayout ?? 0,
                            ])
                            @include('site.affiliate._results-withdraw')
                        </div>
                    </div>
                </div>
            </div>
            <div class="absolute top-5 right-5 sm:top-7 sm:right-7 z-30">
                <button type="button" x-ref="infoBtn"
                        @click="if (window.matchMedia('(min-width: 1024px)').matches) { infoMenu = !infoMenu; place(); } else { infoSheet = true; }"
                        class="size-9 rounded-full bg-brand-gold text-brand ring-2 ring-white shadow-md grid place-items-center text-sm font-extrabold"
                        aria-label="{{ __('site.affiliate_portal.matokeo_info_title') }}">i</button>
                <x-site.bottom-sheet :title="__('site.affiliate_portal.matokeo_info_title')" open="infoSheet">
                    @include('site.affiliate._performance-info')
                </x-site.bottom-sheet>
                <template x-teleport="body">
                    <div x-show="infoMenu" x-cloak x-transition
                         @click.outside="if (! $refs.infoBtn?.contains($event.target)) infoMenu = false"
                         class="fixed z-[70] w-80 rounded-2xl bg-white shadow-xl ring-1 ring-brand/10 p-4 text-left"
                         :style="`top:${pop.top}px;left:${pop.left}px`">
                        <p class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.matokeo_info_title') }}</p>
                        <div class="mt-2 space-y-2">
                            @include('site.affiliate._performance-info')
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <div class="glass-card overflow-hidden">
            <nav class="kf-account-tabs grid grid-cols-3 gap-1 p-1 m-3 rounded-2xl bg-brand/5 ring-1 ring-brand/10" role="tablist">
                <button type="button" @click="tab = 'overview'" role="tab"
                        class="min-w-0 px-2 py-2.5 rounded-xl text-[11px] sm:text-sm font-bold tracking-tight transition text-center"
                        :class="tab === 'overview' ? 'bg-brand text-white shadow-sm' : 'text-brand/70 hover:bg-white hover:text-brand'">
                    {{ __('site.affiliate_portal.tab_overview') }}
                </button>
                <button type="button" @click="tab = 'commissions'" role="tab"
                        class="min-w-0 px-2 py-2.5 rounded-xl text-[11px] sm:text-sm font-bold tracking-tight transition text-center"
                        :class="tab === 'commissions' ? 'bg-brand text-white shadow-sm' : 'text-brand/70 hover:bg-white hover:text-brand'">
                    {{ __('site.affiliate_portal.tab_commissions') }}
                </button>
                <button type="button" @click="tab = 'withdrawals'" role="tab"
                        class="min-w-0 px-2 py-2.5 rounded-xl text-[11px] sm:text-sm font-bold tracking-tight transition text-center"
                        :class="tab === 'withdrawals' ? 'bg-brand text-white shadow-sm' : 'text-brand/70 hover:bg-white hover:text-brand'">
                    {{ __('site.affiliate_portal.tab_withdrawals') }}
                </button>
            </nav>

            <div x-show="tab === 'overview'">
                @php
                    $metrics = [];
                    foreach ($overviewKeys as $key) {
                        $metrics[] = [
                            'label' => __('site.affiliate_portal.funnel_'.$key),
                            'value' => (string) ($funnel[$key] ?? 0),
                        ];
                    }
                    $metrics[] = [
                        'label' => __('site.affiliate_portal.funnel_earned'),
                        'value' => format_money($funnel['earned'] ?? 0),
                    ];
                    $metricCount = count($metrics);
                    $lgCols = match ($metricCount) {
                        1 => 'lg:grid-cols-1',
                        2 => 'lg:grid-cols-2',
                        4 => 'lg:grid-cols-4',
                        5 => 'lg:grid-cols-5',
                        default => 'lg:grid-cols-3',
                    };
                @endphp

                @if ($metrics !== [])
                    <div class="px-4 sm:px-5 pt-2 pb-4" x-data="{ active: 0 }" data-kf-matokeo-rail>
                        <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-2 -mx-1 px-1 scrollbar-none lg:grid {{ $lgCols }} lg:overflow-visible lg:pb-0 lg:mx-0 lg:px-0 lg:gap-3"
                             @scroll.passive="
                                const cards = $event.target.querySelectorAll('[data-metric-card]');
                                if (!cards.length) return;
                                const left = $event.target.scrollLeft;
                                let best = 0, bestDist = Infinity;
                                cards.forEach((card, i) => {
                                    const dist = Math.abs(card.offsetLeft - left);
                                    if (dist < bestDist) { bestDist = dist; best = i; }
                                });
                                active = best;
                             ">
                            @foreach ($metrics as $metric)
                                <div data-metric-card class="min-w-[78%] snap-center shrink-0 lg:min-w-0 h-auto lg:h-full">
                                    <div class="kf-metric-card rounded-xl bg-gray-50 ring-1 ring-gray-100 p-4 h-full">
                                        <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $metric['label'] }}</p>
                                        <p class="text-2xl font-bold mt-1 tabular-nums">
                                            {{ $metric['value'] }}
                                            @if (! empty($metric['hint']))
                                                <span class="text-base font-medium text-gray-500">{{ $metric['hint'] }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($metricCount > 1)
                            <div class="flex justify-center gap-1.5 mt-2 lg:hidden" aria-hidden="true">
                                @foreach ($metrics as $i => $metric)
                                    <span class="size-1.5 rounded-full transition"
                                          :class="active === {{ $i }} ? 'bg-brand' : 'bg-gray-300'"></span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if ($pipeline->isEmpty())
                    <x-site.empty-state
                        class="mb-0"
                        icon="👥"
                        :title="__('site.affiliate_portal.no_referrals_title')"
                        :description="__('site.affiliate_portal.no_referrals_body')"
                        :action-label="__('site.affiliate_portal.nav_share')"
                        :action-url="route('site.affiliate.share')"
                    />
                @else
                    <div class="px-4 sm:px-5 pb-5">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <h2 class="font-semibold text-gray-900">{{ __('site.affiliate_portal.recent_referrals') }}</h2>
                            <a href="{{ route('site.affiliate.reports') }}" class="text-xs font-semibold text-brand">
                                {{ __('site.affiliate_portal.view_monthly_report') }} →
                            </a>
                        </div>
                        <div class="hidden lg:block overflow-x-auto rounded-xl ring-1 ring-gray-100">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-left text-[10px] uppercase tracking-wider text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">{{ __('site.affiliate_portal.col_member') }}</th>
                                        <th class="px-4 py-3">{{ __('site.affiliate_portal.col_source') }}</th>
                                        <th class="px-4 py-3">{{ __('site.affiliate_portal.col_milestone') }}</th>
                                        <th class="px-4 py-3">{{ __('site.affiliate_portal.col_date') }}</th>
                                        <th class="px-4 py-3 lg:text-right">{{ __('site.affiliate_portal.col_commission') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($pipeline as $referral)
                                        <tr>
                                            <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $referral['member_no'] ?: '—' }}</td>
                                            <td class="px-4 py-3">{{ $referral['source'] }}</td>
                                            <td class="px-4 py-3 text-brand font-medium">{{ $referral['stage'] }}</td>
                                            <td class="px-4 py-3 text-gray-500">{{ $referral['date']?->format('d M Y') }}</td>
                                            <td class="px-4 py-3 tabular-nums lg:text-right">{{ format_money($referral['commission_amount'] ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="lg:hidden space-y-3">
                            @foreach ($pipeline as $referral)
                                <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-3">
                                    <p class="font-semibold text-gray-900 font-mono">{{ $referral['member_no'] ?: '—' }}</p>
                                    <p class="text-sm text-brand font-medium mt-1">{{ $referral['stage'] }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $referral['source'] }} · {{ $referral['date']?->format('d M Y') }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($premium && ! empty($impact['insights']))
                    <div class="px-4 sm:px-5 pb-5 space-y-3">
                        <h2 class="text-lg font-bold text-gray-900">{{ __('site.affiliate_portal.impact_insights') }}</h2>
                        @foreach ($impact['insights'] as $insight)
                            <p class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 text-sm text-gray-800">{{ $insight }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

            <div x-show="tab === 'commissions'" x-cloak>
                @include('site.affiliate._results-commissions')
            </div>

            <div x-show="tab === 'withdrawals'" x-cloak>
                @include('site.affiliate._results-withdrawals')
            </div>
        </div>
    </div>
</x-site.affiliate-layout>
