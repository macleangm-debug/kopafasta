@props([
    'name',
    'label' => null,
    'required' => false,
    'maxPages' => 12,
])

@php
    $pageName = str_ends_with((string) $name, '_pages') ? (string) $name : $name.'_pages';
    $hostId = 'admin-doc-'.md5($pageName);
@endphp

<div class="space-y-2">
    @if ($label)
        <label class="block text-xs font-semibold text-gray-700">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <p class="text-xs text-gray-500">Upload a PDF or photos, or capture pages with the camera. Multiple pages become one PDF.</p>
    <div id="{{ $hostId }}" class="hidden"></div>
    <x-site.multi-page-document-upload
        :name="$pageName"
        :input-host-id="$hostId"
        :max-pages="$maxPages"
        :required="$required"
        :labels="[
            'uploadFile' => 'Upload file',
            'capturePage' => 'Capture page',
            'close' => 'Close',
            'pageLabel' => 'Page',
            'remove' => 'Remove',
            'addAnother' => 'Add another page',
            'pagesReady' => 'pages ready',
            'finish' => 'Done',
            'captureMore' => 'Capture another',
            'addPicture' => 'Add picture',
        ]"
    />
    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error($pageName)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error($pageName.'.*')
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
