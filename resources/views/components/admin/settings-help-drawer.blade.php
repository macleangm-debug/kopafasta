@props([
    'page' => null,
    'pages' => null,
    'initialKey' => null,
    'ns' => null,
    'onDark' => false,
])

@php
    $fallback = static function (string $key): array {
        $label = str_replace(['-', '_', '.'], ' ', $key);

        return [
            'title' => ucwords($label),
            'summary' => 'Settings for '.$label.'. Change carefully — saved values affect live borrower, partner, and ops behaviour.',
            'where' => 'Across the related admin and portal screens for this module.',
            'affects' => ['Live product behaviour after you save.'],
            'how_to' => [
                'Review the fields on this section.',
                'Change one control at a time.',
                'Save, then verify the related borrower/partner/admin screen.',
            ],
            'terms' => [],
        ];
    };

    $resolve = static function (string $key) use ($fallback): array {
        $help = config('settings_help.'.$key);
        return is_array($help) ? $help : $fallback($key);
    };

    $docs = [];
    if (is_array($pages) && $pages !== []) {
        foreach ($pages as $alpineKey => $configKey) {
            $docs[(string) $alpineKey] = $resolve(is_string($configKey) ? $configKey : (string) $alpineKey);
        }
        $initialKey = $initialKey ?? array_key_first($docs);
    } else {
        $key = (string) ($page ?: 'company');
        $docs = [$key => $resolve($key)];
        $initialKey = $key;
    }

    $buttonClass = $onDark
        ? 'inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-bold text-brand shadow-sm ring-1 ring-white/40 hover:bg-brand-gold hover:text-brand transition'
        : 'inline-flex items-center gap-1.5 rounded-xl bg-brand px-3 py-2 text-xs font-bold text-white shadow-sm ring-1 ring-brand/30 hover:bg-brand-light transition';
@endphp

<div x-data="{
        open: false,
        key: @js($initialKey),
        docs: @js($docs),
        get help() {
            return this.docs[this.key] || Object.values(this.docs)[0] || {};
        },
        get terms() {
            const t = this.help.terms || {};
            return Object.keys(t).map(k => ({ term: k, meaning: t[k] }));
        }
     }"
     @if ($ns)
        @settings-help-set.window="if ($event.detail?.ns === @js($ns) && $event.detail?.key) { key = $event.detail.key }"
     @endif
     class="contents">
    <button type="button"
            @click="open = true"
            class="{{ $buttonClass }}"
            title="How to use these settings">
        <span @class([
            'grid size-5 place-items-center rounded-full text-[11px] font-bold',
            'bg-brand text-white' => $onDark,
            'bg-white/20 text-white' => ! $onDark,
        ])>i</span>
        <span>Page guide</span>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[10060]" role="dialog" aria-modal="true" aria-label="Settings guide">
            <div class="absolute inset-0 bg-brand/40 backdrop-blur-[2px]" @click="open = false"></div>
            <aside class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl ring-1 ring-brand/10"
                   x-show="open"
                   x-transition:enter="transform transition ease-out duration-200"
                   x-transition:enter-start="translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transform transition ease-in duration-150"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="translate-x-full"
                   @keydown.escape.window="open = false">
                <div class="relative overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-6 text-white shrink-0">
                    <div class="pointer-events-none absolute -right-8 -top-8 size-32 rounded-full bg-brand-gold/20 blur-2xl"></div>
                    <div class="pointer-events-none absolute -bottom-10 left-10 size-28 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="relative flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.22em] text-brand-gold font-semibold">Admin page guide</p>
                            <h2 class="mt-1.5 text-xl font-bold leading-snug tracking-tight" x-text="help.title"></h2>
                        </div>
                        <button type="button" @click="open = false"
                                class="rounded-xl bg-white/10 px-3 py-1.5 text-sm font-semibold text-white hover:bg-white/20 ring-1 ring-white/20">
                            Close
                        </button>
                    </div>
                    <p class="relative mt-3 text-sm text-white/90 leading-relaxed" x-text="help.summary"></p>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5 space-y-7">
                    <template x-if="help.where">
                        <section>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="grid size-6 place-items-center rounded-lg bg-brand-muted text-brand text-[11px] font-bold">1</span>
                                <p class="text-[10px] uppercase tracking-widest font-bold text-brand">Where it shows up</p>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed pl-8" x-text="help.where"></p>
                        </section>
                    </template>

                    <template x-if="help.affects && help.affects.length">
                        <section>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="grid size-6 place-items-center rounded-lg bg-brand-muted text-brand text-[11px] font-bold">2</span>
                                <p class="text-[10px] uppercase tracking-widest font-bold text-brand">What it affects</p>
                            </div>
                            <ul class="space-y-2.5 pl-8">
                                <template x-for="(item, idx) in help.affects" :key="'a'+idx">
                                    <li class="flex gap-2.5 text-sm text-gray-700">
                                        <span class="mt-1.5 size-1.5 rounded-full bg-brand-gold shrink-0"></span>
                                        <span class="leading-relaxed" x-text="item"></span>
                                    </li>
                                </template>
                            </ul>
                        </section>
                    </template>

                    <template x-if="help.how_to && help.how_to.length">
                        <section>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="grid size-6 place-items-center rounded-lg bg-brand-muted text-brand text-[11px] font-bold">3</span>
                                <p class="text-[10px] uppercase tracking-widest font-bold text-brand">How to set it</p>
                            </div>
                            <ol class="space-y-2.5 pl-8 list-decimal text-sm text-gray-700">
                                <template x-for="(step, idx) in help.how_to" :key="'h'+idx">
                                    <li class="leading-relaxed ml-1 pl-1" x-text="step"></li>
                                </template>
                            </ol>
                        </section>
                    </template>

                    <template x-if="terms.length">
                        <section>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="grid size-6 place-items-center rounded-lg bg-brand-gold/30 text-brand text-[11px] font-bold">?</span>
                                <p class="text-[10px] uppercase tracking-widest font-bold text-brand">Meaning of terms</p>
                            </div>
                            <div class="space-y-3">
                                <template x-for="row in terms" :key="row.term">
                                    <div class="rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/10 px-4 py-3.5">
                                        <p class="text-sm font-semibold text-gray-900" x-text="row.term"></p>
                                        <p class="mt-1.5 text-sm text-gray-600 leading-relaxed" x-text="row.meaning"></p>
                                    </div>
                                </template>
                            </div>
                        </section>
                    </template>
                </div>

                <div class="border-t border-gray-100 px-5 py-4 shrink-0">
                    <p class="text-[11px] text-gray-500 leading-relaxed">
                        Tip: change one section, save, then verify the related live screen before the next change.
                    </p>
                </div>
            </aside>
        </div>
    </template>
</div>
