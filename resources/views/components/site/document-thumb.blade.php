@props([
    'url',
    'label' => null,
])

@php
    $path = strtolower((string) parse_url((string) $url, PHP_URL_PATH));
    $isPdf = str_ends_with($path, '.pdf');
    $isImage = preg_match('/\.(jpe?g|png|gif|webp)$/', $path) === 1;
    $label = $label ?: __('borrower.application.view_upload');
@endphp

<div x-data="{ expanded: false }" class="inline-flex">
    @if ($isImage)
        <button type="button" @click="expanded = true"
                class="h-16 w-16 rounded-lg overflow-hidden ring-1 ring-gray-200 bg-white cursor-zoom-in block"
                title="{{ $label }}">
            <img src="{{ $url }}" alt="" class="h-full w-full object-cover">
        </button>
        <div x-show="expanded" x-cloak x-transition
             class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
             @keydown.escape.window="expanded = false"
             @click.self="expanded = false">
            <button type="button" class="absolute top-4 right-4 text-white/90 text-sm font-semibold" @click="expanded = false">
                {{ __('borrower.profile.cancel') }}
            </button>
            <img src="{{ $url }}" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
        </div>
    @elseif ($isPdf)
        <a href="{{ $url }}" target="_blank" rel="noopener"
           class="h-16 w-16 rounded-lg ring-1 ring-gray-200 bg-white flex flex-col items-center justify-center text-gray-700 hover:bg-gray-50"
           title="{{ $label }}">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            <span class="text-[10px] font-bold mt-0.5">PDF</span>
        </a>
    @else
        <a href="{{ $url }}" target="_blank" rel="noopener"
           class="text-xs font-semibold text-amber-700 hover:underline">{{ $label }}</a>
    @endif
</div>
