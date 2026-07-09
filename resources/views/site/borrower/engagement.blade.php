<x-site.borrower-layout :title="brand_title(__('borrower.engagement.title'))" active="engagement" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.engagement.eyebrow')"
        :title="__('borrower.engagement.title')"
        :subtitle="__('borrower.engagement.subtitle')"
    />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div x-data="{ tab: @js($tab) }">
        <nav class="flex gap-2 mb-6 overflow-x-auto snap-x snap-mandatory scrollbar-none pb-1">
            @foreach ([
                'overview' => __('borrower.engagement.tabs.overview'),
                'referrals' => __('borrower.engagement.tabs.referrals'),
                'rewards' => __('borrower.engagement.tabs.rewards'),
                'streak' => __('borrower.engagement.tabs.streak'),
            ] as $key => $label)
                <button type="button" @click="tab = @js($key)"
                        class="snap-start shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition"
                        :class="tab === @js($key) ? 'bg-brand text-white shadow-sm' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-brand-muted/40'">
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        {{-- Overview --}}
        <div x-show="tab === 'overview'" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="glass-card p-5 bg-gradient-to-br from-brand-muted/80 to-white sm:col-span-2 lg:col-span-1">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.rewards.wallet_title') }}</p>
                    <p class="mt-2 text-3xl font-black text-brand tabular-nums">{{ number_format($pointsBalance) }}</p>
                    <p class="text-xs text-gray-600 mt-1">{{ __('borrower.rewards.wallet_hint') }}</p>
                </div>
                <div class="glass-card p-5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.engagement.streak.title') }}</p>
                    <p class="mt-2 text-3xl font-black text-orange-600 tabular-nums">{{ $streakReward['count'] ?? 0 }} 🔥</p>
                    @if (($streakReward['points'] ?? 0) > 0)
                        <p class="text-xs text-orange-800 mt-1">{{ __('borrower.engagement.streak.points_available', ['points' => number_format($streakReward['points'])]) }}</p>
                    @endif
                </div>
                <div class="glass-card p-5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.level') }}</p>
                    <p class="mt-2 text-xl font-bold text-gray-900">{{ $level['label'] ?? 'Bronze' }}</p>
                    <p class="text-xs text-gray-600 mt-1">{{ __('borrower.referrals.progress_count', ['current' => $progress['current'] ?? 0, 'target' => $progress['target'] ?? 5]) }}</p>
                </div>
            </div>

            <div class="glass-card p-5 sm:p-6">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.rewards.overview_actions_title') }}</p>
                <div class="mt-4 grid sm:grid-cols-3 gap-3">
                    <button type="button" @click="tab = 'referrals'"
                            class="w-full text-center text-sm font-semibold px-4 py-3 rounded-xl bg-brand text-white hover:bg-brand-light">
                        {{ __('borrower.rewards.overview_refer_cta') }}
                    </button>
                    <button type="button" @click="tab = 'rewards'"
                            class="w-full text-center text-sm font-semibold px-4 py-3 rounded-xl bg-white text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40">
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
                           class="w-full text-center text-sm font-semibold px-4 py-3 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">
                            {{ __('borrower.rewards.overview_apply_cta') }}
                        </a>
                    @else
                        <a href="{{ route('site.borrower.loan-products') }}"
                           class="w-full text-center text-sm font-semibold px-4 py-3 rounded-xl bg-white text-gray-800 ring-1 ring-gray-200 hover:bg-gray-50">
                            {{ __('borrower.new_application') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Referrals tab --}}
        <div x-show="tab === 'referrals'" x-cloak class="grid gap-6 lg:grid-cols-3 mt-2">
            @include('site.borrower.engagement._referrals-panel')
        </div>

        {{-- Rewards tab --}}
        <div x-show="tab === 'rewards'" x-cloak class="grid gap-6 lg:grid-cols-3 mt-2">
            @include('site.borrower.engagement._rewards-panel')
        </div>

        {{-- Streak tab --}}
        <div x-show="tab === 'streak'" x-cloak class="mt-2 max-w-2xl">
            <div class="glass-card p-6 sm:p-8">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-3xl">🔥</span>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ __('borrower.engagement.streak.title') }}</h2>
                        <p class="text-sm text-gray-600">{{ __('borrower.engagement.streak.subtitle') }}</p>
                    </div>
                </div>
                <p class="text-4xl font-black text-orange-600 tabular-nums">{{ $streakReward['count'] ?? 0 }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.engagement.streak.on_time_count') }}</p>

                @if (($streakReward['milestones'] ?? []) !== [])
                    <ul class="mt-6 space-y-3">
                        @foreach ($streakReward['milestones'] as $milestone)
                            <li class="flex items-center justify-between gap-4 rounded-xl px-4 py-3 ring-1 {{ ($milestone['reached'] ?? false) ? 'bg-emerald-50 ring-emerald-200' : 'bg-gray-50 ring-gray-200' }}">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ __('borrower.engagement.streak.milestone', ['count' => $milestone['count']]) }}</p>
                                    <p class="text-xs text-gray-600">{{ __('borrower.engagement.streak.milestone_points', ['points' => number_format($milestone['points'] ?? 0)]) }}</p>
                                </div>
                                <span class="text-sm font-semibold {{ ($milestone['reached'] ?? false) ? 'text-emerald-700' : 'text-gray-400' }}">
                                    {{ ($milestone['reached'] ?? false) ? '✓' : '—' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="mt-6 text-xs text-gray-500">{{ __('borrower.engagement.streak.points_note') }}</p>
            </div>
        </div>
    </div>
</x-site.borrower-layout>
