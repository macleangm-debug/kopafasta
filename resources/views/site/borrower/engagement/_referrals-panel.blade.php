@php
    $current = (int) ($progress['current'] ?? 0);
    $target = max(1, (int) ($progress['target'] ?? 5));
    $pct = min(100, (int) round(($current / $target) * 100));
@endphp

<div class="space-y-4">
    <div class="glass-card overflow-hidden ring-1 ring-brand/10">
        <div class="relative bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-8 py-6 text-white">
            <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)]" aria-hidden="true"></div>
            <div class="relative space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.referrals.grow') }}</p>
                        <h2 class="text-xl sm:text-2xl font-bold mt-1 tracking-tight">{{ __('borrower.referrals.rewards_title') }}</h2>
                        <p class="text-sm text-white/80 mt-1.5">{{ __('borrower.referrals.your_code') }}:
                            <span class="font-mono font-bold text-white">{{ $referralCode }}</span>
                        </p>
                    </div>
                    <div class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-4 py-3 text-right shrink-0">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.referrals.level') }}</p>
                        <p class="text-lg font-bold text-white mt-0.5">{{ $level['label'] ?? 'Bronze' }}</p>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="font-semibold text-white/90">{{ __('borrower.referrals.progress') }}</span>
                        <span class="tabular-nums text-brand-gold font-bold">{{ __('borrower.referrals.progress_count', ['current' => $current, 'target' => $target]) }}</span>
                    </div>
                    <div class="mt-2 h-2.5 rounded-full bg-white/15 overflow-hidden">
                        <div class="h-full rounded-full bg-brand-gold transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                    @if ($progress['next_reward'] ?? null)
                        <p class="mt-2 text-xs text-white/75">
                            <span class="text-brand-gold font-semibold">{{ __('borrower.referrals.next_reward') }}:</span>
                            {{ $progress['next_reward'] }}
                        </p>
                    @endif
                </div>

                <div class="rounded-2xl bg-black/15 ring-1 ring-white/10 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.referrals.your_link') }}</p>
                    <p class="mt-1.5 text-xs sm:text-sm break-all font-mono text-white/90 leading-relaxed">{{ $referralLink }}</p>
                </div>

                <x-site.referral-share :link="$referralLink" :code="$referralCode" :message="$referralShareMessage ?? null" tone="on-brand" />

                <p class="text-[11px] text-white/60">{{ __('borrower.referrals.complete_note') }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <section class="glass-card p-5 ring-1 ring-brand/10 bg-gradient-to-br from-brand-muted/50 to-white">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.current_wallet') }}</p>
            <p class="mt-2 text-3xl sm:text-4xl font-black text-brand tabular-nums leading-none">
                {{ number_format(wallet_balance_as_points($referralWallet->balance ?? 0)) }}
            </p>
            <p class="text-sm text-gray-600 mt-2">{{ __('borrower.referrals.available_withdraw') }}</p>
            @if ($yourRank)
                <p class="mt-3 text-xs font-bold text-brand">{{ __('borrower.referrals.your_rank', ['rank' => $yourRank]) }}</p>
            @endif
            <a href="{{ route('site.borrower.profile', ['section' => 'membership']) }}"
               class="mt-5 inline-flex w-full justify-center font-bold px-4 py-2.5 rounded-xl text-sm bg-brand text-white hover:bg-brand-light">
                {{ __('borrower.referrals.view_membership') }}
            </a>
        </section>

        @if (($level['benefits'] ?? []) !== [])
            <section class="glass-card p-5 ring-1 ring-brand/10">
                <h2 class="text-sm font-bold text-gray-900">{{ __('borrower.referrals.level_benefits', ['level' => $level['label'] ?? '']) }}</h2>
                <ul class="mt-3 space-y-2 text-sm text-gray-700">
                    @foreach ($level['benefits'] as $benefit)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 size-5 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center text-[10px] font-bold shrink-0">✓</span>
                            <span>{{ is_array($benefit) ? ($benefit['label'] ?? '') : $benefit }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @else
            <section class="glass-card p-5 ring-1 ring-brand/10 flex flex-col justify-center">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.progress') }}</p>
                <p class="mt-2 text-2xl font-black text-brand tabular-nums">{{ $pct }}%</p>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.referrals.progress_count', ['current' => $current, 'target' => $target]) }}</p>
            </section>
        @endif
    </div>

    @if ($rewardHistory->isNotEmpty())
        <section class="glass-card overflow-hidden ring-1 ring-brand/10">
            <div class="px-5 py-4 border-b border-brand/10 bg-brand-muted/30">
                <h2 class="text-sm font-bold text-gray-900">{{ __('borrower.referrals.reward_history') }}</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($rewardHistory as $tx)
                    <li class="px-5 py-3.5 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $tx->description }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $tx->created_at?->format('d M Y') }}</p>
                        </div>
                        <span class="shrink-0 font-bold tabular-nums {{ $tx->type === 'credit' ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $tx->type === 'credit' ? '+' : '−' }}{{ format_money($tx->amount) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($leaderboard->isNotEmpty())
        <section class="glass-card overflow-hidden ring-1 ring-brand/10">
            <div class="px-5 py-4 border-b border-brand/10 bg-brand-muted/30">
                <h2 class="text-sm font-bold text-gray-900">{{ __('borrower.referrals.leaderboard_title') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.referrals.leaderboard_subtitle') }}</p>
            </div>
            <ol class="divide-y divide-gray-100">
                @foreach ($leaderboard as $row)
                    <li class="px-5 py-3.5 flex items-center gap-3">
                        <span @class([
                            'size-8 rounded-full grid place-items-center text-xs font-black shrink-0',
                            'bg-brand-gold text-brand' => (int) $row['rank'] <= 3,
                            'bg-brand-muted text-brand' => (int) $row['rank'] > 3,
                        ])>{{ $row['rank'] }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $row['display_name'] }}</p>
                            <p class="text-[11px] text-gray-500 truncate">{{ $row['member_no'] }}</p>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-brand shrink-0">{{ $row['count'] }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
</div>
