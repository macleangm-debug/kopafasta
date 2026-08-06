@props([
    'name' => 'confirm',
    'title' => null,
    'message' => null,
    'confirmLabel' => null,
    'cancelLabel' => null,
    'confirmClass' => 'bg-brand-gold hover:bg-yellow-400 text-brand',
    'tone' => 'confirm',
])

@php
    $title = $title ?? __('borrower.feedback.confirm_title');
    $confirmLabel = $confirmLabel ?? __('borrower.feedback.confirm');
    $cancelLabel = $cancelLabel ?? __('borrower.apply.cancel');
@endphp

<div
    x-data="{
        open: false,
        form: null,
        title: @js($title),
        message: @js($message),
        confirmLabel: @js($confirmLabel),
        confirmClass: @js($confirmClass),
        tone: @js($tone),
        defaults: {
            title: @js($title),
            message: @js($message),
            confirmLabel: @js($confirmLabel),
            confirmClass: @js($confirmClass),
            tone: @js($tone),
        },
        onCancel: null,
        onConfirm: null,
        cancel() {
            if (this.form instanceof HTMLFormElement) {
                delete this.form.dataset.loadingBound;
                this.form.querySelectorAll('button[type=submit], input[type=submit]').forEach((btn) => {
                    if (btn.dataset.originalHtml != null) {
                        btn.innerHTML = btn.dataset.originalHtml;
                        delete btn.dataset.originalHtml;
                    } else if (btn.dataset.originalValue != null) {
                        btn.value = btn.dataset.originalValue;
                        delete btn.dataset.originalValue;
                    }
                    btn.disabled = false;
                    btn.classList.remove('opacity-70', 'cursor-wait', 'inline-flex', 'items-center', 'gap-2');
                });
            }
            this.open = false;
            this.form = null;
            this.onConfirm = null;
            if (typeof this.onCancel === 'function') this.onCancel();
            this.onCancel = null;
        },
        toneMeta() {
            const map = {
                success: {
                    iconBg: 'bg-emerald-500/20 text-emerald-100 ring-emerald-400/30',
                    eyebrow: @js(__('borrower.feedback.tones.success')),
                },
                warning: {
                    iconBg: 'bg-amber-400/25 text-amber-50 ring-amber-300/40',
                    eyebrow: @js(__('borrower.feedback.tones.warning')),
                },
                error: {
                    iconBg: 'bg-red-400/20 text-red-50 ring-red-300/30',
                    eyebrow: @js(__('borrower.feedback.tones.error')),
                },
                info: {
                    iconBg: 'bg-sky-400/20 text-sky-50 ring-sky-300/30',
                    eyebrow: @js(__('borrower.feedback.tones.info')),
                },
                confirm: {
                    iconBg: 'bg-brand-gold/25 text-brand-gold ring-brand-gold/40',
                    eyebrow: @js(__('borrower.feedback.tones.confirm')),
                },
            };
            return map[this.tone] || map.confirm;
        },
    }"
    x-on:open-confirm-{{ $name }}.window="
        open = true;
        form = $event.detail?.form ?? null;
        title = $event.detail?.title ?? defaults.title;
        message = $event.detail?.message ?? defaults.message;
        confirmLabel = $event.detail?.confirmLabel ?? defaults.confirmLabel;
        confirmClass = $event.detail?.confirmClass ?? defaults.confirmClass;
        tone = $event.detail?.tone ?? defaults.tone;
        onCancel = $event.detail?.onCancel ?? null;
        onConfirm = $event.detail?.onConfirm ?? null;
    "
    x-on:keydown.escape.window="if (open) cancel()"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[10050] flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm" @click="cancel()"></div>
    <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 size-11 rounded-2xl grid place-items-center ring-1 shrink-0"
                      :class="toneMeta().iconBg">
                    <template x-if="tone === 'success'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="tone === 'warning' || tone === 'error'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    </template>
                    <template x-if="tone === 'info'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 3a9 9 0 100 18 9 9 0 000-18z"/></svg>
                    </template>
                    <template x-if="tone !== 'success' && tone !== 'warning' && tone !== 'error' && tone !== 'info'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold" x-text="toneMeta().eyebrow"></p>
                    <h3 class="text-lg font-bold mt-1 leading-snug" x-text="title"></h3>
                </div>
            </div>
        </div>
        <div class="px-6 py-5">
            <p x-show="message" x-cloak class="text-sm text-gray-600 leading-relaxed" x-text="message"></p>
            <div class="mt-6 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                <button type="button" @click="cancel()"
                        class="inline-flex justify-center px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-white ring-1 ring-gray-200 hover:bg-gray-50">
                    {{ $cancelLabel }}
                </button>
                <button type="button"
                        @click="
                            const confirmCb = onConfirm;
                            if (form) {
                                form.dispatchEvent(new CustomEvent('sync-before-submit', { bubbles: true }));
                                form.querySelectorAll('[data-phone-input]').forEach(function (root) {
                                    if (typeof window.syncSitePhoneInput === 'function') {
                                        window.syncSitePhoneInput(root);
                                    }
                                });
                                form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (btn) { btn.disabled = true; });
                                form.submit();
                            } else if (typeof confirmCb === 'function') {
                                confirmCb();
                            }
                            open = false;
                            form = null;
                            onConfirm = null;
                            onCancel = null;
                        "
                        :disabled="!form && typeof onConfirm !== 'function'"
                        class="inline-flex justify-center px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm disabled:opacity-50"
                        :class="confirmClass"
                        x-text="confirmLabel"></button>
            </div>
        </div>
    </div>
</div>
