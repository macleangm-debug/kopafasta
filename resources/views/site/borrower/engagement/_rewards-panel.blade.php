@php
    $groupedCatalog = collect($catalog)->groupBy(function (array $option) {
        $type = $option['benefit_type'] ?? '';
        $fee = $option['fee_type'] ?? null;
        if ($type === 'rate_discount') {
            return 'interest';
        }
        if (in_array($type, ['percent_discount', 'fixed_discount'], true)) {
            return 'fees';
        }

        return 'perks';
    });
    $groupLabels = [
        'fees' => __('borrower.rewards.group_fees'),
        'interest' => __('borrower.rewards.group_interest'),
        'perks' => __('borrower.rewards.group_perks'),
    ];
@endphp

<div class="space-y-4">
    <div class="glass-card overflow-hidden ring-1 ring-brand/10">
        <div class="relative bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-8 py-6 text-white">
            <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)]" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.rewards.eyebrow') }}</p>
                    <h2 class="text-xl sm:text-2xl font-bold mt-1 tracking-tight">{{ __('borrower.rewards.balance') }}</h2>
                    <p class="text-sm text-white/75 mt-1.5 max-w-md">{{ __('borrower.rewards.balance_hint') }}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-4xl sm:text-5xl font-black tabular-nums text-brand-gold leading-none">{{ number_format($pointsBalance) }}</p>
                    <p class="text-xs text-white/70 mt-1.5 font-semibold uppercase tracking-widest">{{ __('borrower.rewards.points_short') }}</p>
                </div>
            </div>
        </div>
    </div>

    @if ($activeRewards->isNotEmpty())
        <section class="glass-card overflow-hidden ring-1 ring-emerald-200/80">
            <div class="px-5 py-4 border-b border-emerald-100 bg-emerald-50/80">
                <h2 class="text-sm font-bold text-emerald-950">{{ __('borrower.rewards.active') }}</h2>
            </div>
            <ul class="divide-y divide-emerald-100">
                @foreach ($activeRewards as $reward)
                    <li class="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-gray-900">{{ $reward->label }}</p>
                            @if ($reward->expires_at)
                                <p class="text-xs text-gray-600 mt-1">{{ __('borrower.rewards.expires', ['date' => $reward->expires_at->format('d M Y')]) }}</p>
                            @endif
                            @if (in_array($reward->benefit_type, ['rate_discount', 'percent_discount'], true))
                                <p class="text-xs text-emerald-800 mt-1 font-semibold">{{ __('borrower.rewards.applies_at_checkout') }}</p>
                            @endif
                        </div>
                        @if (in_array($reward->benefit_type, ['rate_discount', 'percent_discount'], true) && ($reward->fee_type === null || $reward->fee_type === 'application_fee'))
                            <a href="{{ route('site.borrower.loan-products') }}"
                               class="inline-flex justify-center shrink-0 font-bold px-4 py-2.5 rounded-xl text-sm bg-emerald-600 text-white hover:bg-emerald-700">
                                {{ __('borrower.rewards.redeemed_apply_cta') }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="glass-card overflow-hidden ring-1 ring-brand/10">
        <div class="px-5 sm:px-6 py-4 sm:py-5 border-b border-brand/10 bg-brand-muted/30">
            <h2 class="text-sm sm:text-base font-bold text-gray-900">{{ __('borrower.rewards.redeem_title') }}</h2>
            <p class="text-xs sm:text-sm text-gray-600 mt-1">{{ __('borrower.rewards.redeem_subtitle') }}</p>
        </div>
        <div class="p-4 sm:p-5 space-y-6">
            @foreach (['fees', 'interest', 'perks'] as $groupKey)
                @continue(! $groupedCatalog->has($groupKey) || $groupedCatalog[$groupKey]->isEmpty())
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-bold mb-3">{{ $groupLabels[$groupKey] }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        @foreach ($groupedCatalog[$groupKey] as $option)
                            @php
                                $cost = (int) ($option['points'] ?? 0);
                                $unlocked = $pointsBalance >= $cost;
                                $shortfall = max(0, $cost - $pointsBalance);
                                $checkoutReward = in_array($option['benefit_type'] ?? '', ['rate_discount', 'percent_discount'], true)
                                    && in_array($option['fee_type'] ?? null, [null, 'application_fee'], true);
                            @endphp
                            <form method="POST" action="{{ route('site.borrower.engagement.redeem') }}"
                                  class="relative overflow-hidden rounded-2xl ring-1 p-4 flex flex-col h-full transition {{ $unlocked ? 'bg-white ring-brand/20 shadow-sm' : 'bg-gray-50/80 ring-gray-200' }}">
                                @csrf
                                <input type="hidden" name="option_key" value="{{ $option['key'] }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-900 leading-snug">{{ $option['label'] }}</p>
                                        @if ($option['description'])
                                            <p class="text-xs sm:text-sm text-gray-600 mt-1.5 line-clamp-2">{{ $option['description'] }}</p>
                                        @endif
                                    </div>
                                    <span @class([
                                        'shrink-0 size-10 rounded-xl grid place-items-center text-sm font-black',
                                        'bg-brand text-white' => $unlocked,
                                        'bg-gray-200 text-gray-500' => ! $unlocked,
                                    ]) aria-hidden="true">
                                        {{ $unlocked ? '★' : '○' }}
                                    </span>
                                </div>
                                @if ($checkoutReward)
                                    <p class="mt-3 text-[10px] uppercase tracking-widest font-bold {{ $unlocked ? 'text-brand' : 'text-gray-400' }}">
                                        {{ __('borrower.rewards.applies_at_checkout') }}
                                    </p>
                                @endif
                                <div class="mt-auto pt-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 border-t border-gray-100">
                                    <p class="text-xs uppercase tracking-widest text-brand font-bold tabular-nums">{{ __('borrower.rewards.cost', ['points' => number_format($cost)]) }}</p>
                                    <button type="submit"
                                            @disabled(! $unlocked)
                                            class="w-full sm:w-auto shrink-0 text-center text-sm font-bold px-4 py-2.5 rounded-xl {{ $unlocked ? 'bg-brand-gold hover:brightness-95 text-brand' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
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
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2">
        <section class="glass-card p-5 ring-1 ring-brand/10">
            <h2 class="text-sm font-bold text-gray-900">{{ __('borrower.rewards.earn_more') }}</h2>
            <div class="mt-4 space-y-2">
                <a href="{{ route('site.borrower.profile') }}"
                   class="block w-full text-center text-sm font-bold px-4 py-2.5 rounded-xl bg-brand text-white hover:bg-brand-light">
                    {{ __('borrower.engagement.next_action.complete_profile') }}
                </a>
                <button type="button" @click="tab = 'referrals'"
                        class="block w-full text-center text-sm font-bold px-4 py-2.5 rounded-xl bg-white text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40">
                    {{ __('borrower.engagement.next_action.refer') }}
                </button>
                <a href="{{ route('site.borrower.loans') }}"
                   class="block w-full text-center text-sm font-bold px-4 py-2.5 rounded-xl bg-white text-gray-800 ring-1 ring-gray-200 hover:bg-gray-50">
                    {{ __('borrower.engagement.next_action.repay_on_time') }}
                </a>
            </div>
        </section>

        @if ($transactions->isNotEmpty())
            <section class="glass-card overflow-hidden ring-1 ring-brand/10">
                <div class="px-5 py-4 border-b border-brand/10 bg-brand-muted/30">
                    <h2 class="text-sm font-bold text-gray-900">{{ __('borrower.rewards.recent_activity') }}</h2>
                </div>
                <ul class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                    @foreach ($transactions as $tx)
                        @php
                            $activityLabel = filled($tx->action_key ?? null)
                                ? __('borrower.rewards.actions.'.$tx->action_key)
                                : null;
                            if (! $activityLabel || $activityLabel === 'borrower.rewards.actions.'.($tx->action_key ?? '')) {
                                $activityLabel = $tx->description;
                            }
                        @endphp
                        <li class="px-5 py-3 flex justify-between gap-3 text-sm">
                            <span class="text-gray-700 min-w-0 truncate">{{ $activityLabel }}</span>
                            <span class="font-bold tabular-nums shrink-0 {{ $tx->points >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ $tx->points >= 0 ? '+' : '' }}{{ number_format($tx->points) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
