@props([
    'message' => null,
])

@php
    $message = $message ?? __('borrower.profile.uploading');
@endphp

<div
    x-show="uploading"
    x-cloak
    class="fixed inset-0 z-[10060] flex items-center justify-center bg-brand/70 backdrop-blur-sm p-4"
    role="status"
    aria-live="polite"
>
    <div class="w-full max-w-sm rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15 px-6 py-8 text-center">
        <div class="mx-auto size-14 rounded-2xl bg-brand-muted grid place-items-center ring-1 ring-brand/20">
            <svg class="size-7 animate-spin text-brand" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        </div>
        <p class="mt-4 text-base font-bold text-gray-900">{{ $message }}</p>
        <p class="mt-1 text-sm text-gray-500">{{ __('borrower.profile.uploading_hint') }}</p>
    </div>
</div>
