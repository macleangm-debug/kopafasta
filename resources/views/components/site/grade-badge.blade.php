@props([
    'grade' => 'bronze',
    'plus' => false,
    'size' => 'md',
])

@php
    $grade = strtolower((string) ($grade ?: 'bronze'));
    $palette = [
        'bronze' => 'from-[#8B5A2B] via-[#E0B07A] to-[#6F4518] text-[#2C1810] ring-[#F6DEBA]',
        'silver' => 'from-slate-400 via-slate-100 to-slate-500 text-slate-800 ring-white/80',
        'gold' => 'from-amber-600 via-yellow-200 to-amber-500 text-amber-950 ring-yellow-100',
        'platinum' => 'from-slate-400 via-white to-cyan-200 text-slate-800 ring-white',
    ];
    $pad = match ($size) {
        'sm' => 'px-2.5 py-0.5 text-[10px] gap-1',
        'lg' => 'px-4 py-1.5 text-sm gap-2',
        default => 'px-3 py-1 text-xs gap-1.5',
    };
    $gem = $size === 'lg' ? 'size-3' : 'size-2.5';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-gradient-to-br shadow-md ring-2 font-extrabold uppercase tracking-[0.16em] '.$pad.' '.($palette[$grade] ?? $palette['bronze'])]) }}>
    <svg class="{{ $gem }} fill-current opacity-90" viewBox="0 0 8 8" aria-hidden="true"><path d="M4 0 8 4 4 8 0 4Z"/></svg>
    {{ strtoupper($grade) }}
    @if ($plus)
        <span class="normal-case tracking-wide font-bold">· {{ __('plus.card.plus') }}</span>
    @endif
</span>
