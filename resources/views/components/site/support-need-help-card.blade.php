@props([
    'supportUrl' => null,
    'activeConversation' => null,
    'openCount' => null,
    'isSw' => null,
])

@php
    $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
    $supportUrl = $supportUrl ?? route('site.borrower.support');
    $active = $activeConversation;
    $opens = $openCount !== null ? (int) $openCount : ($active ? 1 : 0);
    if ($opens > 1) {
        $ctaUrl = str_contains($supportUrl, '?') ? $supportUrl.'&section=active' : $supportUrl.'?section=active';
        $ctaLabel = $isSw ? 'Endelea na mazungumzo' : 'Continue conversation';
    } elseif ($active) {
        $ctaUrl = str_contains($supportUrl, '?')
            ? $supportUrl.'&chat=1&section=active&conversation='.$active->id
            : $supportUrl.'?chat=1&section=active&conversation='.$active->id;
        $ctaLabel = $isSw ? 'Endelea na mazungumzo' : 'Continue conversation';
    } else {
        $ctaUrl = $supportUrl;
        $ctaLabel = $isSw ? 'Pata msaada' : 'Get help';
    }
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
                    @if ($opens > 1)
                        · {{ $isSw ? $opens.' mazungumzo yanayoendelea' : $opens.' open conversations' }}
                    @elseif ($active && $status !== '')
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
