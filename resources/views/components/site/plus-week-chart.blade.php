@props(['days' => []])

@php
    $days = is_array($days) ? $days : [];
    $max = max(1, collect($days)->max(fn ($d) => max((float) ($d['sold'] ?? 0), (float) ($d['spent'] ?? 0))) ?: 1);
@endphp

<div>
    <div class="flex items-center gap-3 text-[10px] uppercase tracking-widest text-white/60 mb-2">
        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-brand-gold"></span> {{ __('plus.business.sold') }}</span>
        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-white/45"></span> {{ __('plus.business.spent') }}</span>
    </div>
    <div class="grid gap-2 items-end h-28" style="grid-template-columns: repeat({{ max(1, count($days)) }}, minmax(0, 1fr));">
        @foreach ($days as $point)
            @php
                $soldH = (float) ($point['sold'] ?? 0) > 0 ? max(6, round(((float) $point['sold'] / $max) * 100)) : 0;
                $spentH = (float) ($point['spent'] ?? 0) > 0 ? max(6, round(((float) $point['spent'] / $max) * 100)) : 0;
            @endphp
            <div class="flex flex-col items-center gap-1 min-w-0">
                <div class="flex items-end justify-center gap-0.5 h-20 w-full">
                    <div class="w-2.5 rounded-t bg-brand-gold" style="height: {{ $soldH }}%" title="{{ format_money($point['sold'] ?? 0) }}"></div>
                    <div class="w-2.5 rounded-t bg-white/40" style="height: {{ $spentH }}%" title="{{ format_money($point['spent'] ?? 0) }}"></div>
                </div>
                <p class="text-[9px] text-white/70 truncate w-full text-center">{{ $point['label'] ?? '' }}</p>
            </div>
        @endforeach
    </div>
</div>
