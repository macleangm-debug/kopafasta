@props([
    'customer',
    'photos',
    'angles',
    'status' => null,
    'compact' => false,
])

@php
    $statusKey = $customer->face_verification_status ?? 'incomplete';
    $photoEntries = collect($angles)->map(function ($meta, $key) use ($photos) {
        $photo = $photos[$key] ?? null;
        $path = is_object($photo) ? ($photo->file_path ?? null) : null;
        $url = filled($path) ? asset('storage/'.$path) : null;

        return [
            'key' => $key,
            'label' => $meta['label'] ?? $key,
            'url' => $url,
        ];
    })->values();
    $captured = $photoEntries->filter(fn ($p) => filled($p['url']))->values();
@endphp

{{-- Mirror NIDA card + collateral lightbox: no overflow trap, teleport escape glass-card backdrop-filter --}}
<div
    x-data="{
        photos: @js($captured->values()->all()),
        index: 0,
        lightbox: false,
        swipeStartX: null,
        openPreview(url, label) {
            if (!url) return;
            const i = this.photos.findIndex(p => p.url === url);
            this.index = i >= 0 ? i : 0;
            if (i < 0 && url) {
                this.photos = [{ url, label: label || '' }, ...this.photos];
                this.index = 0;
            }
            this.lightbox = true;
        },
        closePreview() { this.lightbox = false; },
        next() { if (this.photos.length) this.index = (this.index + 1) % this.photos.length; },
        prev() { if (this.photos.length) this.index = (this.index - 1 + this.photos.length) % this.photos.length; },
        current() { return this.photos[this.index] || null; },
    }"
    @photo-carousel-open="openPreview($event.detail.url, $event.detail.label)"
    @photo-carousel-retake="window.dispatchEvent(new CustomEvent('profile-card-open-edit', { detail: 'profile-face' }))"
    @class([
        'rounded-3xl ring-1 ring-brand/15 bg-white' => ! $compact,
    ])
>
    @unless ($compact)
        <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.nida.face_title') }}</p>
                <h2 class="font-semibold text-gray-900 mt-0.5">{{ __('borrower.nida.face_captured_photos') }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('borrower.nida.face_compare_hint') }}</p>
            </div>
        </div>
    @else
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.nida.face_captured_photos') }}</p>
        </div>
    @endunless

    @if ($customer->face_rejection_notes && in_array($statusKey, ['rejected', 'revision_required'], true))
        <div @class(['rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800', 'mx-5 mt-4' => ! $compact, 'mb-3' => $compact])>
            <p class="font-medium">{{ $statusKey === 'revision_required' ? __('borrower.apply.checklist.face_revision') : __('borrower.face_verification_page.rejected_title') }}</p>
            <p class="mt-1">{{ $customer->face_rejection_notes }}</p>
        </div>
    @elseif ($statusKey === 'revision_required')
        <div @class(['rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900', 'mx-5 mt-4' => ! $compact, 'mb-3' => $compact])>
            <p class="font-medium">{{ __('borrower.apply.checklist.face_revision') }}</p>
        </div>
    @endif

    {{-- Horizontal carousel so the page stays short on phone and desktop --}}
    <div @class(['p-5' => ! $compact])>
        <p class="text-[11px] text-gray-500 mb-3">{{ __('borrower.profile.tap_to_enlarge') }}</p>
        <x-site.photo-carousel :retake="false" :photos="$captured->map(fn ($entry, $i) => [
            'url' => $entry['url'],
            'label' => $entry['label'],
            'index' => $i,
        ])->all()" />
        @if ($photoEntries->contains(fn ($entry) => blank($entry['url'])))
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($photoEntries as $entry)
                    @if (! $entry['url'])
                        <span class="rounded-full bg-amber-50 ring-1 ring-amber-200 px-3 py-1 text-xs font-semibold text-amber-800">{{ $entry['label'] }} · {{ __('borrower.nida.face_not_captured') }}</span>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <div @class(['px-5 pb-5 flex flex-wrap gap-2', 'mt-3' => $compact, 'pt-1' => ! $compact])>
        @if ($captured->isNotEmpty())
            <button type="button"
                    @click="openPreview(@js($captured->first()['url']), @js($captured->first()['label']))"
                    class="inline-flex items-center justify-center font-semibold px-4 py-2 rounded-full text-sm bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800">
                {{ __('borrower.nida.face_view') }}
            </button>
        @endif
        <button type="button"
                @click="window.dispatchEvent(new CustomEvent('profile-card-open-edit', { detail: 'profile-face' }))"
                class="inline-flex items-center justify-center font-semibold px-4 py-2 rounded-full text-sm bg-brand-gold hover:bg-yellow-400 text-brand">
            {{ __('borrower.nida.face_replace') }}
        </button>
    </div>

    <template x-teleport="body">
        <div x-show="lightbox" x-cloak x-transition
             class="fixed inset-0 z-[90] bg-black/80 flex items-center justify-center p-4"
             @keydown.escape.window="closePreview()"
             @keydown.arrow-right.window="if (lightbox) next()"
             @keydown.arrow-left.window="if (lightbox) prev()"
             @click.self="closePreview()">
            <button type="button" class="absolute top-4 right-4 text-white/90 text-2xl font-semibold" @click="closePreview()"
                    aria-label="{{ __('borrower.profile.cancel') }}">×</button>
            <button type="button" x-show="photos.length > 1" @click.stop="prev()"
                    class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 rounded-full bg-white/15 hover:bg-white/25 text-white w-10 h-10 text-xl font-bold">‹</button>
            <button type="button" x-show="photos.length > 1" @click.stop="next()"
                    class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 rounded-full bg-white/15 hover:bg-white/25 text-white w-10 h-10 text-xl font-bold">›</button>
            <div class="relative max-h-[90vh] max-w-[95vw]">
                <p class="absolute -top-8 left-0 right-0 text-center text-sm font-semibold text-white/90 truncate px-8"
                   x-text="(current()?.label || '') + (photos.length > 1 ? (' · ' + (index + 1) + '/' + photos.length) : '')"></p>
                <img :src="current()?.url" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl"
                     @touchstart.passive="swipeStartX = $event.changedTouches[0].clientX"
                     @touchend.passive="
                        const dx = $event.changedTouches[0].clientX - (swipeStartX || 0);
                        if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); }
                     ">
            </div>
            @if (true)
                <div class="absolute bottom-6 left-1/2 -translate-x-1/2">
                    <button type="button"
                            @click="closePreview(); window.dispatchEvent(new CustomEvent('profile-card-open-edit', { detail: 'profile-face' }))"
                            class="inline-flex font-semibold px-5 py-2.5 rounded-full text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-lg">
                        {{ __('borrower.nida.face_replace') }}
                    </button>
                </div>
            @endif
        </div>
    </template>
</div>
