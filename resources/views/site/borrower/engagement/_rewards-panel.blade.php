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
                    <li class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm">
                        <p class="font-semibold text-gray-900">{{ $reward->label }}</p>
                        @if ($reward->expires_at)
                            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.rewards.expires', ['date' => $reward->expires_at->format('d M Y')]) }}</p>
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
                <form method="POST" action="{{ route('site.borrower.engagement.redeem') }}" class="rounded-xl ring-1 ring-gray-200 bg-white p-4 flex flex-col hover:ring-brand/30 transition">
                    @csrf
                    <input type="hidden" name="option_key" value="{{ $option['key'] }}">
                    <p class="font-semibold text-gray-900">{{ $option['label'] }}</p>
                    @if ($option['description'])
                        <p class="text-sm text-gray-600 mt-2 flex-1">{{ $option['description'] }}</p>
                    @endif
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mt-4">{{ __('borrower.rewards.cost', ['points' => number_format($option['points'])]) }}</p>
                    <button type="submit"
                            @disabled($pointsBalance < $option['points'])
                            class="mt-3 w-full text-center text-sm font-semibold px-4 py-2.5 rounded-xl {{ $pointsBalance >= $option['points'] ? 'bg-brand hover:bg-brand-light text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">
                        {{ __('borrower.rewards.redeem_button') }}
                    </button>
                </form>
            @endforeach
        </div>
    </section>
</section>

<aside class="space-y-6">
    <section class="glass-card p-6">
        <h2 class="font-semibold text-gray-900">{{ __('borrower.rewards.earn_more') }}</h2>
        <ul class="mt-3 space-y-2 text-sm text-gray-700">
            <li>• {{ __('borrower.engagement.next_action.complete_profile') }}</li>
            <li>• {{ __('borrower.engagement.next_action.refer') }}</li>
            <li>• {{ __('borrower.engagement.next_action.repay_on_time') }}</li>
        </ul>
        <button type="button" @click="tab = 'referrals'" class="mt-4 block w-full text-center text-sm font-semibold text-brand hover:underline">
            {{ __('borrower.engagement.tabs.referrals') }} →
        </button>
    </section>

    @if ($transactions->isNotEmpty())
        <section class="glass-card p-6">
            <h2 class="font-semibold text-gray-900">{{ __('borrower.rewards.recent_activity') }}</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach ($transactions as $tx)
                    <li class="flex justify-between gap-3 border-b border-gray-100 pb-2 last:border-0">
                        <span class="text-gray-700">{{ $tx->description }}</span>
                        <span class="font-semibold tabular-nums {{ $tx->points >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $tx->points >= 0 ? '+' : '' }}{{ number_format($tx->points) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</aside>
