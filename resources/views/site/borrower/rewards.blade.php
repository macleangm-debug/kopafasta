<x-site.borrower-layout :title="brand_title(__('borrower.rewards.title'))" active="rewards" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.rewards.eyebrow')"
        :title="__('borrower.rewards.title')"
        :subtitle="__('borrower.rewards.subtitle')"
    />

    <x-site.page-loading-shell>
        <x-slot:skeleton>
            <div class="grid gap-6 lg:grid-cols-3">
                <x-site.skeleton-card :lines="6" class="lg:col-span-2" />
                <x-site.skeleton-card :lines="4" />
            </div>
        </x-slot:skeleton>

        <div class="grid gap-6 lg:grid-cols-3">
            @php
                $pointsBalance = $balance;
                $redeemRoute = route('site.borrower.rewards.redeem');
            @endphp

            <section class="lg:col-span-2 space-y-6">
                <div class="glass-card p-6 sm:p-8 bg-gradient-to-br from-brand-muted/60 to-white">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.rewards.balance') }}</p>
                    <p class="mt-2 text-4xl font-black text-brand tabular-nums">{{ number_format($pointsBalance) }}</p>
                    <p class="text-sm text-gray-600 mt-2">{{ __('borrower.rewards.balance_hint') }}</p>
                </div>

                @if ($activeRewards->isNotEmpty())
                    <section class="glass-card p-6">
                        <h2 class="font-semibold text-gray-900">{{ __('borrower.rewards.active') }}</h2>
                        <ul class="mt-4 space-y-3">
                            @foreach ($activeRewards as $reward)
                                <li class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $reward->label }}</p>
                                        @if ($reward->expires_at)
                                            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.rewards.expires', ['date' => $reward->expires_at->format('d M Y')]) }}</p>
                                        @endif
                                        @if (in_array($reward->benefit_type, ['rate_discount', 'percent_discount'], true))
                                            <p class="text-xs text-emerald-800 mt-1 font-medium">{{ __('borrower.rewards.applies_at_checkout') }}</p>
                                        @endif
                                    </div>
                                    @if (in_array($reward->benefit_type, ['rate_discount', 'percent_discount'], true) && ($reward->fee_type === null || $reward->fee_type === 'application_fee'))
                                        <a href="{{ route('site.borrower.loan-products') }}"
                                           class="shrink-0 text-xs font-semibold text-brand underline">
                                            {{ __('borrower.rewards.redeemed_apply_cta') }} →
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="glass-card p-6">
                    <h2 class="font-semibold text-gray-900">{{ __('borrower.rewards.redeem_title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.rewards.redeem_subtitle') }}</p>
                    <div class="mt-5 grid sm:grid-cols-2 gap-4">
                        @foreach ($catalog as $option)
                            @php
                                $cost = (int) ($option['points'] ?? 0);
                                $unlocked = $pointsBalance >= $cost;
                                $shortfall = max(0, $cost - $pointsBalance);
                                $checkoutReward = in_array($option['benefit_type'] ?? '', ['rate_discount', 'percent_discount'], true)
                                    && in_array($option['fee_type'] ?? null, [null, 'application_fee'], true);
                            @endphp
                            <form method="POST" action="{{ $redeemRoute }}"
                                  class="relative overflow-hidden rounded-2xl ring-1 p-4 flex flex-col h-full transition {{ $unlocked ? 'bg-white ring-brand/25 hover:ring-brand/40' : 'bg-gray-50 ring-gray-200' }}">
                                @csrf
                                <input type="hidden" name="option_key" value="{{ $option['key'] }}">
                                <div class="flex items-start justify-between gap-3 min-h-[4.5rem]">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $option['label'] }}</p>
                                        @if ($option['description'] ?? null)
                                            <p class="text-sm text-gray-600 mt-2 line-clamp-2">{{ $option['description'] }}</p>
                                        @endif
                                    </div>
                                    <span class="shrink-0 w-11 h-11 rounded-xl grid place-items-center text-lg {{ $unlocked ? 'bg-brand-muted text-brand' : 'bg-gray-200 text-gray-500' }}"
                                          aria-hidden="true">
                                        {{ $unlocked ? '🎁' : '🔒' }}
                                    </span>
                                </div>
                                @if ($checkoutReward)
                                    <p class="mt-3 text-[10px] uppercase tracking-widest font-semibold {{ $unlocked ? 'text-brand' : 'text-gray-400' }}">
                                        {{ __('borrower.rewards.applies_at_checkout') }}
                                    </p>
                                @endif
                                <div class="mt-auto pt-4 flex items-center justify-between gap-3 border-t border-gray-100">
                                    <p class="text-xs uppercase tracking-widest text-brand font-semibold tabular-nums">{{ __('borrower.rewards.cost', ['points' => number_format($cost)]) }}</p>
                                    <button type="submit"
                                            @disabled(! $unlocked)
                                            class="shrink-0 text-center text-sm font-semibold px-4 py-2 rounded-xl {{ $unlocked ? 'bg-brand hover:bg-brand-light text-white' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
                                        @if ($unlocked)
                                            {{ __('borrower.rewards.redeem_button') }}
                                        @else
                                            {{ __('borrower.rewards.locked_button', ['points' => number_format($shortfall)]) }}
                                        @endif
                                    </button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </section>
            </section>

            <aside class="space-y-6">
                <section class="glass-card p-6">
                    <h2 class="font-semibold text-gray-900">{{ __('borrower.rewards.earn_more') }}</h2>
                    <div class="mt-4 space-y-2">
                        <a href="{{ route('site.borrower.profile') }}"
                           class="block w-full text-center text-sm font-semibold px-4 py-2.5 rounded-xl bg-brand text-white hover:bg-brand-light">
                            {{ __('borrower.engagement.next_action.complete_profile') }}
                        </a>
                        <a href="{{ route('site.borrower.referrals') }}"
                           class="block w-full text-center text-sm font-semibold px-4 py-2.5 rounded-xl bg-white text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40">
                            {{ __('borrower.engagement.next_action.refer') }}
                        </a>
                        <a href="{{ route('site.borrower.loans') }}"
                           class="block w-full text-center text-sm font-semibold px-4 py-2.5 rounded-xl bg-white text-gray-800 ring-1 ring-gray-200 hover:bg-gray-50">
                            {{ __('borrower.engagement.next_action.repay_on_time') }}
                        </a>
                    </div>
                </section>

                @if ($transactions->isNotEmpty())
                    <section class="glass-card p-6">
                        <h2 class="font-semibold text-gray-900">{{ __('borrower.rewards.recent_activity') }}</h2>
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($transactions as $tx)
                                @php
                                    $activityLabel = filled($tx->action_key ?? null)
                                        ? __('borrower.rewards.actions.'.$tx->action_key)
                                        : null;
                                    if (! $activityLabel || $activityLabel === 'borrower.rewards.actions.'.($tx->action_key ?? '')) {
                                        $activityLabel = $tx->description;
                                    }
                                @endphp
                                <li class="flex justify-between gap-3 border-b border-gray-100 pb-2 last:border-0">
                                    <span class="text-gray-700">{{ $activityLabel }}</span>
                                    <span class="font-semibold tabular-nums {{ $tx->points >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                        {{ $tx->points >= 0 ? '+' : '' }}{{ number_format($tx->points) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </x-site.page-loading-shell>
</x-site.borrower-layout>
