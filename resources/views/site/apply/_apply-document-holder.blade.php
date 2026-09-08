{{--
  Canonical Document Holder for apply-wizard product documents.
  Compact card + branded + → Upload / Camera (document-source-picker).
  Expects: $docCode, $label, $required, $hint?, $hostPrefix, $multiPage?, $guideCompact?, $capture?
  Generic documents default to multi-page camera → one PDF. Pass capture=images (or multiPage=false)
  only for photo collections / specialized single captures.
--}}
@php
    $docCode = $docCode ?? 'document';
    $label = $label ?? __('borrower.profile.view_document');
    $required = (bool) ($required ?? false);
    $hint = $hint ?? '';
    $hostPrefix = $hostPrefix ?? 'apply';
    $hostId = $hostPrefix.'-'.$docCode.'-upload';
    $errorKey = $errorKey ?? null;
    $capture = $capture ?? null;
    $multiPageArg = $multiPage ?? null;
    if ($multiPageArg === null) {
        // Generic documents → multi-page PDF. Photo collections / specialized flows opt out.
        $multiPage = ! in_array($capture, ['images', 'single', 'selfie', 'nida'], true);
    } else {
        $multiPage = (bool) $multiPageArg;
    }
    $guideCompact = (bool) ($guideCompact ?? true);
    $guideText = $guideCompact
        ? __('borrower.document_upload.guide_document_compact')
        : __('borrower.document_upload.guide_document');
@endphp
<div class="sm:col-span-2"
     x-data="{
        replaceMode: false,
        sourceOpen: false,
        captureOpen: false,
        pendingSource: null,
        openCapture(source) {
            this.pendingSource = source;
            this.captureOpen = true;
            this.$nextTick(() => {
                if (source === 'camera') {
                    this.$dispatch('document-open-camera', { hostId: @js($hostId) });
                } else {
                    this.$dispatch('document-open-upload', { hostId: @js($hostId) });
                }
            });
        },
     }"
     @document-source="openCapture($event.detail?.source)">
    <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-bold text-gray-900">{{ $label }}</p>
                    @if ($required)
                        <span class="inline-flex rounded-full bg-rose-50 text-rose-700 ring-1 ring-rose-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">{{ __('borrower.application.status_required') }}</span>
                    @else
                        <span class="inline-flex rounded-full bg-gray-50 text-gray-600 ring-1 ring-gray-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">{{ __('borrower.application.status_optional') }}</span>
                    @endif
                    <span x-show="educationDocuments[@js($docCode)]?.customer_document_id && !replaceMode" x-cloak
                          class="inline-flex rounded-full bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">
                        {{ __('borrower.documents_page.status_pending') }}
                    </span>
                </div>
                @if ($hint !== '')
                    <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
                @endif
                <p x-show="educationDocuments[@js($docCode)]?.file_name && !replaceMode" x-cloak
                   class="mt-1 text-xs text-gray-600 truncate"
                   x-text="educationDocuments[@js($docCode)]?.file_name"></p>
            </div>

            <div x-show="!educationDocuments[@js($docCode)]?.customer_document_id || replaceMode" class="shrink-0">
                <x-site.document-source-picker open="sourceOpen" />
            </div>
        </div>

        <div x-show="educationDocuments[@js($docCode)]?.customer_document_id && !replaceMode" x-cloak class="mt-3 flex flex-wrap gap-2">
            <button type="button"
                    x-show="educationDocuments[@js($docCode)]?.view_url"
                    @click="window.kfSiteOpenDocumentPreview?.(educationDocuments[@js($docCode)].view_url, @js($label), educationDocuments[@js($docCode)]?.is_pdf ? 'pdf' : 'image')"
                    class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                {{ __('borrower.profile.view_document') }}
            </button>
            <button type="button" @click="replaceMode = true"
                    class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                {{ __('borrower.profile.replace_document') }}
            </button>
            <button type="button" @click="removeEducationDocument(@js($docCode)); replaceMode = false"
                    class="inline-flex items-center rounded-full bg-white ring-1 ring-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                {{ __('borrower.profile.remove_document') }}
            </button>
        </div>

        <div x-show="captureOpen || replaceMode" x-cloak class="mt-4 space-y-3"
             @kf-document-file.window="
                if ($event.detail?.hostId === @js($hostId) && $event.detail?.file) {
                    uploadEducationDocument(@js($docCode), { target: { files: [$event.detail.file], value: '' } })
                        .then(() => { replaceMode = false; captureOpen = false; @if($errorKey) educationErrors[@js($errorKey)] = ''; @endif });
                }
             "
             @kf-document-pages-ready.window="
                if ($event.detail?.hostId === @js($hostId)) {
                    uploadEducationDocumentPages(@js($docCode), @js($hostId))
                        .then(() => { replaceMode = false; captureOpen = false; @if($errorKey) educationErrors[@js($errorKey)] = ''; @endif });
                }
             ">
            @if ($multiPage)
                <x-site.multi-page-document-upload
                    :name="$hostPrefix.'_'.$docCode"
                    :input-host-id="$hostId"
                    :required="$required"
                    :camera-first="true"
                    :max-pages="12"
                    :auto-finish-upload="true"
                />
                <p class="text-xs text-gray-500">{{ $guideText }}</p>
            @else
                <x-site.single-image-document-upload
                    :name="$hostPrefix.'_'.$docCode"
                    :input-host-id="$hostId"
                    facing="environment"
                    :required="$required"
                    :guide="$guideText"
                    :source-driven="true"
                />
            @endif
            <button type="button"
                    x-show="replaceMode && educationDocuments[@js($docCode)]?.customer_document_id"
                    x-cloak
                    @click="replaceMode = false; captureOpen = false"
                    class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                {{ __('borrower.profile.cancel_update') }}
            </button>
        </div>

        <div x-show="educationDocumentUploading && educationDocumentUploadCode === @js($docCode)" x-cloak
             class="mt-3 rounded-xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3 space-y-2">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-brand">{{ __('borrower.apply.document_saving') }}</p>
                <p class="text-sm font-bold tabular-nums text-brand"
                   x-show="educationDocumentUploadProgress !== null"
                   x-text="(educationDocumentUploadProgress ?? 0) + '%'"></p>
            </div>
            <div class="h-2 rounded-full bg-white overflow-hidden ring-1 ring-brand/10"
                 x-show="educationDocumentUploadProgress !== null">
                <div class="h-full bg-brand transition-[width] duration-150"
                     :style="'width:' + (educationDocumentUploadProgress ?? 0) + '%'"></div>
            </div>
            <div class="h-2 rounded-full bg-white overflow-hidden ring-1 ring-brand/10"
                 x-show="educationDocumentUploadProgress === null">
                <div class="h-full w-1/3 bg-brand animate-pulse rounded-full"></div>
            </div>
        </div>
        <p x-show="educationDocumentUploadError && educationDocumentUploadCode === @js($docCode)" x-cloak
           class="mt-2 text-sm font-medium text-rose-600" x-text="educationDocumentUploadError"></p>
    </div>

    <input type="hidden"
           name="product_question[{{ $hiddenName ?? ($docCode.'_document_id') }}]"
           :value="educationDocuments[@js($docCode)]?.customer_document_id || ''"
           @if ($required) data-education-doc-required="1" @endif>

    @if ($errorKey)
        <p x-show="educationErrors[@js($errorKey)]" x-cloak class="mt-1.5 text-sm text-rose-600 font-medium" x-text="educationErrors[@js($errorKey)]"></p>
    @endif
</div>
