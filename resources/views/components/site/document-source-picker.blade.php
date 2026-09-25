@props([
    'hostId' => null,
    'uploadLabel' => null,
    'cameraLabel' => null,
    'title' => null,
    'cameraOnly' => false,
])

@php
    $cameraLabel = $cameraLabel ?: __('borrower.document_upload.take_photo');
    $uploadLabel = $uploadLabel ?: __('borrower.document_upload.upload');
    $title = $title ?: __('borrower.document_upload.add');
    $hostId = $hostId ? (string) $hostId : null;
    $cameraOnly = (bool) $cameraOnly;
@endphp

{{-- Canonical + → Upload / Camera.
     Desktop uses a popover only. Mobile uses a bottom sheet only.
     Separate open flags so a teleported sheet cannot appear beside the desktop menu. --}}
<div class="relative inline-flex shrink-0"
     x-data="{
        menuOpen: false,
        sheetOpen: false,
        cameraOnly: @js($cameraOnly),
        desktop: false,
        menuStyle: {},
        syncDesktop() {
            this.desktop = window.matchMedia('(min-width: 1024px)').matches;
            if (this.desktop) {
                this.sheetOpen = false;
            } else {
                this.menuOpen = false;
            }
        },
        placeMenu() {
            const btn = this.$refs.trigger;
            if (! btn) return;
            const r = btn.getBoundingClientRect();
            const width = 224;
            const left = Math.min(window.innerWidth - width - 12, Math.max(12, r.right - width));
            this.menuStyle = {
                position: 'fixed',
                top: (r.bottom + 8) + 'px',
                left: left + 'px',
                width: width + 'px',
                zIndex: 10060,
            };
        },
        pick(source) {
            this.menuOpen = false;
            this.sheetOpen = false;
            window.dispatchEvent(new CustomEvent('document-source', {
                detail: { source: source, hostId: @js($hostId) },
            }));
        },
        openPicker() {
            this.syncDesktop();
            if (this.cameraOnly) {
                this.pick('camera');
                return;
            }
            if (this.desktop) {
                this.sheetOpen = false;
                this.menuOpen = ! this.menuOpen;
                if (this.menuOpen) this.placeMenu();
                return;
            }
            this.menuOpen = false;
            this.sheetOpen = ! this.sheetOpen;
        },
     }"
     x-init="syncDesktop()"
     @resize.window="syncDesktop(); if (menuOpen) placeMenu()"
     @document-source-open.window="
        if ($event.detail?.hostId && $event.detail.hostId !== @js($hostId)) return;
        openPicker();
     ">
    <button type="button"
            x-ref="trigger"
            @click="openPicker()"
            class="kf-request-add"
            :class="(menuOpen || sheetOpen) && 'is-open'"
            aria-label="{{ $cameraOnly ? $cameraLabel : $title }}">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
    </button>

    @unless ($cameraOnly)
    <template x-teleport="body">
        <div x-cloak
             x-show="menuOpen"
             x-transition
             @click.outside="menuOpen = false"
             @resize.window="if (menuOpen) placeMenu()"
             @scroll.window.passive="if (menuOpen) placeMenu()"
             :style="menuStyle"
             class="rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 overflow-hidden">
            <button type="button" @click="pick('upload')"
                    class="w-full flex items-center gap-3 px-4 py-3.5 text-left text-sm font-semibold text-gray-800 hover:bg-brand/5 transition">
                <span class="size-9 rounded-xl bg-brand/10 text-brand grid place-items-center shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 20h16"/></svg>
                </span>
                {{ $uploadLabel }}
            </button>
            <button type="button" @click="pick('camera')"
                    class="w-full flex items-center gap-3 px-4 py-3.5 text-left text-sm font-semibold text-gray-800 hover:bg-brand/5 transition border-t border-gray-100">
                <span class="size-9 rounded-xl bg-brand-gold/20 text-brand grid place-items-center shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                </span>
                {{ $cameraLabel }}
            </button>
        </div>
    </template>

    <x-site.bottom-sheet :title="$title" open="sheetOpen">
        <div class="space-y-2 pb-2">
            <button type="button" @click="pick('upload')"
                    class="w-full flex items-center gap-3 rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 text-left text-sm font-semibold text-gray-800 hover:bg-brand/5">
                <span class="size-10 rounded-xl bg-brand/10 text-brand grid place-items-center shrink-0" aria-hidden="true">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 20h16"/></svg>
                </span>
                {{ $uploadLabel }}
            </button>
            <button type="button" @click="pick('camera')"
                    class="w-full flex items-center gap-3 rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 text-left text-sm font-semibold text-gray-800 hover:bg-brand/5">
                <span class="size-10 rounded-xl bg-brand-gold/20 text-brand grid place-items-center shrink-0" aria-hidden="true">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                </span>
                {{ $cameraLabel }}
            </button>
        </div>
    </x-site.bottom-sheet>
    @endunless
</div>
