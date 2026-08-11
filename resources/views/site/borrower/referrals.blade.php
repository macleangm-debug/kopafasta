<x-site.borrower-layout :title="brand_title(__('borrower.referrals.title'))" active="referrals" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.referrals.grow')"
        :title="__('borrower.referrals.rewards_title')"
        :subtitle="__('borrower.referrals.rewards_subtitle')"
    />

    @php
        $current = (int) ($progress['current'] ?? 0);
        $target = max(1, (int) ($progress['target'] ?? 5));
        $barFilled = min(8, (int) floor(($current / $target) * 8));
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2 glass-card p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.referrals.level') }}: {{ $level['label'] ?? 'Bronze' }}</p>
                    <p class="mt-2 text-sm text-gray-600">{{ __('borrower.referrals.your_code') }}: <span class="font-mono font-bold text-gray-900">{{ $referralCode }}</span></p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.progress') }}</p>
                    <p class="mt-1 font-mono text-sm tracking-widest text-brand">{{ str_repeat('■', $barFilled) }}{{ str_repeat('□', 8 - $barFilled) }}</p>
                    <p class="text-xs text-gray-600 mt-1">{{ __('borrower.referrals.progress_count', ['current' => $current, 'target' => $target]) }}</p>
                </div>
            </div>

            @if ($progress['next_reward'] ?? null)
                <div class="mt-5 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.next_reward') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-1">{{ $progress['next_reward'] }}</p>
                </div>
            @endif

            <p class="mt-5 text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.your_link') }}</p>
            <p class="mt-2 text-sm break-all text-gray-800 bg-gray-50 rounded-xl px-4 py-3 ring-1 ring-gray-200 font-mono">{{ $referralLink }}</p>

            <div class="mt-6">
                <x-site.referral-share :link="$referralLink" :code="$referralCode" :message="$referralShareMessage ?? null" />
            </div>

            <p class="mt-4 text-xs text-gray-500">{{ __('borrower.referrals.complete_note') }}</p>
        </section>

        <section class="glass-card p-6 flex flex-col">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.current_wallet') }}</p>
            <p class="mt-3 text-4xl font-black text-gray-900 tabular-nums leading-none">{{ format_money($referralWallet->balance ?? 0) }}</p>
            <p class="text-sm text-gray-600 mt-3">{{ __('borrower.referrals.available_withdraw') }}</p>

            @if ($yourRank)
                <p class="mt-4 text-xs text-brand font-semibold">{{ __('borrower.referrals.your_rank', ['rank' => $yourRank]) }}</p>
            @endif

            <div class="mt-auto pt-6 space-y-2">
                <a href="{{ route('site.borrower.profile', ['section' => 'membership']) }}" class="block text-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.referrals.view_membership') }} →
                </a>
            </div>
        </section>
    </div>

    @if (($level['benefits'] ?? []) !== [])
        <section class="mt-6 glass-card p-6">
            <h2 class="font-semibold text-gray-900">{{ __('borrower.referrals.level_benefits', ['level' => $level['label'] ?? '']) }}</h2>
            <ul class="mt-3 grid sm:grid-cols-2 gap-2 text-sm text-gray-700">
                @foreach ($level['benefits'] as $benefit)
                    <li class="flex items-start gap-2"><span class="text-emerald-600">✓</span><span>{{ is_array($benefit) ? ($benefit['label'] ?? '') : $benefit }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($rewardHistory->isNotEmpty())
        <section class="mt-6 glass-card p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="font-semibold text-gray-900">{{ __('borrower.referrals.reward_history') }}</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($rewardHistory as $tx)
                    <li class="py-3 flex items-center justify-between gap-3 text-sm">
                        <div>
                            <p class="font-medium text-gray-900">{{ $tx->description }}</p>
                            <p class="text-xs text-gray-500">{{ $tx->created_at?->format('d M Y') }}</p>
                        </div>
                        <span class="font-semibold {{ $tx->type === 'credit' ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $tx->type === 'credit' ? '+' : '−' }}{{ format_money($tx->amount) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($leaderboard->isNotEmpty())
        <section class="mt-6 glass-card p-6">
            <h2 class="font-semibold text-gray-900">{{ __('borrower.referrals.leaderboard_title') }}</h2>
            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.referrals.leaderboard_subtitle') }}</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-widest text-gray-500">
                            <th class="pb-2 pr-4">#</th>
                            <th class="pb-2 pr-4">{{ __('borrower.referrals.leaderboard_member') }}</th>
                            <th class="pb-2">{{ __('borrower.referrals.leaderboard_referrals') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($leaderboard as $row)
                            <tr>
                                <td class="py-2 pr-4 font-semibold text-brand">{{ $row['rank'] }}</td>
                                <td class="py-2 pr-4">{{ $row['display_name'] }} <span class="text-gray-400">· {{ $row['member_no'] }}</span></td>
                                <td class="py-2 font-semibold">{{ $row['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

</x-site.borrower-layout>
