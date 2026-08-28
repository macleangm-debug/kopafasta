@props([
    'message' => null,
])

@php
    $message = $message ?? __('borrower.profile.uploading');
@endphp

<div x-data>
<template x-teleport="body">
    <div
        x-show="$store.kfSaving?.uploading || (typeof uploading !== 'undefined' && uploading)"
        x-cloak
        class="fixed inset-0 z-[10060] flex items-center justify-center bg-brand/70 backdrop-blur-sm p-4"
        role="status"
        aria-live="polite"
    >
        <div class="w-full max-w-sm rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15 px-6 py-8 text-center"
             x-data="{
                percent: 8,
                timer: null,
                start() {
                    clearInterval(this.timer);
                    if (this.isDeterminate()) {
                        this.syncDeterminate();
                        return;
                    }
                    this.percent = 8;
                    this.timer = setInterval(() => {
                        if (this.percent < 92) {
                            this.percent = Math.min(92, this.percent + (this.percent < 45 ? 7 : 2));
                        }
                    }, 260);
                },
                stop() {
                    clearInterval(this.timer);
                    this.percent = 0;
                },
                isBusy() {
                    return !!(this.$store.kfSaving?.uploading || (typeof uploading !== 'undefined' && uploading));
                },
                isDeterminate() {
                    return Number(this.$store.kfSaving?.total) > 0;
                },
                syncDeterminate() {
                    const total = Number(this.$store.kfSaving?.total) || 0;
                    const current = Number(this.$store.kfSaving?.current) || 0;
                    this.percent = total ? Math.round(100 * current / total) : 0;
                },
                heading() {
                    return this.$store.kfSaving?.message || @js($message);
                },
                countLine() {
                    if (! this.isDeterminate()) {
                        return '';
                    }
                    return String(this.$store.kfSaving.current || 0) + ' / ' + String(this.$store.kfSaving.total);
                }
             }"
             x-effect="isBusy() ? start() : stop(); if (isBusy() && isDeterminate()) syncDeterminate()">
            <div class="mx-auto size-14 rounded-2xl bg-brand-muted grid place-items-center ring-1 ring-brand/20">
                <svg class="size-7 animate-spin text-brand" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
            <p class="mt-4 text-base font-bold text-gray-900" x-text="heading()"></p>
            <p class="mt-1 text-sm text-gray-500" x-show="!countLine()">{{ __('borrower.profile.uploading_hint') }}</p>
            <p class="mt-1 text-sm font-semibold text-brand" x-show="countLine()" x-cloak x-text="countLine()"></p>
            <div class="mt-5 h-2.5 rounded-full bg-gray-100 overflow-hidden ring-1 ring-gray-200/80">
                <div class="h-full rounded-full bg-gradient-to-r from-brand to-brand-gold transition-all duration-300 ease-out"
                     :style="`width: ${percent}%`"></div>
            </div>
            <p class="mt-2 text-xs font-semibold tabular-nums text-brand" x-text="`${Math.round(percent)}%`"></p>
        </div>
    </div>
</template>
</div>
