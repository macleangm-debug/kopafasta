@props([
    'message' => null,
])

@php
    $message = $message ?? __('borrower.profile.uploading');
@endphp

<template x-teleport="body">
    <div
        x-show="uploading"
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
                    this.percent = 8;
                    clearInterval(this.timer);
                    this.timer = setInterval(() => {
                        if (this.percent < 92) {
                            this.percent = Math.min(92, this.percent + (this.percent < 45 ? 7 : 2));
                        }
                    }, 260);
                },
                stop() {
                    clearInterval(this.timer);
                    this.percent = 0;
                }
             }"
             x-effect="uploading ? start() : stop()">
            <div class="mx-auto size-14 rounded-2xl bg-brand-muted grid place-items-center ring-1 ring-brand/20">
                <svg class="size-7 animate-spin text-brand" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
            <p class="mt-4 text-base font-bold text-gray-900">{{ $message }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ __('borrower.profile.uploading_hint') }}</p>
            <div class="mt-5 h-2.5 rounded-full bg-gray-100 overflow-hidden ring-1 ring-gray-200/80">
                <div class="h-full rounded-full bg-gradient-to-r from-brand to-brand-gold transition-all duration-300 ease-out"
                     :style="`width: ${percent}%`"></div>
            </div>
            <p class="mt-2 text-xs font-semibold tabular-nums text-brand" x-text="`${Math.round(percent)}%`"></p>
        </div>
    </div>
</template>
