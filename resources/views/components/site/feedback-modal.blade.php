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
        tone: 'error',
        okLabel: @js($defaultOk),
        defaults: {
            title: @js($defaultTitle),
            message: @js($message),
            okLabel: @js($defaultOk),
        },
        show(detail = {}) {
            this.title = detail.title ?? this.defaults.title;
            this.message = detail.message ?? this.defaults.message ?? '';
            this.lines = Array.isArray(detail.lines) ? detail.lines : [];
            this.tone = detail.tone || 'error';
            this.okLabel = detail.okLabel || this.defaults.okLabel;
            this.open = true;
        },
        close() {
            this.open = false;
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
    <div class="absolute inset-0 bg-black/40" @click="close()"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 p-6"
         @click.stop>
        <div class="flex items-start gap-3">
            <span class="mt-0.5 size-9 rounded-full grid place-items-center text-sm font-bold shrink-0"
                  :class="{
                      'bg-red-100 text-red-700': tone === 'error',
                      'bg-amber-100 text-amber-800': tone === 'warning',
                      'bg-emerald-100 text-emerald-700': tone === 'success',
                      'bg-sky-100 text-sky-800': tone === 'info',
                  }"
                  x-text="tone === 'success' ? '✓' : '!'"></span>
            <div class="min-w-0 flex-1">
                <h3 class="text-lg font-semibold text-gray-900" x-text="title"></h3>
                <p x-show="message" x-cloak class="text-sm text-gray-600 mt-2 whitespace-pre-line" x-text="message"></p>
                <ul x-show="lines.length" x-cloak class="mt-2 space-y-1 text-sm text-gray-700 list-disc ml-5">
                    <template x-for="(line, i) in lines" :key="i">
                        <li x-text="line"></li>
                    </template>
                </ul>
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <button type="button" @click="close()"
                    class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-brand hover:bg-brand-light text-white"
                    x-text="okLabel"></button>
        </div>
    </div>
</div>
