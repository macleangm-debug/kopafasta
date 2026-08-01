@props([
    'customer',
    'photos',
    'angles',
    'status' => null,
    'compact' => false,
])

@php
    $statusKey = $customer->face_verification_status ?? 'incomplete';
    $statusBadge = match ($statusKey) {
        'verified' => [__('borrower.nida.face_status.verified'), 'bg-emerald-100 text-emerald-800'],
        'pending'  => [__('borrower.nida.face_status.submitted'), 'bg-sky-100 text-sky-800'],
        'rejected' => [__('borrower.nida.face_status.failed'), 'bg-red-100 text-red-800'],
        'revision_required' => [__('borrower.nida.face_status.revision_required'), 'bg-amber-100 text-amber-800'],
        default    => [__('borrower.nida.face_status.incomplete'), 'bg-amber-100 text-amber-800'],
    };
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
    // Uploads are locked while approved or under review; UW sets revision_required to unlock
    $canReplaceFace = ! in_array($statusKey, ['verified', 'pending'], true);
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
            <span class="text-xs font-semibold rounded-full px-3 py-1 {{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span>
        </div>
    @else
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.nida.face_captured_photos') }}</p>
            <span class="text-xs font-semibold rounded-full px-3 py-1 {{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span>
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

    {{-- NIDA-style card thumbnails per angle --}}
    <div @class(['grid sm:grid-cols-2 gap-3', 'p-5' => ! $compact])>
        @foreach ($photoEntries as $entry)
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                <p class="text-xs text-gray-500">{{ $entry['label'] }}</p>
                <div class="mt-2 flex items-start gap-3">
                    @if ($entry['url'])
                        <button type="button"
                                @click="openPreview(@js($entry['url']), @js($entry['label']))"
                                class="h-28 w-24 shrink-0 rounded-lg ring-1 ring-gray-200 overflow-hidden bg-white cursor-zoom-in block"
                                title="{{ __('borrower.profile.view_document') }}">
                            <img src="{{ $entry['url'] }}"
                                 alt="{{ $entry['label'] }}"
                                 class="h-full w-full object-cover object-top"
                                 loading="lazy">
                        </button>
                        <div class="min-w-0 flex-1 flex flex-col gap-2 pt-0.5">
                            <p class="text-[11px] text-gray-500">{{ __('borrower.profile.tap_to_enlarge') }}</p>
                            <button type="button"
                                    @click="openPreview(@js($entry['url']), @js($entry['label']))"
                                    class="inline-flex items-center justify-center self-start rounded-full bg-white ring-1 ring-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">
                                {{ __('borrower.nida.face_view_angle') }}
                            </button>
                        </div>
                    @else
                        <div class="h-28 w-24 shrink-0 rounded-lg ring-1 ring-dashed ring-gray-300 bg-white flex flex-col items-center justify-center gap-1 text-center px-1">
                            <span class="text-lg opacity-40" aria-hidden="true">◎</span>
                            <span class="text-[10px] text-gray-400 leading-tight">{{ __('borrower.nida.face_not_captured') }}</span>
                        </div>
                        <p class="text-sm font-semibold text-amber-700 pt-2">{{ __('borrower.profile.missing') }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($statusKey === 'pending' && ! $compact)
        <div class="px-5 pb-2">
            <p class="text-sm text-gray-600">{{ __('borrower.nida.face_submitted_body') }}</p>
        </div>
    @endif

    <div @class(['px-5 pb-5 flex flex-wrap gap-2', 'mt-3' => $compact, 'pt-1' => ! $compact])>
        @if ($captured->isNotEmpty())
            <button type="button"
                    @click="openPreview(@js($captured->first()['url']), @js($captured->first()['label']))"
                    class="inline-flex items-center justify-center font-semibold px-4 py-2 rounded-full text-sm bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800">
                {{ __('borrower.nida.face_view') }}
            </button>
        @endif
        @if ($canReplaceFace || $statusKey === 'verified')
            @if ($statusKey === 'verified')
                <form method="POST" action="{{ route('site.borrower.face-verification.retake') }}"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.nida.face_replace')), message: @js(__('borrower.nida.face_replace_hint')), confirmLabel: @js(__('borrower.nida.face_retake')), confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand' })">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center justify-center font-semibold px-4 py-2 rounded-full text-sm bg-brand-gold hover:bg-yellow-400 text-brand">
                        {{ __('borrower.nida.face_replace') }}
                    </button>
                </form>
            @else
                <a href="{{ route('site.borrower.face-verification') }}"
                   class="inline-flex items-center justify-center font-semibold px-4 py-2 rounded-full text-sm bg-brand-gold hover:bg-yellow-400 text-brand">
                    {{ __('borrower.nida.face_replace') }}
                </a>
            @endif
        @elseif ($statusKey === 'pending')
            <p class="text-xs text-gray-500 self-center">{{ __('borrower.nida.face_retake_pending_blocked') }}</p>
        @endif
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
            @if ($canReplaceFace || $statusKey === 'verified')
                <div class="absolute bottom-6 left-1/2 -translate-x-1/2">
                    @if ($statusKey === 'verified')
                        <form method="POST" action="{{ route('site.borrower.face-verification.retake') }}"
                              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.nida.face_replace')), message: @js(__('borrower.nida.face_replace_hint')), confirmLabel: @js(__('borrower.nida.face_retake')), confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand' })">
                            @csrf
                            <button type="submit" class="inline-flex font-semibold px-5 py-2.5 rounded-full text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-lg">
                                {{ __('borrower.nida.face_replace') }}
                            </button>
                        </form>
                    @else
                        <a href="{{ route('site.borrower.face-verification') }}"
                           class="inline-flex font-semibold px-5 py-2.5 rounded-full text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-lg">
                            {{ __('borrower.nida.face_replace') }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </template>
</div>
