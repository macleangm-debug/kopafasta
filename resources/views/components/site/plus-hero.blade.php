@props([
    'kicker' => 'Kopafasta Plus',
    'title',
    'body' => null,
    'backUrl' => null,
    'backLabel' => null,
    'showBack' => true,
])

@php
    $backUrl = $backUrl ?: route('site.borrower.plus.home');
    $backLabel = $backLabel ?: __('plus.nav.home');
@endphp

<section {{ $attributes->merge(['class' => 'kf-premium-panel rounded-2xl p-5 sm:p-6']) }}>
    <div class="relative flex items-start justify-between gap-3">
        <x-site.brand-mark size="sm" variant="light" />
    </div>
    <p class="relative text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold mt-4">{{ $kicker }}</p>
    <h1 class="relative text-2xl sm:text-3xl font-extrabold tracking-tight mt-2">{{ $title }}</h1>
    @if ($body)
        <p class="relative text-sm text-white/85 mt-2 max-w-xl">{{ $body }}</p>
    @endif

    @if ($showBack)
        <div class="relative mt-4 flex flex-wrap gap-2">
            <a href="{{ $backUrl }}"
               data-loading="click"
               class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white text-brand hover:bg-white/90 shadow-sm">
                {{ $backLabel }}
            </a>
        </div>
    @endif

    @if ($slot->isNotEmpty())
        <div class="relative mt-4">
            {{ $slot }}
        </div>
    @endif
</section>
