{{-- Shared Help Centre landing body (borrower + partner + public). Single surface. --}}
@php
    $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
    $helpCategories = $helpCategories ?? [];
    $helpGroups = $helpGroups ?? [];
    $helpResults = $helpResults ?? [];
    $helpQuery = $helpQuery ?? '';
    $homeUrl = $homeUrl ?? route('site.borrower.support');
    $chatUrl = $chatUrl ?? route('site.borrower.support', ['chat' => 1]);
    $phones = $phones ?? support_phones();
    $primaryPhone = $primaryPhone ?? ($phones[0] ?? support_contact('phone'));
    $supportHours = class_exists(\App\Models\Setting::class)
        ? (\App\Models\Setting::get('company.support_hours') ?: \App\Models\Setting::get('company.hours'))
        : null;
    $howtoLabel = $isSw ? 'JINSI YA' : 'HOW TO';
    $initialTopic = (string) ($helpTopic ?? request('topic', ''));
    $initialArticle = (string) ($helpArticle ?? request('article', ''));
    $showChatCard = $showChatCard ?? true;

    $surfaceGroups = collect($helpGroups)->map(function (array $g) use ($isSw) {
        $key = (string) ($g['key'] ?? '');
        $articles = collect($g['articles'] ?? [])->map(function (array $a) use ($isSw, $key) {
            $slug = (string) ($a['slug'] ?? '');
            $kind = (string) ($a['kind'] ?? 'answer');
            $steps = $isSw
                ? array_values(array_filter(array_map('strval', (array) ($a['steps_sw'] ?? $a['steps_en'] ?? []))))
                : array_values(array_filter(array_map('strval', (array) ($a['steps_en'] ?? $a['steps_sw'] ?? []))));
            $ctaRoute = $a['cta_route'] ?? null;
            $ctaUrl = null;
            if ($ctaRoute && \Illuminate\Support\Facades\Route::has($ctaRoute)) {
                try {
                    $ctaUrl = route($ctaRoute);
                } catch (\Throwable) {
                    $ctaUrl = null;
                }
            }

            return [
                'slug' => $slug,
                'kind' => $kind,
                'title' => $isSw
                    ? (string) ($a['q_sw'] ?? $a['q_en'] ?? $a['title_sw'] ?? $a['title_en'] ?? '')
                    : (string) ($a['q_en'] ?? $a['q_sw'] ?? $a['title_en'] ?? $a['title_sw'] ?? ''),
                'howto_title' => $isSw
                    ? (string) ($a['title_sw'] ?? $a['title_en'] ?? '')
                    : (string) ($a['title_en'] ?? $a['title_sw'] ?? ''),
                'body' => $isSw
                    ? (string) ($a['a_sw'] ?? $a['a_en'] ?? '')
                    : (string) ($a['a_en'] ?? $a['a_sw'] ?? ''),
                'steps' => $steps,
                'cta_url' => $ctaUrl,
                'cta_label' => $isSw
                    ? ($a['cta_label_sw'] ?? $a['cta_label_en'] ?? null)
                    : ($a['cta_label_en'] ?? $a['cta_label_sw'] ?? null),
                'share_url' => ($key !== '' && $slug !== '')
                    ? route('site.help.article', ['category' => $key, 'slug' => $slug])
                    : null,
            ];
        })->values()->all();

        return [
            'key' => $key,
            'label' => $isSw
                ? (string) ($g['label_sw'] ?? $g['label_en'] ?? '')
                : (string) ($g['label_en'] ?? $g['label_sw'] ?? ''),
            'icon' => (string) ($g['icon'] ?? '📘'),
            'topic_count' => count($articles),
            'articles' => $articles,
        ];
    })->filter(fn ($g) => ($g['key'] ?? '') !== '')->values()->all();
@endphp

<div
    x-data="helpCentreSurface({
        groups: @js($surfaceGroups),
        initialTopic: @js($initialTopic),
        initialArticle: @js($initialArticle),
        howtoLabel: @js($howtoLabel),
        copyLabel: @js($isSw ? 'Nakili kiungo' : 'Copy link'),
        copiedLabel: @js($isSw ? 'Imenakili ✓' : 'Copied ✓'),
        topicCountPrefix: @js($isSw ? 'Mada ' : ''),
        topicCountSuffix: @js($isSw ? '' : ' topics'),
        homePath: @js(parse_url($homeUrl, PHP_URL_PATH) ?: '/support'),
    })"
    x-init="init()"
    class="space-y-6"
