@props([
    'code' => 'default',
    'size' => 'md',
])

@php
    $theme = config('loan_product_themes.'.strtoupper($code), config('loan_product_themes.default'));
    $illustration = $theme['illustration'] ?? 'wallet';
    $palette = match ($theme['theme'] ?? 'slate') {
        'indigo'  => ['from-indigo-500', 'to-indigo-700', 'ring-indigo-200/50', 'bg-indigo-100/40'],
        'violet'  => ['from-violet-500', 'to-violet-700', 'ring-violet-200/50', 'bg-violet-100/40'],
        'sky'     => ['from-sky-500', 'to-sky-700', 'ring-sky-200/50', 'bg-sky-100/40'],
        'amber'   => ['from-amber-500', 'to-amber-600', 'ring-amber-200/50', 'bg-amber-100/40'],
        'emerald' => ['from-emerald-500', 'to-emerald-700', 'ring-emerald-200/50', 'bg-emerald-100/40'],
        'orange'  => ['from-orange-500', 'to-orange-600', 'ring-orange-200/50', 'bg-orange-100/40'],
        'blue'    => ['from-blue-500', 'to-blue-700', 'ring-blue-200/50', 'bg-blue-100/40'],
        'rose'    => ['from-rose-500', 'to-rose-600', 'ring-rose-200/50', 'bg-rose-100/40'],
        'pink'    => ['from-pink-500', 'to-pink-600', 'ring-pink-200/50', 'bg-pink-100/40'],
        'cyan'    => ['from-cyan-500', 'to-cyan-600', 'ring-cyan-200/50', 'bg-cyan-100/40'],
        default   => ['from-brand', 'to-brand-light', 'ring-brand/20', 'bg-brand-muted/60'],
    };
    [$gradFrom, $gradTo, $ringColor, $blobColor] = $palette;
    $sizes = match ($size) {
        'sm'   => ['box' => 'size-16', 'pad' => 'p-3', 'showLabel' => false],
        'card' => ['box' => 'w-full aspect-[16/10]', 'pad' => 'p-5', 'showLabel' => false],
        'lg'   => ['box' => 'w-full aspect-square max-w-sm', 'pad' => 'p-10', 'showLabel' => true],
        'hero' => ['box' => 'w-full aspect-[4/3] lg:aspect-square lg:max-w-md', 'pad' => 'p-8 sm:p-10', 'showLabel' => true],
        default => ['box' => 'size-28 sm:size-32', 'pad' => 'p-5', 'showLabel' => false],
    };
@endphp

<div {{ $attributes->merge(['class' => "relative overflow-hidden rounded-3xl bg-gradient-to-br {$gradFrom} {$gradTo} {$sizes['box']} ring-1 {$ringColor} shadow-[0_20px_60px_rgba(0,77,64,0.15)]"]) }}>
    <div class="absolute -right-8 -top-8 size-32 rounded-full {{ $blobColor }} blur-2xl"></div>
    <div class="absolute -left-6 -bottom-10 size-28 rounded-full bg-white/10 blur-xl"></div>
    <svg class="absolute inset-0 w-full h-full opacity-15" viewBox="0 0 200 200" fill="none" aria-hidden="true">
        <circle cx="160" cy="40" r="28" stroke="white" stroke-width="1.5" stroke-dasharray="4 6"/>
        <path d="M30 90 Q100 30 170 110" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <div class="relative h-full flex flex-col items-center justify-center {{ $sizes['pad'] }} text-center gap-3">
        @include('components.site.illustrations.product', ['type' => $illustration])
        @if ($sizes['showLabel'])
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/85">{{ $theme['label'] ?? strtoupper($code) }}</p>
        @endif
    </div>
</div>
