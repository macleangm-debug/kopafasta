@props([
    'kicker' => 'Kopafasta Plus',
    'title',
    'body' => null,
])

<section {{ $attributes->merge(['class' => 'kf-premium-panel rounded-2xl p-5 sm:p-6']) }}>
    <p class="relative text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ $kicker }}</p>
    <h1 class="relative text-2xl sm:text-3xl font-extrabold tracking-tight mt-2">{{ $title }}</h1>
    @if ($body)
        <p class="relative text-sm text-white/85 mt-2 max-w-xl">{{ $body }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="relative mt-4">
            {{ $slot }}
        </div>
    @endif
</section>
