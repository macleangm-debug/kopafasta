@props([
    'backUrl' => null,
    'backLabel' => null,
])

<div class="flex flex-wrap items-center justify-between gap-3">
    <a href="{{ $backUrl ?: route('site.borrower.plus.home') }}"
       class="inline-flex items-center gap-2 rounded-xl bg-brand-gold hover:brightness-95 text-brand px-4 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40">
        ← {{ $backLabel ?: __('plus.nav.home') }}
    </a>
    {{ $slot }}
</div>
