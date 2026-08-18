@props([
    'url',
    'previewLabel' => 'Preview',
    'useAdminPreview' => true,
])

@php
    $downloadUrl = $url.(str_contains((string) $url, '?') ? '&' : '?').'download=1';
@endphp

<div class="flex flex-wrap items-center gap-2 shrink-0">
    @if ($useAdminPreview)
        <x-admin.document-preview :url="$url" :label="$previewLabel" type="pdf" />
    @else
        <x-site.document-view-button :url="$url" type="pdf" :label="$previewLabel" class="text-xs font-semibold text-brand hover:text-brand-light bg-white ring-1 ring-brand/15 px-3 py-1.5 rounded-lg shrink-0" />
    @endif
    <a href="{{ $downloadUrl }}"
       class="text-xs font-semibold text-gray-700 hover:text-brand bg-white ring-1 ring-gray-200 hover:ring-brand/20 px-3 py-1.5 rounded-lg shrink-0">
        Download PDF
    </a>
</div>
