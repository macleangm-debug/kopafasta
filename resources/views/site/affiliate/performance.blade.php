<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.impact_title'))" active="performance" :hero="false">
    @php
        $funnelKeys = $funnelKeys ?? app(\App\Services\AffiliatePortalPresenter::class)->visibleFunnelKeys();
        $pipeline = $pipeline ?? collect();
    @endphp

    <section class="relative mb-6" x-data="{
        infoSheet: false,
        infoMenu: false,
        pop: { top: 0, left: 0 },
        place() {
            const r = this.$refs.infoBtn?.getBoundingClientRect();
            if (! r) return;
            this.pop = { top: r.bottom + 8, left: Math.max(12, r.right - 320) };
        }
    }">
        <div class="kf-premium-panel rounded-2xl p-6 sm:p-8 relative overflow-hidden">
            <div class="absolute inset-0 overflow-hidden rounded-2xl pointer-events-none opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative pr-14">
                @if ($premium)
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.premium_badge') }}</p>
                @endif
                <h1 class="text-2xl sm:text-3xl font-bold mt-2">{{ __('site.affiliate_portal.impact_hero') }}</h1>
                <p class="text-sm text-white/80 mt-2 max-w-2xl">{{ $standing['status_label'] ?? '' }}</p>
            </div>
        </div>
        <div class="absolute top-5 right-5 sm:top-7 sm:right-7 z-30">
            <button type="button" x-ref="infoBtn"
                    @click="if (window.matchMedia('(min-width: 1024px)').matches) { infoMenu = !infoMenu; place(); } else { infoSheet = true; }"
                    class="size-9 rounded-full bg-brand-gold text-brand ring-2 ring-white shadow-md grid place-items-center text-sm font-extrabold"
                    aria-label="{{ __('site.affiliate_portal.matokeo_info_title') }}">i</button>
            <x-site.bottom-sheet :title="__('site.affiliate_portal.matokeo_info_title')" open="infoSheet">
                <p class="text-sm text-gray-700 leading-relaxed">{{ $premium ? __('site.affiliate_portal.matokeo_info_body_premium') : __('site.affiliate_portal.matokeo_info_body_standard') }}</p>
            </x-site.bottom-sheet>
            <template x-teleport="body">
                <div x-show="infoMenu" x-cloak x-transition
                     @click.outside="if (! $refs.infoBtn?.contains($event.target)) infoMenu = false"
                     class="fixed z-[90] w-80 rounded-2xl bg-white shadow-xl ring-1 ring-brand/10 p-4 text-left"
                     :style="`top:${pop.top}px;left:${pop.left}px`">
                    <p class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.matokeo_info_title') }}</p>
                    <p class="text-sm text-gray-700 leading-relaxed mt-2">{{ $premium ? __('site.affiliate_portal.matokeo_info_body_premium') : __('site.affiliate_portal.matokeo_info_body_standard') }}</p>
                </div>
            </template>
        </div>
    </section>

    @php
        $metrics = [];
        foreach ($funnelKeys as $key) {
            $metrics[] = [
                'label' => __('site.affiliate_portal.funnel_'.$key),
                'value' => (string) ($funnel[$key] ?? 0),
            ];
        }
        if ($premium) {
            $metrics[] = [
                'label' => __('site.affiliate_portal.impact_earned'),
                'value' => format_money($impact['earned'] ?? 0),
            ];
        } else {
            foreach ($standing['kpi_results'] ?? [] as $kpi) {
                if (! ($kpi['enabled'] ?? false)) {
                    continue;
                }
                $actual = $kpi['key'] === 'conversion'
                    ? number_format($kpi['actual'], 1).'%'
                    : number_format($kpi['actual'], 0);
                $target = $kpi['key'] === 'conversion'
                    ? number_format($kpi['target'], 0).'%'
                    : number_format($kpi['target'], 0);
                $metrics[] = [
                    'label' => $kpi['label'],
                    'value' => $actual,
                    'hint' => '/ '.$target,
                ];
            }
        }
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
        <div class="mb-6" x-data="{ active: 0 }" data-kf-matokeo-rail>
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
                        <div class="glass-card p-4 h-full">
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
            class="mb-6"
            icon="👥"
            :title="__('site.affiliate_portal.no_referrals_title')"
            :description="__('site.affiliate_portal.no_referrals_body')"
            :action-label="__('site.affiliate_portal.nav_share')"
            :action-url="route('site.affiliate.share')"
        />
    @else
        <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white mb-6">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5">
                <h2 class="font-bold text-white">{{ __('site.affiliate_portal.recent_referrals') }}</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($pipeline as $referral)
                    <div class="px-4 sm:px-5 py-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $referral['name'] }}</p>
                            <p class="text-sm text-brand font-medium mt-0.5">{{ $referral['stage'] }}</p>
                        </div>
                        <p class="text-sm text-gray-500">{{ $referral['date']?->format('d M Y') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($premium)
        @if (! empty($impact['insights']))
            <section class="glass-card p-6 space-y-3">
                <h2 class="text-lg font-bold text-gray-900">{{ __('site.affiliate_portal.impact_insights') }}</h2>
                @foreach ($impact['insights'] as $insight)
                    <p class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 text-sm text-gray-800">{{ $insight }}</p>
                @endforeach
            </section>
        @endif
    @else
        <section class="glass-card p-6 space-y-3">
            <h2 class="text-lg font-bold text-gray-900">{{ __('site.affiliate_portal.performance_help_title') }}</h2>
            <details class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 group">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-sm font-semibold text-gray-900">
                    <span>{{ __('site.affiliate_portal.faq_assessed') }}</span>
                    <span class="text-gray-400 group-open:rotate-180 transition">⌄</span>
                </summary>
                <p class="text-sm text-gray-700 mt-3">{{ $assessmentExplanation }}</p>
            </details>
            <details class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 group">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-sm font-semibold text-gray-900">
                    <span>{{ __('site.affiliate_portal.faq_miss_target') }}</span>
                    <span class="text-gray-400 group-open:rotate-180 transition">⌄</span>
                </summary>
                <div class="mt-3 space-y-2">
                    @foreach ($warningLadder as $step)
                        <p class="text-sm text-gray-700">
                            <span class="font-semibold">{{ __('site.affiliate_portal.miss_step', ['n' => $step['periods']]) }}</span>
                            → {{ $step['label'] }}
                        </p>
                    @endforeach
                </div>
            </details>
            <details class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 group">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-sm font-semibold text-gray-900">
                    <span>{{ __('site.affiliate_portal.faq_good_standing') }}</span>
                    <span class="text-gray-400 group-open:rotate-180 transition">⌄</span>
                </summary>
                <p class="text-sm text-gray-700 mt-3">{{ $recovery }}</p>
            </details>
        </section>
    @endif
</x-site.affiliate-layout>
