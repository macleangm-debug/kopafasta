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
@endphp

<div
    x-data="{ expandedUrl: null }"
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

    <div @class(['grid grid-cols-2 gap-3', 'p-5 sm:gap-4' => ! $compact])>
        @foreach ($angles as $key => $meta)
            @php
                $photo = $photos[$key] ?? null;
                $url = $photo?->file_path ? asset('storage/'.$photo->file_path) : null;
            @endphp
            <figure class="rounded-2xl overflow-hidden ring-1 ring-gray-200 bg-white shadow-sm">
                <div class="relative aspect-[3/4] bg-gradient-to-b from-gray-100 to-gray-200">
                    @if ($url)
                        <button type="button" @click="expandedUrl = @js($url)"
                                class="absolute inset-0 block group cursor-zoom-in text-left w-full">
                            <img
                                src="{{ $url }}"
                                alt="{{ $meta['label'] ?? $key }}"
                                class="absolute inset-0 w-full h-full object-cover object-top"
                                loading="lazy"
                            >
                            <span class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/55 to-transparent opacity-80 pointer-events-none"></span>
                            <span class="absolute bottom-2 left-2 right-2 text-[11px] font-semibold text-white drop-shadow-sm truncate">
                                {{ $meta['label'] ?? $key }}
                            </span>
                        </button>
                    @else
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 px-2 text-center">
                            <span class="text-2xl opacity-40" aria-hidden="true">📷</span>
                            <span class="text-xs text-gray-400">{{ __('borrower.nida.face_not_captured') }}</span>
                        </div>
                    @endif
                </div>
            </figure>
        @endforeach
    </div>

    @if ($statusKey === 'pending' && ! $compact)
        <div class="px-5 pb-5">
            <p class="text-sm text-gray-600">{{ __('borrower.nida.face_submitted_body') }}</p>
        </div>
    @endif

    <div @class(['px-5 pb-5 flex flex-wrap gap-2', 'mt-3' => $compact, 'pt-1' => ! $compact])>
        @php
            $firstPhoto = collect($photos)->first(fn ($p) => filled($p?->file_path));
            $firstUrl = $firstPhoto?->file_path ? asset('storage/'.$firstPhoto->file_path) : null;
            $canReplaceFace = in_array($statusKey, ['rejected', 'incomplete', 'pending'], true);
        @endphp
        @if ($firstUrl)
            <button type="button"
                    @click="expandedUrl = @js($firstUrl)"
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

    <div x-show="expandedUrl" x-cloak x-transition
         class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
         @keydown.escape.window="expandedUrl = null"
         @click.self="expandedUrl = null">
        <img :src="expandedUrl" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
    </div>
</div>
