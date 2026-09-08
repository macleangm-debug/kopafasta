{{--
  Canonical Document Holder for apply-wizard product documents (Education, Emergency, …).
  Expects: $docCode, $label, $required (bool), $hint (optional string), $hostPrefix (e.g. education / emergency)
--}}
@php
    $docCode = $docCode ?? 'document';
    $label = $label ?? __('borrower.profile.view_document');
    $required = (bool) ($required ?? false);
    $hint = $hint ?? '';
    $hostPrefix = $hostPrefix ?? 'apply';
    $hostId = $hostPrefix.'-'.$docCode.'-upload';
    $errorKey = $errorKey ?? null;
@endphp
<div class="space-y-3 sm:col-span-2" x-data="{ replaceMode: false }">
    <label class="block text-sm font-semibold text-gray-700">
        {{ $label }}
        @if ($required) <span class="text-rose-500">*</span> @endif
    </label>
    @if ($hint !== '')
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif

    <div x-show="educationDocuments[@js($docCode)]?.customer_document_id && !replaceMode" x-cloak
         class="rounded-xl p-4 ring-1 bg-emerald-50 ring-emerald-200">
        <div class="flex flex-col sm:flex-row sm:items-start gap-3 sm:gap-4">
            <div class="flex items-start gap-3 min-w-0 flex-1">
                <div class="shrink-0">
                    <template x-if="educationDocuments[@js($docCode)]?.is_pdf">
                        <button type="button"
                                @click="window.kfSiteOpenDocumentPreview?.(educationDocuments[@js($docCode)].view_url, @js($label), 'pdf')"
                                class="h-16 w-16 sm:h-24 sm:w-24 rounded-lg ring-1 ring-emerald-200 bg-white flex flex-col items-center justify-center text-emerald-800 cursor-zoom-in"
                                title="{{ __('borrower.profile.view_document') }}">
                            <svg class="h-8 w-8 sm:h-10 sm:w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span class="text-[10px] font-bold mt-0.5 sm:mt-1">PDF</span>
                        </button>
                    </template>
                    <template x-if="!educationDocuments[@js($docCode)]?.is_pdf && educationDocuments[@js($docCode)]?.view_url">
                        <button type="button"
                                @click="window.kfSiteOpenDocumentPreview?.(educationDocuments[@js($docCode)].view_url, @js($label), 'image')"
                                class="h-16 w-16 sm:h-24 sm:w-24 rounded-lg ring-1 ring-emerald-200 overflow-hidden bg-white cursor-zoom-in block"
                                title="{{ __('borrower.profile.view_document') }}">
                            <img :src="educationDocuments[@js($docCode)].view_url" alt="" class="h-full w-full object-cover object-center">
                        </button>
                    </template>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-emerald-900 truncate">{{ $label }}</p>
                    <dl class="mt-2 space-y-1 text-xs text-emerald-800">
                        <div class="truncate" x-show="educationDocuments[@js($docCode)]?.file_name">
                            <span class="font-medium">{{ __('borrower.profile.document_file_name') }}:</span>
                            <span x-text="educationDocuments[@js($docCode)]?.file_name"></span>
                        </div>
                        <div x-show="educationDocuments[@js($docCode)]?.uploaded_at">
                            <span class="font-medium">{{ __('borrower.profile.uploaded_on') }}</span>
                            <span x-text="educationDocuments[@js($docCode)]?.uploaded_at"></span>
                        </div>
                        <div>
                            <span class="font-medium">{{ __('borrower.profile.document_status_label') }}:</span>
                            {{ __('borrower.documents_page.status_pending') }}
                        </div>
                    </dl>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap w-full sm:w-auto">
                <button type="button"
                        x-show="educationDocuments[@js($docCode)]?.view_url"
                        @click="window.kfSiteOpenDocumentPreview?.(educationDocuments[@js($docCode)].view_url, @js($label), educationDocuments[@js($docCode)]?.is_pdf ? 'pdf' : 'image')"
                        class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                    {{ __('borrower.profile.view_document') }}
                </button>
                <button type="button"
                        @click="replaceMode = true"
                        class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                    {{ __('borrower.profile.replace_document') }}
                </button>
                <button type="button"
                        @click="removeEducationDocument(@js($docCode)); replaceMode = false"
                        class="inline-flex items-center rounded-full bg-white ring-1 ring-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                    {{ __('borrower.profile.remove_document') }}
                </button>
            </div>
        </div>
    </div>

    <div x-show="!educationDocuments[@js($docCode)]?.customer_document_id || replaceMode"
         class="space-y-3"
         @kf-document-file.window="
            if ($event.detail?.hostId === @js($hostId) && $event.detail?.file) {
                uploadEducationDocument(@js($docCode), { target: { files: [$event.detail.file], value: '' } })
                    .then(() => { replaceMode = false; @if($errorKey) educationErrors[@js($errorKey)] = ''; @endif });
            }
         ">
        <x-site.single-image-document-upload
            :name="$hostPrefix.'_'.$docCode"
            :input-host-id="$hostId"
            facing="environment"
            :required="$required"
            :guide="__('borrower.document_upload.guide_document')"
        />
        <button type="button"
                x-show="replaceMode && educationDocuments[@js($docCode)]?.customer_document_id"
                x-cloak
                @click="replaceMode = false"
                class="text-sm font-semibold text-gray-500 hover:text-gray-700">
            {{ __('borrower.profile.cancel_update') }}
        </button>
        <div x-show="educationDocumentUploading && educationDocumentUploadCode === @js($docCode)" x-cloak
             class="rounded-xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3 space-y-2">
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
           class="text-sm font-medium text-rose-600" x-text="educationDocumentUploadError"></p>
    </div>

    <input type="hidden"
           name="product_question[{{ $hiddenName ?? ($docCode.'_document_id') }}]"
           :value="educationDocuments[@js($docCode)]?.customer_document_id || ''"
           @if ($required) data-education-doc-required="1" @endif>

    @if ($errorKey)
        <p x-show="educationErrors[@js($errorKey)]" x-cloak class="text-sm text-rose-600 font-medium" x-text="educationErrors[@js($errorKey)]"></p>
    @endif
</div>
