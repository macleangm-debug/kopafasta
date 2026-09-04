@props([
    'dashboard' => [],
    'href' => null,
])

@php
    $dash = is_array($dashboard) ? $dashboard : [];
    $href = $href ?: route('site.borrower.engagement', ['tab' => 'rewards']);
    $balance = (int) ($dash['balance'] ?? 0);
    $next = $dash['next'] ?? null;
    $claimable = $dash['claimable'][0] ?? null;
    $toNext = (int) ($dash['to_next'] ?? 0);
    $highlight = $claimable['label'] ?? ($next['label'] ?? null);
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'block overflow-hidden rounded-2xl kf-premium-panel p-4 sm:p-5 hover:brightness-[1.03] transition']) }}>
    <div class="relative flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('borrower.rewards.title') }}</p>
            <p class="mt-2 text-3xl sm:text-4xl font-black tabular-nums text-white leading-none">
                {{ number_format($balance) }}
                <span class="text-sm font-semibold text-white/70">{{ __('borrower.rewards.points_short') }}</span>
            </p>
            @if ($claimable)
                <p class="mt-2 text-sm text-white/90">{{ $claimable['label'] }}</p>
            @elseif ($toNext > 0 && $highlight)
                <p class="mt-2 text-sm text-white/80">{{ __('borrower.rewards.to_next', ['points' => number_format($toNext)]) }}</p>
                <p class="mt-0.5 text-xs text-white/70">{{ __('borrower.rewards.next_reward') }} · {{ $highlight }}</p>
            @elseif ($highlight)
                <p class="mt-2 text-sm text-white/80">{{ $highlight }}</p>
            @else
                <p class="mt-2 text-sm text-white/75">{{ __('borrower.rewards.balance_hint') }}</p>
            @endif
            <p class="mt-3 text-sm font-bold text-brand-gold">{{ __('borrower.rewards.points_earned_cta') }} →</p>
        </div>
        <div class="kf-welcome-art shrink-0 opacity-90 hidden sm:block" aria-hidden="true">
            @include('components.site.illustrations.product', ['type' => 'rewards'])
        </div>
    </div>
    @if (($dash['progress'] ?? 0) > 0 && $toNext > 0)
        <div class="relative mt-4 h-1.5 rounded-full bg-white/15 overflow-hidden">
            <div class="h-full rounded-full bg-brand-gold" style="width: {{ max(6, min(100, (int) $dash['progress'])) }}%"></div>
        </div>
    @endif
</a>
