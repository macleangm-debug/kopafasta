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

<div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 space-y-3" data-document-holder data-document-attach-only
     @document-source.window="
        if ($event.detail?.hostId && $event.detail.hostId !== @js($hostId)) return;
        $dispatch($event.detail?.source === 'camera' ? 'document-open-camera' : 'document-open-upload', {
            hostId: @js($hostId),
            fresh: true,
        });
     ">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            @if ($label)
                <label class="block text-xs font-semibold text-gray-700">
                    {{ $label }}
                    @if ($required)<span class="text-red-500">*</span>@endif
                </label>
            @endif
            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.document_upload.guide_document_compact') }}</p>
        </div>
        <x-site.document-source-picker :host-id="$hostId" />
    </div>
    <x-site.multi-page-document-upload
        :name="$pageName"
        :input-host-id="$hostId"
        :max-pages="$maxPages"
        :required="$required"
        :source-driven="true"
        :auto-finish-upload="true"
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
