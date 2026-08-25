<x-site.borrower-layout :title="brand_title(__('borrower.engagement.title'))" active="engagement" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.engagement.eyebrow')"
        :title="__('borrower.engagement.title')"
        :subtitle="__('borrower.engagement.subtitle')"
    />

    <div x-data="{ tab: @js($tab) }">
        <nav class="mb-5 sm:mb-6 grid grid-cols-4 gap-1 p-1 rounded-2xl bg-brand/5 ring-1 ring-brand/10" role="tablist">
            @foreach ([
                'overview' => __('borrower.engagement.tabs_short.overview'),
                'referrals' => __('borrower.engagement.tabs_short.referrals'),
                'rewards' => __('borrower.engagement.tabs_short.rewards'),
                'streak' => __('borrower.engagement.tabs_short.streak'),
            ] as $key => $label)
                <button type="button" @click="tab = @js($key)" role="tab"
                        class="min-w-0 px-1.5 sm:px-2 py-2.5 rounded-xl text-[11px] sm:text-sm font-bold tracking-tight transition text-center"
                        :class="tab === @js($key) ? 'bg-brand text-white shadow-sm' : 'text-brand/70 hover:bg-white hover:text-brand'">
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        {{-- Overview --}}
        <div x-show="tab === 'overview'" class="space-y-4">
            <div class="glass-card overflow-hidden ring-1 ring-brand/10">
                <div class="relative kf-premium-panel px-5 sm:px-8 py-6">
                    <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)]" aria-hidden="true"></div>
                    <div class="relative flex flex-wrap items-end justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.rewards.wallet_title') }}</p>
                            <h2 class="text-xl sm:text-2xl font-bold mt-1 tracking-tight">{{ __('borrower.engagement.title') }}</h2>
                            <p class="text-sm text-white/75 mt-1.5">{{ __('borrower.rewards.wallet_hint') }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-4xl sm:text-5xl font-black tabular-nums text-brand-gold leading-none">{{ number_format($pointsBalance) }}</p>
                            <p class="text-xs text-white/70 mt-1.5 font-semibold uppercase tracking-widest">{{ __('borrower.rewards.points_short') }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-px bg-brand/10">
                    <button type="button" @click="tab = 'streak'"
                            class="bg-white px-4 py-4 text-left hover:bg-brand-muted/30 transition">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.engagement.streak.title') }}</p>
                        <p class="mt-1.5 text-2xl font-black text-brand tabular-nums">{{ $streakReward['count'] ?? 0 }}</p>
                        <p class="text-[11px] text-gray-500 mt-1">{{ __('borrower.engagement.streak.on_time_count') }}</p>
                    </button>
                    <button type="button" @click="tab = 'referrals'"
                            class="bg-white px-4 py-4 text-left hover:bg-brand-muted/30 transition">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.level') }}</p>
                        <p class="mt-1.5 text-lg sm:text-xl font-bold text-gray-900 truncate">{{ $level['label'] ?? 'Bronze' }}</p>
                        <p class="text-[11px] text-gray-500 mt-1">{{ __('borrower.referrals.progress_count', ['current' => $progress['current'] ?? 0, 'target' => $progress['target'] ?? 5]) }}</p>
                    </button>
                </div>
            </div>

            <div class="glass-card p-4 sm:p-5 ring-1 ring-brand/10">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.rewards.overview_actions_title') }}</p>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <button type="button" @click="tab = 'referrals'"
                            class="w-full text-center text-sm font-bold px-4 py-3 rounded-xl bg-brand-gold text-brand hover:brightness-95">
                        {{ __('borrower.rewards.overview_refer_cta') }}
                    </button>
                    <button type="button" @click="tab = 'rewards'"
                            class="w-full text-center text-sm font-bold px-4 py-3 rounded-xl bg-brand text-white hover:bg-brand-light">
                        {{ __('borrower.rewards.overview_redeem_cta') }}
                    </button>
                    @php
                        $hasCheckoutReward = $activeRewards->contains(fn ($r) =>
                            $r->benefit_type === 'rate_discount'
                            || ($r->benefit_type === 'percent_discount' && $r->fee_type === 'application_fee')
                        );
                    @endphp
                    @if ($hasCheckoutReward)
                        <a href="{{ route('site.borrower.loan-products') }}"
                           class="w-full text-center text-sm font-bold px-4 py-3 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">
                            {{ __('borrower.rewards.overview_apply_cta') }}
                        </a>
                    @else
                        <a href="{{ route('site.borrower.loan-products') }}"
                           class="w-full text-center text-sm font-bold px-4 py-3 rounded-xl bg-white text-gray-800 ring-1 ring-gray-200 hover:bg-gray-50">
                            {{ __('borrower.new_application') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Referrals tab --}}
        <div x-show="tab === 'referrals'" x-cloak class="mt-1">
            @include('site.borrower.engagement._referrals-panel')
        </div>

        {{-- Rewards tab --}}
        <div x-show="tab === 'rewards'" x-cloak class="mt-1">
            @include('site.borrower.engagement._rewards-panel')
        </div>

        {{-- Streak tab — journey stepper --}}
        <div x-show="tab === 'streak'" x-cloak class="mt-1">
            @php
                $currentCount = (int) ($streakReward['count'] ?? 0);
                $milestones = collect($streakReward['milestones'] ?? [])->values();
                $nextMilestone = $milestones->first(fn ($m) => ! ($m['reached'] ?? false));
                $remainingToNext = $nextMilestone ? max(0, (int) $nextMilestone['count'] - $currentCount) : 0;
                $progressPct = $nextMilestone
                    ? min(100, (int) round(($currentCount / max(1, (int) $nextMilestone['count'])) * 100))
                    : ($milestones->isNotEmpty() ? 100 : 0);
            @endphp
            <div class="glass-card overflow-hidden ring-1 ring-brand/10">
                <div class="relative kf-premium-panel px-5 sm:px-8 py-6">
                    <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)]" aria-hidden="true"></div>
                    <div class="relative">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.engagement.streak.title') }}</p>
                        <h2 class="text-xl sm:text-2xl font-bold mt-1 tracking-tight">{{ __('borrower.engagement.streak.subtitle') }}</h2>
                        <div class="mt-5 flex items-end gap-3">
                            <p class="text-5xl font-black tabular-nums text-brand-gold leading-none">{{ $currentCount }}</p>
                            <p class="text-sm text-white/80 pb-1">{{ __('borrower.engagement.streak.on_time_count') }}</p>
                        </div>

                        @if ($nextMilestone)
                            <div class="mt-5">
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="font-semibold text-brand-gold">
                                        {{ __('borrower.engagement.streak.next_milestone_remaining', [
                                            'remaining' => $remainingToNext,
                                            'points' => number_format($nextMilestone['points'] ?? 0),
                                        ]) }}
                                    </span>
                                    <span class="tabular-nums text-white/80 font-bold">
                                        {{ __('borrower.engagement.streak.progress_label', [
                                            'current' => $currentCount,
                                            'target' => $nextMilestone['count'],
                                        ]) }}
                                    </span>
                                </div>
                                <div class="mt-2 h-2.5 rounded-full bg-white/15 overflow-hidden">
                                    <div class="h-full rounded-full bg-brand-gold transition-all" style="width: {{ $progressPct }}%"></div>
                                </div>
                            </div>
                        @elseif ($milestones->isNotEmpty())
                            <p class="text-sm text-brand-gold/90 mt-4">{{ __('borrower.engagement.streak.next_milestone_reached') }}</p>
                        @endif
                    </div>
                </div>

                <div class="px-5 sm:px-8 py-5 sm:py-6">
                    @if ($nextMilestone)
                        <p class="mb-5 text-sm font-medium text-brand bg-brand-muted/50 ring-1 ring-brand/10 rounded-xl px-4 py-3">
                            {{ __('borrower.engagement.streak.next_milestone', [
                                'count' => $nextMilestone['count'],
                                'points' => number_format($nextMilestone['points'] ?? 0),
                            ]) }}
                        </p>
                    @elseif ($milestones->isEmpty())
                        <p class="mb-5 text-sm text-gray-600">{{ __('borrower.engagement.streak.empty_hint') }}</p>
                    @endif

                    @if ($milestones->isNotEmpty())
                        <ol class="relative space-y-0">
                            @foreach ($milestones as $index => $milestone)
                                @php
                                    $reached = (bool) ($milestone['reached'] ?? false);
                                    $isNext = ! $reached && $nextMilestone && (int) $milestone['count'] === (int) $nextMilestone['count'];
                                    $isLast = $index === $milestones->count() - 1;
                                    $toGo = max(0, (int) $milestone['count'] - $currentCount);
                                @endphp
                                <li class="relative flex gap-3 sm:gap-4 {{ $isLast ? '' : 'pb-5' }}">
                                    @unless ($isLast)
                                        <span @class([
                                            'absolute left-[1.05rem] sm:left-[1.15rem] top-9 bottom-0 w-0.5',
                                            'bg-emerald-400' => $reached,
                                            'bg-brand/40' => $isNext,
                                            'bg-gray-200' => ! $reached && ! $isNext,
                                        ]) aria-hidden="true"></span>
                                    @endunless
                                    <span @class([
                                        'relative z-10 size-8 sm:size-9 rounded-full grid place-items-center text-xs sm:text-sm font-bold shrink-0 ring-2',
                                        'bg-emerald-500 text-white ring-emerald-200' => $reached,
                                        'bg-brand text-white ring-brand/30 shadow-sm' => $isNext,
                                        'bg-white text-gray-400 ring-gray-200' => ! $reached && ! $isNext,
                                    ])>
                                        {{ $reached ? '✓' : $milestone['count'] }}
                                    </span>
                                    <div @class([
                                        'flex-1 min-w-0 rounded-xl px-3.5 sm:px-4 py-3 ring-1',
                                        'bg-emerald-50 ring-emerald-200' => $reached,
                                        'bg-brand-muted/40 ring-brand/20' => $isNext,
                                        'bg-white ring-gray-200' => ! $reached && ! $isNext,
                                    ])>
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-semibold text-gray-900 text-sm sm:text-base">{{ __('borrower.engagement.streak.milestone', ['count' => $milestone['count']]) }}</p>
                                            <p class="text-sm font-bold tabular-nums text-brand">{{ number_format($milestone['points'] ?? 0) }} pts</p>
                                        </div>
                                        @if ($isNext && $toGo > 0)
                                            <p class="text-xs font-semibold text-brand mt-1">
                                                {{ __('borrower.engagement.streak.next_milestone_remaining', [
                                                    'remaining' => $toGo,
                                                    'points' => number_format($milestone['points'] ?? 0),
                                                ]) }}
                                            </p>
                                        @else
                                            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.engagement.streak.milestone_points', ['points' => number_format($milestone['points'] ?? 0)]) }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    <p class="mt-5 text-xs text-gray-500">{{ __('borrower.engagement.streak.points_note') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-site.borrower-layout>
