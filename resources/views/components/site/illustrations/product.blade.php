@props(['type' => 'wallet'])

@php
    $svg = match ($type) {
        'vehicle' => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[140px] h-auto" aria-hidden="true">
  <path d="M8 52h104l-8-24H16l-8 24z" stroke="white" stroke-width="2.5" stroke-linejoin="round"/>
  <circle cx="32" cy="56" r="8" stroke="white" stroke-width="2.5"/>
  <circle cx="88" cy="56" r="8" stroke="white" stroke-width="2.5"/>
  <path d="M44 28h32l8 12H36l8-12z" fill="rgba(255,255,255,0.2)" stroke="white" stroke-width="2"/>
</svg>
SVG,
        'business' => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[140px] h-auto" aria-hidden="true">
  <rect x="20" y="18" width="80" height="52" rx="4" stroke="white" stroke-width="2.5"/>
  <path d="M20 34h80" stroke="white" stroke-width="2"/>
  <rect x="32" y="42" width="18" height="18" rx="2" fill="rgba(255,255,255,0.25)"/>
  <rect x="70" y="42" width="18" height="18" rx="2" fill="rgba(255,255,255,0.15)"/>
  <path d="M48 18V10M72 18V10" stroke="white" stroke-width="2" stroke-linecap="round"/>
</svg>
SVG,
        'individual' => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[120px] h-auto" aria-hidden="true">
  <circle cx="60" cy="26" r="12" stroke="white" stroke-width="2.5"/>
  <path d="M36 62c0-13 11-22 24-22s24 9 24 22" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
  <path d="M78 30l8-6M42 30l-8-6" stroke="rgba(245,200,66,0.9)" stroke-width="2" stroke-linecap="round"/>
</svg>
SVG,
        'emergency' => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[120px] h-auto" aria-hidden="true">
  <rect x="48" y="12" width="24" height="56" rx="4" stroke="white" stroke-width="2.5"/>
  <path d="M60 24v20M50 34h20" stroke="white" stroke-width="3" stroke-linecap="round"/>
  <circle cx="60" cy="58" r="4" fill="rgba(245,200,66,0.9)"/>
</svg>
SVG,
        'education' => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[140px] h-auto" aria-hidden="true">
  <path d="M60 16L16 36l44 20 44-20-44-20z" stroke="white" stroke-width="2.5" stroke-linejoin="round"/>
  <path d="M28 44v14c0 8 14 14 32 14s32-6 32-14V44" stroke="white" stroke-width="2.5"/>
  <circle cx="88" cy="28" r="6" fill="rgba(245,200,66,0.85)"/>
</svg>
SVG,
        'group' => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[140px] h-auto" aria-hidden="true">
  <circle cx="40" cy="30" r="10" stroke="white" stroke-width="2"/>
  <circle cx="80" cy="30" r="10" stroke="white" stroke-width="2"/>
  <circle cx="60" cy="22" r="10" stroke="white" stroke-width="2.5"/>
  <path d="M24 62c0-10 8-16 16-16M96 62c0-10-8-16-16-16M44 62c0-8 7-14 16-14s16 6 16 14" stroke="white" stroke-width="2" stroke-linecap="round"/>
</svg>
SVG,
        'agriculture' => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[140px] h-auto" aria-hidden="true">
  <path d="M20 58h80" stroke="white" stroke-width="2" stroke-linecap="round"/>
  <path d="M60 58V28M60 28c-12 0-20-8-20-16M60 28c12 0 20-8 20-16" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
  <ellipse cx="60" cy="20" rx="8" ry="12" fill="rgba(255,255,255,0.2)" stroke="white" stroke-width="2"/>
</svg>
SVG,
        'women' => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[120px] h-auto" aria-hidden="true">
  <circle cx="60" cy="24" r="11" stroke="white" stroke-width="2.5"/>
  <path d="M38 62c0-12 10-20 22-20s22 8 22 20" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
  <path d="M60 46v16M52 58h16" stroke="rgba(245,200,66,0.9)" stroke-width="2.5" stroke-linecap="round"/>
</svg>
SVG,
        default => <<<'SVG'
<svg viewBox="0 0 120 80" fill="none" class="w-full max-w-[120px] h-auto" aria-hidden="true">
  <rect x="24" y="20" width="72" height="44" rx="8" stroke="white" stroke-width="2.5"/>
  <path d="M24 36h72" stroke="white" stroke-width="2"/>
  <circle cx="60" cy="48" r="6" fill="rgba(245,200,66,0.9)"/>
  <path d="M36 20V12h48v8" stroke="white" stroke-width="2" stroke-linejoin="round"/>
</svg>
SVG,
    };
@endphp

{!! $svg !!}
