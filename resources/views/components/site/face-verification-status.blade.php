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
        default    => [__('borrower.nida.face_status.incomplete'), 'bg-amber-100 text-amber-800'],
    };
    $photoEntries = collect($angles)->map(function ($meta, $key) use ($photos) {
        $photo = $photos[$key] ?? null;
        $url = $photo?->file_path ? asset('storage/'.$photo->file_path) : null;

        return [
            'key' => $key,
            'label' => $meta['label'] ?? $key,
            'url' => $url,
        ];
    })->values();
    $captured = $photoEntries->filter(fn ($p) => filled($p['url']))->values();
    $canReplaceFace = in_array($statusKey, ['rejected', 'incomplete', 'pending'], true);
@endphp

<div
    x-data="{
        expandedUrl: null,
        expandedLabel: null,
        openPreview(url, label) {
            if (!url) return;
            this.expandedUrl = url;
            this.expandedLabel = label || '';
        },
        closePreview() {
            this.expandedUrl = null;
            this.expandedLabel = null;
        },
    }"
    @class([
        'overflow-hidden',
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

    @if ($customer->face_rejection_notes && $statusKey === 'rejected')
        <div @class(['rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800', 'mx-5 mt-4' => ! $compact, 'mb-3' => $compact])>
            <p class="font-medium">{{ __('borrower.face_verification_page.rejected_title') }}</p>
            <p class="mt-1">{{ $customer->face_rejection_notes }}</p>
        </div>
    @endif

    {{-- NIDA-style card previews per angle --}}
    <div @class(['grid sm:grid-cols-2 gap-3', 'p-5' => ! $compact])>
        @foreach ($photoEntries as $entry)
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                <p class="text-xs text-gray-500">{{ $entry['label'] }}</p>
                <div class="mt-2 flex items-start gap-3">
                    @if ($entry['url'])
                        <button type="button"
                                @click="openPreview(@js($entry['url']), @js($entry['label']))"
                                class="h-28 w-24 shrink-0 rounded-lg ring-1 ring-brand/15 overflow-hidden bg-white cursor-zoom-in block shadow-sm hover:ring-brand/40 transition"
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
                                    class="inline-flex items-center justify-center self-start rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-semibold text-brand hover:bg-brand-muted/40">
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
        @if ($canReplaceFace)
            <a href="{{ route('site.borrower.face-verification') }}"
               class="inline-flex items-center justify-center font-semibold px-4 py-2 rounded-full text-sm bg-brand-gold hover:bg-yellow-400 text-brand">
                {{ __('borrower.nida.face_replace') }}
            </a>
        @elseif ($statusKey === 'verified')
            <a href="{{ route('site.borrower.face-verification') }}"
               class="inline-flex items-center justify-center font-semibold px-4 py-2 rounded-full text-sm bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand">
                {{ __('borrower.nida.face_manage') }}
            </a>
        @endif
    </div>

    {{-- Premium card-style enlarge preview --}}
    <div x-show="expandedUrl" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center p-4 sm:p-6"
         @keydown.escape.window="closePreview()"
         @click.self="closePreview()">
        <div class="absolute inset-0 bg-brand/80 backdrop-blur-sm" @click="closePreview()"></div>
        <div class="relative w-full max-w-lg" @click.stop>
            <div class="overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-white/20">
                <div class="bg-gradient-to-r from-brand via-brand to-brand-light px-5 py-3.5 flex items-center justify-between gap-3 text-white">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">{{ __('borrower.nida.face_title') }}</p>
                        <p class="font-semibold truncate" x-text="expandedLabel || @js(__('borrower.nida.face_preview'))"></p>
                    </div>
                    <button type="button" @click="closePreview()"
                            class="size-9 shrink-0 grid place-items-center rounded-full bg-white/15 hover:bg-white/25 text-white text-xl"
                            aria-label="{{ __('borrower.profile.cancel') }}">×</button>
                </div>
                <div class="bg-gradient-to-b from-brand-muted/30 to-gray-100 p-4 sm:p-5">
                    <div class="mx-auto max-w-sm overflow-hidden rounded-2xl bg-black shadow-lg ring-1 ring-brand/10">
                        <img :src="expandedUrl" alt="" class="w-full max-h-[70vh] object-contain object-top bg-black">
                    </div>
                </div>
                <div class="px-5 py-4 flex flex-wrap gap-2 border-t border-gray-100 bg-white">
                    <button type="button" @click="closePreview()"
                            class="inline-flex items-center justify-center font-semibold px-4 py-2.5 rounded-xl text-sm bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800">
                        {{ __('borrower.feedback.ok') }}
                    </button>
                    @if ($canReplaceFace)
                        <a href="{{ route('site.borrower.face-verification') }}"
                           class="inline-flex items-center justify-center font-semibold px-4 py-2.5 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand">
                            {{ __('borrower.nida.face_replace') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
