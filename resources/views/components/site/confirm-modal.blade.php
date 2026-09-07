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

{{-- Desktop: centered modal. Mobile: premium bottom sheet (same pattern as action-panel). --}}
<template x-teleport="body">
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
                    if (typeof window.kfClearBusy === 'function') {
                        window.kfClearBusy(btn);
                    } else {
                        if (btn.dataset.originalHtml != null) {
                            btn.innerHTML = btn.dataset.originalHtml;
                            delete btn.dataset.originalHtml;
                        } else if (btn.dataset.originalValue != null) {
                            btn.value = btn.dataset.originalValue;
                            delete btn.dataset.originalValue;
                        }
                        btn.disabled = false;
                        btn.classList.remove('opacity-70', 'cursor-wait', 'inline-flex', 'items-center', 'gap-2', 'pointer-events-none');
                    }
                });
            }
            this.open = false;
            this.form = null;
            this.onConfirm = null;
            if (typeof this.onCancel === 'function') this.onCancel();
            this.onCancel = null;
        },
        runConfirm(confirmBtn) {
            const confirmCb = this.onConfirm;
            if (this.form) {
                this.form.dispatchEvent(new CustomEvent('sync-before-submit', { bubbles: true }));
                this.form.querySelectorAll('[data-phone-input]').forEach(function (root) {
                    if (typeof window.syncSitePhoneInput === 'function') {
                        window.syncSitePhoneInput(root);
                    }
                });
                const submitter = this.form.querySelector('button[type=submit], input[type=submit]');
                if (typeof window.kfMarkBusy === 'function') {
                    if (submitter) window.kfMarkBusy(submitter);
                    window.kfMarkBusy(confirmBtn);
                    this.form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (btn) {
                        if (btn !== submitter) btn.disabled = true;
                    });
                } else {
                    this.form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (btn) { btn.disabled = true; });
                }
                this.form.dataset.loadingBound = '1';
                if (typeof window.kfFormNeedsSaving === 'function' && window.kfFormNeedsSaving(this.form) && typeof window.kfShowSaving === 'function') {
                    window.kfShowSaving(this.form.getAttribute('data-saving-message') || '');
                }
                this.form.submit();
            } else if (typeof confirmCb === 'function') {
                if (typeof window.kfMarkBusy === 'function') window.kfMarkBusy(confirmBtn);
                confirmCb();
            }
            this.open = false;
            this.form = null;
            this.onConfirm = null;
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
    x-effect="
        if (typeof document === 'undefined') return;
        document.documentElement.classList.toggle('overflow-hidden', open);
        document.body.classList.toggle('overflow-hidden', open);
    "
    class="fixed inset-0 z-[10050]"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm lg:bg-brand/70" @click="cancel()" x-transition.opacity></div>

    <div class="absolute inset-x-0 bottom-0 lg:inset-auto lg:left-1/2 lg:top-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2
                w-full lg:max-w-md max-h-[min(90dvh,640px)] flex flex-col overflow-hidden
                rounded-t-2xl lg:rounded-3xl bg-white shadow-[0_-8px_40px_rgba(0,0,0,0.18)] lg:shadow-2xl lg:ring-1 lg:ring-brand/15"
         style="padding-bottom: env(safe-area-inset-bottom, 0px)"
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full lg:translate-y-0 lg:opacity-0 lg:scale-95"
         x-transition:enter-end="translate-y-0 lg:opacity-100 lg:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 lg:opacity-100"
         x-transition:leave-end="translate-y-full lg:opacity-0 lg:scale-95"
         @click.stop>
        <div class="flex justify-center pt-3 pb-1 shrink-0 lg:hidden">
            <div class="w-10 h-1 rounded-full bg-gray-300"></div>
        </div>
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white shrink-0">
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
        <div class="px-6 py-5 overflow-y-auto overscroll-contain flex-1">
            <p x-show="message" x-cloak class="text-sm text-gray-600 leading-relaxed" x-text="message"></p>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row-reverse">
                <button type="button"
                        @click="runConfirm($el)"
                        :disabled="!form && typeof onConfirm !== 'function'"
                        class="inline-flex w-full sm:w-auto justify-center px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm disabled:opacity-50"
                        :class="confirmClass"
                        x-text="confirmLabel"></button>
                <button type="button" @click="cancel()"
                        class="inline-flex w-full sm:w-auto justify-center px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-white ring-1 ring-gray-200 hover:bg-gray-50">
                    {{ $cancelLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
</template>
