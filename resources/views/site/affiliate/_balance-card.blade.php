@props([
    'available' => 0,
    'inProgress' => 0,
])

<div class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-5 py-4 min-w-[14rem] w-full">
    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.hero_available') }}</p>
    <p class="text-2xl font-extrabold tabular-nums mt-1" title="{{ format_money($available) }}">{{ format_money($available) }}</p>
    @if ($inProgress > 0)
        <p class="text-xs text-white/70 mt-1">{{ __('site.affiliate_portal.hero_in_progress', ['amount' => format_money($inProgress)]) }}</p>
    @endif
</div>
