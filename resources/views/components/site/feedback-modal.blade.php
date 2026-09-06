@props([
    'name' => 'feedback',
    'title' => null,
    'message' => null,
    'okLabel' => null,
])

@php
    $defaultTitle = $title ?? __('borrower.feedback.title');
    $defaultOk = $okLabel ?? __('borrower.feedback.ok');
@endphp

<div
    x-data="{
        open: false,
        title: @js($defaultTitle),
        message: @js($message),
        lines: [],
        statuses: [],
        actionRequired: null,
        secondaryLabel: null,
        secondaryHref: null,
        tone: 'error',
        okLabel: @js($defaultOk),
        defaults: {
            title: @js($defaultTitle),
            message: @js($message),
            okLabel: @js($defaultOk),
        },
        eyebrow: null,
        show(detail = {}) {
            this.title = detail.title ?? this.defaults.title;
            this.message = detail.message ?? this.defaults.message ?? '';
            this.lines = Array.isArray(detail.lines) ? detail.lines : [];
            this.statuses = Array.isArray(detail.statuses) ? detail.statuses : [];
            this.actionRequired = detail.action_required || detail.actionRequired || null;
            this.secondaryLabel = detail.secondaryLabel || null;
            this.secondaryHref = detail.secondaryHref || null;
            this.tone = detail.tone || 'error';
            this.okLabel = detail.okLabel || this.defaults.okLabel;
            this.eyebrow = detail.eyebrow || null;
            this.open = true;
        },
        close() {
            this.open = false;
        },
        statusIcon(state) {
            if (state === 'success') return '✓';
            if (state === 'warning') return '!';
            if (state === 'error') return '×';
            return '•';
        },
        statusClass(state) {
            if (state === 'success') return 'bg-emerald-100 text-emerald-800 ring-emerald-200';
            if (state === 'warning') return 'bg-amber-100 text-amber-900 ring-amber-200';
            if (state === 'error') return 'bg-rose-100 text-rose-800 ring-rose-200';
            return 'bg-slate-100 text-slate-700 ring-slate-200';
        },
        toneMeta() {
            const map = {
                success: {
                    iconBg: 'bg-emerald-500/20 text-emerald-100 ring-emerald-400/30',
                    accent: 'from-brand via-brand to-brand-light',
                    eyebrow: @js(__('borrower.feedback.tones.success')),
                },
                warning: {
                    iconBg: 'bg-amber-400/25 text-amber-50 ring-amber-300/40',
                    accent: 'from-brand via-brand to-brand-light',
                    eyebrow: @js(__('borrower.feedback.tones.warning')),
                },
                info: {
                    iconBg: 'bg-sky-400/20 text-sky-50 ring-sky-300/30',
                    accent: 'from-brand via-brand to-brand-light',
                    eyebrow: @js(__('borrower.feedback.tones.info')),
                },
                confirm: {
                    iconBg: 'bg-brand-gold/25 text-brand-gold ring-brand-gold/40',
                    accent: 'from-brand via-brand to-brand-light',
                    eyebrow: @js(__('borrower.feedback.tones.confirm')),
                },
                error: {
                    iconBg: 'bg-red-400/20 text-red-50 ring-red-300/30',
                    accent: 'from-brand via-brand to-brand-light',
                    eyebrow: @js(__('borrower.feedback.tones.error')),
                },
            };
            const meta = map[this.tone] || map.error;
            return this.eyebrow ? { ...meta, eyebrow: this.eyebrow } : meta;
        },
    }"
    x-on:open-feedback-{{ $name }}.window="show($event.detail || {})"
    x-on:keydown.escape.window="if (open) close()"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[10050] flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm" @click="close()"></div>
    <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         @click.stop>
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 size-11 rounded-2xl grid place-items-center ring-1 shrink-0"
                      :class="toneMeta().iconBg">
                    <template x-if="tone === 'success'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="tone === 'warning'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    </template>
                    <template x-if="tone === 'info'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 3a9 9 0 100 18 9 9 0 000-18z"/></svg>
                    </template>
                    <template x-if="tone === 'confirm'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </template>
                    <template x-if="tone !== 'success' && tone !== 'warning' && tone !== 'info' && tone !== 'confirm'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z"/></svg>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold" x-text="toneMeta().eyebrow"></p>
                    <h3 class="text-lg font-bold mt-1 leading-snug" x-text="title"></h3>
                </div>
            </div>
        </div>
        <div class="px-6 py-5">
            <p x-show="message" x-cloak class="text-sm font-semibold text-gray-900 whitespace-pre-line leading-relaxed" x-text="message"></p>

            <ul x-show="statuses.length" x-cloak class="mt-4 space-y-2">
                <template x-for="(row, i) in statuses" :key="row.key || i">
                    <li class="flex items-center justify-between gap-3 rounded-xl bg-white ring-1 ring-gray-200 px-3 py-2.5">
                        <span class="text-sm font-medium text-gray-700" x-text="row.label"></span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1"
                              :class="statusClass(row.state)">
                            <span aria-hidden="true" x-text="statusIcon(row.state)"></span>
                            <span x-text="row.value"></span>
                        </span>
                    </li>
                </template>
            </ul>

            <div x-show="actionRequired" x-cloak class="mt-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3.5 py-3">
                <p class="text-[10px] uppercase tracking-widest font-bold text-amber-800">Action required</p>
                <p class="mt-1 text-sm text-amber-950" x-text="actionRequired"></p>
            </div>

            <ul x-show="!statuses.length && lines.length" x-cloak class="mt-3 space-y-2 text-sm text-gray-700">
                <template x-for="(line, i) in lines" :key="i">
                    <li class="flex gap-2 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-3 py-2">
                        <span class="mt-0.5 size-5 shrink-0 rounded-full bg-slate-200 text-slate-700 text-[11px] font-bold grid place-items-center" aria-hidden="true">•</span>
                        <span class="min-w-0" x-text="line"></span>
                    </li>
                </template>
            </ul>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <a x-show="secondaryHref && secondaryLabel" x-cloak
                   :href="secondaryHref"
                   class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold ring-1 ring-gray-200 bg-white text-gray-800 hover:bg-gray-50"
                   x-text="secondaryLabel"></a>
                <button type="button" @click="close()"
                        class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm"
                        x-text="okLabel"></button>
            </div>
        </div>
    </div>
</div>
