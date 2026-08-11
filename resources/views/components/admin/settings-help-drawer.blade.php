@props([
    'page' => 'company',
])

@php
    $help = config('settings_help.'.$page);
    if (! is_array($help)) {
        $label = str_replace('-', ' ', (string) $page);
        $help = [
            'title' => ucwords($label),
            'summary' => 'Settings for '.$label.'. Change carefully — saved values affect live borrower, partner, and ops behaviour.',
            'where' => 'Across the related admin and portal screens for this module.',
            'affects' => [
                'Live product behaviour after you save.',
            ],
            'how_to' => [
                'Review the fields on this page.',
                'Change one section at a time.',
                'Save, then verify the related borrower/partner/admin screen.',
            ],
            'terms' => [],
        ];
    }
@endphp

<div x-data="{ open: false }" class="contents">
    <button type="button"
            @click="open = true"
            class="inline-flex items-center gap-1.5 rounded-xl bg-white px-2.5 py-1.5 text-xs font-semibold text-brand ring-1 ring-brand/20 hover:bg-brand-muted/50 transition"
            title="How to use these settings">
        <span class="grid size-5 place-items-center rounded-full bg-brand text-white text-[11px] font-bold">i</span>
        <span class="hidden sm:inline">Page guide</span>
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
                <div class="relative overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-6 text-white">
                    <div class="pointer-events-none absolute -right-8 -top-8 size-32 rounded-full bg-brand-gold/20 blur-2xl"></div>
                    <div class="pointer-events-none absolute -bottom-10 left-10 size-28 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="relative flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.22em] text-brand-gold font-semibold">Admin page guide</p>
                            <h2 class="mt-1.5 text-xl font-bold leading-snug tracking-tight">{{ $help['title'] }}</h2>
                        </div>
                        <button type="button" @click="open = false"
                                class="rounded-xl bg-white/10 px-3 py-1.5 text-sm font-semibold text-white hover:bg-white/20 ring-1 ring-white/20">
                            Close
                        </button>
                    </div>
                    <p class="relative mt-3 text-sm text-white/90 leading-relaxed">{{ $help['summary'] }}</p>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5 space-y-7">
                    @if (! empty($help['where']))
                        <section>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="grid size-6 place-items-center rounded-lg bg-brand-muted text-brand text-[11px] font-bold">1</span>
                                <p class="text-[10px] uppercase tracking-widest font-bold text-brand">Where it shows up</p>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed pl-8">{{ $help['where'] }}</p>
                        </section>
                    @endif

                    @if (! empty($help['affects']))
                        <section>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="grid size-6 place-items-center rounded-lg bg-brand-muted text-brand text-[11px] font-bold">2</span>
                                <p class="text-[10px] uppercase tracking-widest font-bold text-brand">What it affects</p>
                            </div>
                            <ul class="space-y-2.5 pl-8">
                                @foreach ($help['affects'] as $item)
                                    <li class="flex gap-2.5 text-sm text-gray-700">
                                        <span class="mt-1.5 size-1.5 rounded-full bg-brand-gold shrink-0"></span>
                                        <span class="leading-relaxed">{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if (! empty($help['how_to']))
                        <section>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="grid size-6 place-items-center rounded-lg bg-brand-muted text-brand text-[11px] font-bold">3</span>
                                <p class="text-[10px] uppercase tracking-widest font-bold text-brand">How to set it</p>
                            </div>
                            <ol class="space-y-2.5 pl-8 list-decimal text-sm text-gray-700">
                                @foreach ($help['how_to'] as $step)
                                    <li class="leading-relaxed ml-1 pl-1">{{ $step }}</li>
                                @endforeach
                            </ol>
                        </section>
                    @endif

                    @if (! empty($help['terms']))
                        <section>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="grid size-6 place-items-center rounded-lg bg-brand-gold/30 text-brand text-[11px] font-bold">?</span>
                                <p class="text-[10px] uppercase tracking-widest font-bold text-brand">Meaning of terms</p>
                            </div>
                            <div class="space-y-3 pl-0">
                                @foreach ($help['terms'] as $term => $meaning)
                                    <div class="rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/10 px-4 py-3.5">
                                        <p class="text-sm font-semibold text-gray-900">{{ $term }}</p>
                                        <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">{{ $meaning }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>

                <div class="border-t border-gray-100 px-5 py-4">
                    <p class="text-[11px] text-gray-500 leading-relaxed">
                        Tip: change one section, save, then verify the related live screen before the next change.
                    </p>
                </div>
            </aside>
        </div>
    </template>
</div>
