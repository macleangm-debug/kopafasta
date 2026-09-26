@props([
    'name',
    'label',
    'required' => false,
    'capture' => null, // nida | null (ordinary docs)
])

@php
    $hostId = 'form-doc-'.md5((string) $name);
    $isIdentity = in_array($capture, ['nida', 'single'], true);
@endphp

<div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm"
     data-document-holder
     data-document-attach-only
     x-data="{
        captureOpen: false,
        fileName: '',
        openCapture(source) {
            this.captureOpen = true;
            this.$nextTick(() => {
                this.$dispatch(source === 'camera' ? 'document-open-camera' : 'document-open-upload', {
                    hostId: @js($hostId),
                    fresh: true,
                });
            });
        },
     }"
     @document-source.window="
        if ($event.detail?.hostId && $event.detail.hostId !== @js($hostId)) return;
        openCapture($event.detail?.source);
     "
     @kf-document-file.window="
        if ($event.detail?.hostId === @js($hostId) && $event.detail?.file) {
            fileName = $event.detail.file.name || @js(__('borrower.profile.view_document'));
            captureOpen = false;
        }
     "
     @kf-document-pages-ready.window="
        if ($event.detail?.hostId === @js($hostId)) {
            fileName = @js(__('borrower.profile.view_document'));
            captureOpen = false;
        }
     ">
    <div class="flex items-start gap-3">
        <div class="size-14 rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 shrink-0 grid place-items-center text-brand">
            <span class="text-[10px] font-bold tracking-wide" x-text="fileName ? '✓' : 'DOC'"></span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-gray-900">
                {{ $label }}
                @if ($required)<span class="text-red-500">*</span>@endif
            </p>
            <p class="mt-1 text-xs text-gray-500 truncate" x-show="fileName" x-cloak x-text="fileName"></p>
            <p class="mt-1 text-xs text-gray-500" x-show="!fileName">{{ __('borrower.document_upload.guide_document_compact') }}</p>
        </div>
        <x-site.document-source-picker :host-id="$hostId" />
    </div>

    <div class="mt-3" x-show="captureOpen || fileName" x-cloak>
        @if ($isIdentity)
            <x-site.single-image-document-upload
                :name="$name"
                :input-host-id="$hostId"
                facing="environment"
                :required="$required"
                source-driven="true"
                guide-frame="id-card"
            />
        @else
            <x-site.multi-page-document-upload
                :name="$name"
                :input-host-id="$hostId"
                :required="$required"
                :source-driven="true"
                :auto-finish-upload="true"
                output-mode="pdf"
            />
        @endif
    </div>
</div>
