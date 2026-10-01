@php
    $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
    $q = $isSw ? ($article['q_sw'] ?? $article['q_en'] ?? '') : ($article['q_en'] ?? $article['q_sw'] ?? '');
    $title = $isSw ? ($article['title_sw'] ?? $article['title_en'] ?? $q) : ($article['title_en'] ?? $article['title_sw'] ?? $q);
    $body = $isSw ? ($article['a_sw'] ?? $article['a_en'] ?? '') : ($article['a_en'] ?? $article['a_sw'] ?? '');
    $steps = $isSw ? ($article['steps_sw'] ?? $article['steps_en'] ?? []) : ($article['steps_en'] ?? $article['steps_sw'] ?? []);
    $kind = $article['kind'] ?? 'answer';
    $catLabel = $isSw ? ($article['category_label_sw'] ?? '') : ($article['category_label_en'] ?? '');
    $ctaRoute = $article['cta_route'] ?? null;
    $ctaLabel = $isSw ? ($article['cta_label_sw'] ?? $article['cta_label_en'] ?? null) : ($article['cta_label_en'] ?? $article['cta_label_sw'] ?? null);
    $ctaUrl = null;
    if ($ctaRoute && \Illuminate\Support\Facades\Route::has($ctaRoute)) {
        try {
            $ctaUrl = route($ctaRoute);
        } catch (\Throwable) {
            $ctaUrl = null;
        }
    }
    $supportHome = auth()->user()?->customer
        ? route('site.borrower.support')
        : (auth()->check() ? route('site.partner.support') : route('site.faq'));
    $howtoLabel = $howtoLabel ?? (str_starts_with(app()->getLocale(), 'sw') ? 'JINSI YA' : 'HOW TO');
@endphp

<div class="space-y-6">
    <a href="{{ route('site.help.category', $categoryKey) }}" class="text-sm font-semibold text-brand hover:underline">
        ← {{ $catLabel ?: ($isSw ? 'Rudi' : 'Back') }}
    </a>

    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="glass-card overflow-hidden ring-1 ring-brand/15">
        <div class="px-5 sm:px-6 py-5 space-y-4">
            <p class="text-sm font-semibold text-gray-900">{{ $q }}</p>

            @if ($kind === 'howto' && $steps !== [])
                <div class="rounded-xl bg-brand-muted/30 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $howtoLabel }}</p>
                    <p class="text-base font-bold text-gray-900 mt-1">{{ $title }}</p>
                    @if (filled($body))
                        <p class="text-sm text-gray-600 mt-1">{{ $body }}</p>
                    @endif
                    <ol class="mt-3 list-decimal ml-5 text-sm text-gray-800 space-y-1.5">
                        @foreach ($steps as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                </div>
            @else
                <p class="text-sm text-gray-700 leading-relaxed">{{ $body }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-3 pt-1">
                @if ($ctaUrl && $ctaLabel)
                    <a href="{{ $ctaUrl }}" class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                        {{ $ctaLabel }} →
                    </a>
                @endif
                <button type="button"
                        x-data="{ copied: false }"
                        @click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                        class="inline-flex items-center text-xs font-semibold text-brand hover:underline">
                    <span x-show="!copied">{{ $isSw ? 'Nakili kiungo' : 'Copy link' }}</span>
                    <span x-cloak x-show="copied">{{ $isSw ? 'Imenakiliwa' : 'Copied' }}</span>
                </button>
            </div>
        </div>
    </div>

    <div class="glass-card px-5 py-4 ring-1 ring-brand/10" x-data="{ voted: @js(session('help_feedback_vote')) }">
        <p class="text-sm font-semibold text-gray-900">{{ $isSw ? 'Je, hii imekusaidia?' : 'Was this helpful?' }}</p>
        <div class="mt-3 flex flex-wrap gap-2" x-show="!voted">
            <form method="POST" action="{{ route('site.help.feedback', ['category' => $categoryKey, 'slug' => $slug]) }}">
                @csrf
                <input type="hidden" name="vote" value="yes">
                <button type="submit" class="rounded-xl bg-emerald-50 text-emerald-800 font-semibold text-sm px-4 py-2 ring-1 ring-emerald-200 hover:bg-emerald-100">
                    {{ $isSw ? 'Ndiyo' : 'Yes' }}
                </button>
            </form>
            <form method="POST" action="{{ route('site.help.feedback', ['category' => $categoryKey, 'slug' => $slug]) }}">
                @csrf
                <input type="hidden" name="vote" value="no">
                <button type="submit" class="rounded-xl bg-white text-gray-800 font-semibold text-sm px-4 py-2 ring-1 ring-gray-200 hover:bg-gray-50">
                    {{ $isSw ? 'Hapana' : 'No' }}
                </button>
            </form>
        </div>
        @if (session('help_feedback_vote') === 'no')
            <div class="mt-4 grid sm:grid-cols-2 gap-3">
                <a href="{{ $supportHome }}?chat=1" class="rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-3 text-center">
                    {{ $isSw ? 'Ongea na timu' : 'Talk to Support' }}
                </a>
                <button type="button" @click="$dispatch('open-feedback')" class="rounded-xl ring-1 ring-brand/20 text-brand font-bold text-sm px-4 py-3">
                    {{ $isSw ? 'Tuma maoni' : 'Send feedback' }}
                </button>
            </div>
        @endif
    </div>
</div>
