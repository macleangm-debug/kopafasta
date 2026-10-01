<div class="space-y-6">
    <a href="{{ $supportHome }}" class="text-sm font-semibold text-brand hover:underline">
        ← {{ $isSw ? 'Rudi Kituo cha Usaidizi' : 'Back to Help Centre' }}
    </a>

    <section class="relative overflow-hidden rounded-3xl kf-premium-panel px-5 sm:px-8 py-7">
        <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ $isSw ? 'Kituo cha Usaidizi' : 'Help Centre' }}</p>
        <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight text-white">
            <span class="mr-2" aria-hidden="true">{{ $group['icon'] ?? '📘' }}</span>{{ $label }}
        </h1>
        <p class="mt-2 text-sm text-white/80">
            {{ $isSw ? ('Mada '.count($articles)) : (count($articles).' topics') }}
        </p>
    </section>

    @foreach ($subgroups as $subLabel => $items)
        <section class="space-y-2">
            <h2 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ $subLabel }}</h2>
            @foreach ($items as $article)
                @php
                    $q = $isSw ? ($article['q_sw'] ?? $article['q_en']) : ($article['q_en'] ?? $article['q_sw']);
                    $slug = $article['slug'] ?? '';
                @endphp
                <a href="{{ route('site.help.article', ['category' => $categoryKey, 'slug' => $slug]) }}"
                   class="block glass-card rounded-2xl px-4 py-3.5 hover:ring-brand/30 transition">
                    <p class="text-sm font-semibold text-gray-900">{{ $q }}</p>
                </a>
            @endforeach
        </section>
    @endforeach
</div>