>
    @if ($helpQuery !== '')
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-3">
            <p class="text-sm font-semibold text-gray-900">{{ $isSw ? 'Matokeo' : 'Results' }} ({{ count($helpResults) }})</p>
            @forelse ($helpResults as $row)
                <button type="button"
                        @click="openFromSearch(@js($row['category'] ?? ''), @js($row['slug'] ?? ''))"
                        class="w-full text-left block rounded-xl bg-slate-50 hover:bg-brand-muted/30 px-4 py-3 transition">
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ $row['group'] }}</p>
                    <p class="text-sm font-bold text-gray-900 mt-1">{{ $row['title'] }}</p>
                    <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $row['body'] }}</p>
                </button>
            @empty
                <p class="text-sm text-gray-500">{{ $isSw ? 'Hakuna matokeo. Jaribu maneno mengine au Ongea na timu.' : 'No matches. Try different words or Talk to Support.' }}</p>
            @endforelse
        </section>
    @endif

    {{-- Category carousel (no “Chagua mada” heading — self-explanatory) --}}
    <section>
        <div class="flex items-center justify-end gap-2 mb-3">
            <div class="hidden sm:flex items-center gap-2">
                <button type="button" @click="scrollCarousel(-1)"
                        class="size-9 rounded-full bg-white ring-1 ring-brand/15 text-brand hover:bg-brand-muted/40 grid place-items-center"
                        aria-label="{{ $isSw ? 'Nyuma' : 'Previous' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button" @click="scrollCarousel(1)"
                        class="size-9 rounded-full bg-white ring-1 ring-brand/15 text-brand hover:bg-brand-muted/40 grid place-items-center"
                        aria-label="{{ $isSw ? 'Mbele' : 'Next' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
        <div x-ref="carousel"
             class="overflow-x-auto pb-2 -mx-1 px-1 snap-x snap-mandatory scrollbar-none"
             style="-webkit-overflow-scrolling: touch;">
            <div class="flex gap-3.5 w-max mx-auto sm:mx-0">
                <template x-for="cat in groups" :key="cat.key">
                    <button type="button"
                            @click="selectCategory(cat.key)"
                            class="snap-start shrink-0 w-[12.5rem] sm:w-[13.5rem] rounded-2xl bg-white ring-1 shadow-sm px-5 py-6 transition text-left"
                            :class="selectedKey === cat.key ? 'ring-brand/50 bg-brand-muted/30' : 'ring-brand/10 hover:ring-brand/30'">
                        <span class="text-3xl sm:text-4xl" aria-hidden="true" x-text="cat.icon"></span>
                        <p class="mt-3.5 text-[15px] font-bold text-gray-900 leading-snug" x-text="cat.label"></p>
                        <p class="mt-1.5 text-xs font-semibold text-brand/80"
                           x-text="topicCountPrefix + cat.topic_count + topicCountSuffix"></p>
                    </button>
                </template>
            </div>
        </div>
    </section>

    {{-- Premium content surface under carousel --}}
    <section x-show="selectedGroup" x-cloak>
        <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
            <div class="relative overflow-hidden px-5 sm:px-6 py-5 bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white">
                <div class="absolute -right-10 -top-10 size-36 rounded-full bg-brand-gold/15 pointer-events-none"></div>
                <div class="absolute -left-8 -bottom-12 size-28 rounded-full bg-white/5 pointer-events-none"></div>
                <div class="relative">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ $isSw ? 'Mada' : 'Topic' }}</p>
                    <h3 class="text-lg sm:text-xl font-bold tracking-tight mt-1" x-text="selectedGroup?.label"></h3>
                    <p class="text-xs text-white/70 mt-1"
                       x-text="topicCountPrefix + (selectedGroup?.topic_count || 0) + topicCountSuffix"></p>
                </div>
            </div>
            <ul class="divide-y divide-gray-100">
                <template x-for="article in (selectedGroup?.articles || [])" :key="article.slug">
                    <li>
                        <button type="button"
                                @click="selectArticle(article.slug)"
                                class="w-full text-left flex items-center justify-between gap-3 px-5 sm:px-6 py-3.5 transition"
                                :class="openSlug === article.slug ? 'bg-brand-muted/30' : 'hover:bg-slate-50'">
                            <span class="text-sm font-semibold text-gray-900" x-text="article.title"></span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="openSlug === article.slug ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <div x-show="openSlug === article.slug" x-cloak class="px-5 sm:px-6 pb-5 pt-1">
                            {{-- One expanded-card format for howto + Q&A --}}
                            <div class="rounded-xl bg-brand-muted/25 ring-1 ring-brand/10 px-4 py-4">
                                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold"
                                   x-text="article.kind === 'howto' && article.steps?.length ? howtoLabel : (article.kind === 'howto' ? howtoLabel : @js($isSw ? 'JIBU' : 'ANSWER'))"></p>
                                <p class="text-base font-bold text-gray-900 mt-1" x-text="article.howto_title || article.title"></p>
                                <p class="text-sm text-gray-600 mt-1" x-show="article.body" x-text="article.body"></p>
                                <ol class="mt-3 list-decimal ml-5 text-sm text-gray-800 space-y-1.5" x-show="article.steps?.length">
                                    <template x-for="(step, i) in (article.steps || [])" :key="i">
                                        <li x-text="step"></li>
                                    </template>
                                </ol>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 mt-4">
                                <template x-if="article.cta_url && article.cta_label">
                                    <a :href="article.cta_url"
                                       class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm"
                                       x-text="article.cta_label + ' →'"></a>
                                </template>
                                <button type="button"
                                        @click="copyLink(article)"
                                        class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-xs font-bold ring-1 transition"
                                        :class="copiedSlug === article.slug
                                            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                            : 'bg-white text-brand ring-brand/20 hover:bg-brand-muted/40'">
                                    <span x-text="copiedSlug === article.slug ? copiedLabel : copyLabel"></span>
                                </button>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </section>

    {{-- Get help — Anza mazungumzo primary; Feedback/Phone secondary. Chat and Feedback never share routing/state. --}}
    <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-4 sm:p-5 space-y-3">
        <div>
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">{{ $isSw ? 'Pata msaada' : 'Get help' }}</p>
            <p class="mt-1 text-sm text-gray-600">{{ $isSw ? 'Chagua njia inayokufaa sasa.' : 'Choose the path that fits right now.' }}</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <a href="{{ $chatUrl }}"
               data-kf-support-action="chat"
               class="rounded-2xl bg-brand text-white ring-1 ring-brand/30 hover:bg-brand-light px-4 sm:px-5 py-4 text-left transition shadow-sm sm:col-span-1">
                <p class="text-lg sm:text-xl" aria-hidden="true">💬</p>
                <p class="mt-2 text-sm font-bold leading-snug">{{ $isSw ? 'Anza mazungumzo' : 'Start a conversation' }}</p>
                <p class="mt-1 text-xs text-white/80 leading-snug">{{ $isSw ? 'Msaidizi wa Kopafasta — msaada saa 24.' : 'Kopafasta Assistant — help 24/7.' }}</p>
            </a>
            <button type="button"
                    data-kf-support-action="feedback"
                    @click.stop="$dispatch('open-feedback')"
                    class="rounded-2xl bg-slate-50 ring-1 ring-brand/10 hover:ring-brand/25 hover:bg-white px-4 sm:px-5 py-4 text-left transition">
                <p class="text-lg sm:text-xl" aria-hidden="true">✉️</p>
                <p class="mt-2 text-sm font-bold text-gray-900 leading-snug">{{ $isSw ? 'Tuma maoni' : 'Send feedback' }}</p>
                <p class="mt-1 text-xs text-gray-500 leading-snug">{{ $isSw ? 'Tuma swali, pendekezo au malalamiko.' : 'Send a question, suggestion or complaint.' }}</p>
            </button>
            @if ($primaryPhone)
                <a href="tel:{{ preg_replace('/\s+/', '', $primaryPhone) }}"
                   class="rounded-2xl bg-slate-50 ring-1 ring-brand/10 hover:ring-brand/25 hover:bg-white px-4 sm:px-5 py-4 text-left transition">
                    <p class="text-lg sm:text-xl" aria-hidden="true">☎️</p>
                    <p class="mt-2 text-sm font-bold text-gray-900 leading-snug">{{ $isSw ? 'Piga simu' : 'Call us' }}</p>
                    <p class="mt-1 text-xs text-brand font-semibold leading-snug tabular-nums">{{ $primaryPhone }}</p>
                    @if (filled($supportHours))
                        <p class="mt-0.5 text-[10px] text-gray-500">{{ $supportHours }}</p>
                    @endif
                </a>
            @else
                <div class="rounded-2xl bg-slate-50 ring-1 ring-brand/10 px-4 sm:px-5 py-4 text-left opacity-70">
                    <p class="text-lg sm:text-xl" aria-hidden="true">☎️</p>
                    <p class="mt-2 text-sm font-bold text-gray-900">{{ $isSw ? 'Piga simu' : 'Call us' }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $isSw ? 'Nambari itakuja hivi karibuni' : 'Number coming soon' }}</p>
                </div>
            @endif
        </div>
    </section>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('helpCentreSurface', (cfg) => ({
        groups: cfg.groups || [],
        selectedKey: cfg.initialTopic || '',
        openSlug: cfg.initialArticle || '',
        howtoLabel: cfg.howtoLabel,
        copyLabel: cfg.copyLabel,
        copiedLabel: cfg.copiedLabel,
        topicCountPrefix: cfg.topicCountPrefix || '',
        topicCountSuffix: cfg.topicCountSuffix || '',
        copiedSlug: null,
        homePath: cfg.homePath || '/support',
        get selectedGroup() {
            return this.groups.find((g) => g.key === this.selectedKey) || null;
        },
        init() {
            if (this.selectedKey && this.openSlug) {
                // deep link already set
            } else if (this.selectedKey && ! this.openSlug) {
                // category only
            }
            if (this.selectedKey) {
                this.$nextTick(() => this.scrollSelectedIntoView());
            }
            window.addEventListener('popstate', () => this.restoreFromUrl());
        },
        selectCategory(key) {
            this.selectedKey = key;
            this.openSlug = '';
            this.pushState();
            this.$nextTick(() => this.scrollSelectedIntoView());
        },
        selectArticle(slug) {
            this.openSlug = this.openSlug === slug ? '' : slug;
            this.pushState();
        },
        openFromSearch(category, slug) {
            if (!category) return;
            this.selectedKey = category;
            this.openSlug = slug || '';
            this.pushState();
            this.$nextTick(() => this.scrollSelectedIntoView());
        },
        scrollCarousel(dir) {
            const el = this.$refs.carousel;
            if (!el) return;
            el.scrollBy({ left: dir * Math.max(220, el.clientWidth * 0.6), behavior: 'smooth' });
        },
        scrollSelectedIntoView() {
            const el = this.$refs.carousel;
            if (!el || !this.selectedKey) return;
            const cards = el.querySelectorAll('button');
            const idx = this.groups.findIndex((g) => g.key === this.selectedKey);
            if (idx >= 0 && cards[idx]) {
                cards[idx].scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
            }
        },
        shareUrlFor(article) {
            // Exact article deep link — never category-only when slug exists.
            if (article?.share_url) return article.share_url;
            if (!this.selectedKey || !article?.slug) return window.location.href;
            return `${window.location.origin}/help/${this.selectedKey}/${article.slug}`;
        },
        copyLink(article) {
            const url = this.shareUrlFor(article);
            navigator.clipboard.writeText(url).then(() => {
                this.copiedSlug = article.slug;
                setTimeout(() => { if (this.copiedSlug === article.slug) this.copiedSlug = null; }, 2000);
            });
        },
        pushState() {
            let url = this.homePath + (this.homePath.includes('?') ? '&' : '?') + 'section=help';
            if (this.selectedKey && this.openSlug) {
                url = `/help/${this.selectedKey}/${this.openSlug}`;
            } else if (this.selectedKey) {
                url = `/help/${this.selectedKey}`;
            }
            const current = window.location.pathname + window.location.search;
            if (current !== url) {
                history.pushState({ help: true, topic: this.selectedKey, article: this.openSlug }, '', url);
            }
        },
        restoreFromUrl() {
            const path = window.location.pathname || '';
            const m = path.match(/^\/help\/([^\/]+)(?:\/([^\/]+))?/);
            if (m) {
                this.selectedKey = decodeURIComponent(m[1] || '');
                this.openSlug = decodeURIComponent(m[2] || '');
                return;
            }
            const params = new URLSearchParams(window.location.search || '');
            this.selectedKey = params.get('topic') || '';
            this.openSlug = params.get('article') || '';
        },
    }));
});
</script>
