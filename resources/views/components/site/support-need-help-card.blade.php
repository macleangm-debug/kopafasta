@props([
    'supportUrl' => null,
    'activeConversation' => null,
    'isSw' => null,
])

@php
    $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
    $supportUrl = $supportUrl ?? route('site.borrower.support');
    $active = $activeConversation;
    $ctaUrl = $active
        ? (str_contains($supportUrl, '?') ? $supportUrl.'&chat=1' : $supportUrl.'?chat=1&section=active')
        : $supportUrl;
    $ctaLabel = $active
        ? ($isSw ? 'Endelea na mazungumzo' : 'Continue conversation')
        : ($isSw ? 'Pata msaada' : 'Get help');
    $status = $active ? (string) ($active->status ?? '') : '';
@endphp

<section class="mb-6">
    <a href="{{ $ctaUrl }}"
       class="block rounded-2xl bg-gradient-to-br from-brand via-[#127A5F] to-[#0a4a3c] text-white ring-1 ring-brand/20 shadow-sm px-5 py-4 hover:brightness-105 transition">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-semibold">
                    {{ $isSw ? 'Unahitaji msaada?' : 'Need help?' }}
                </p>
                <p class="mt-1 text-base font-bold leading-snug">Msaidizi wa Kopafasta</p>
                <p class="mt-0.5 text-xs text-white/80">
                    {{ $isSw ? 'Msaada saa 24' : 'Available 24/7' }}
                    @if ($active && $status !== '')
                        · {{ $isSw ? 'Mazungumzo yanaendelea' : 'Conversation in progress' }}
                    @endif
                </p>
            </div>
            <span class="shrink-0 inline-flex items-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 shadow-sm">
                {{ $ctaLabel }}
            </span>
        </div>
    </a>
</section>
