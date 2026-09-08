@props([
    'document' => null,
    'fieldName' => 'document',
    'pagesFieldName' => null,
    'mode' => 'multi',
    'label' => '',
    'required' => false,
    'inputHostId' => null,
    'labels' => [],
    'removeUrl' => null,
    'documentCode' => null,
    'readOnly' => false,
    'nested' => false,
    'allowRemove' => true,
    'allowReplace' => true,
    /** When true, Replace opens the parent profile-section-card edit surface (`open = true`) instead of inline replaceMode. */
    'replaceOpensEdit' => false,
])

@php
    $docService = app(\App\Services\ProfileDocumentService::class);
    $pagesName = $pagesFieldName ?? ($fieldName.'_pages');
    $hostId = $inputHostId ?? ($fieldName.'-upload');
    $documentCode = $documentCode ?? $fieldName;
    $removeUrl = $removeUrl ?? ($document && $allowRemove ? route('site.borrower.profile.documents.destroy', ['code' => $documentCode]) : null);
    $isPdf = $document && $document->file_path && str_ends_with(strtolower($document->file_path), '.pdf');
    $isImage = $document && $document->file_path && ! $isPdf;
    $meta = $document ? $docService->metadata($document) : [];
    $pageCount = (int) ($meta['page_count'] ?? 1);
    $fileName = (string) ($meta['original_name'] ?? ($document?->file_path ? basename($document->file_path) : ''));
    $statusLabel = $document ? $docService->statusLabel($document) : '';
    $previewUrl = ($document && $document->file_path) ? asset('storage/'.$document->file_path) : null;
    $fileExt = strtoupper(pathinfo($fileName !== '' ? $fileName : (string) ($document?->file_path ?? ''), PATHINFO_EXTENSION) ?: 'FILE');
    $type = $document?->documentType
        ?? \App\Models\DocumentType::query()->where('code', $documentCode)->first();
    $requiresExpiry = $docService->typeRequiresExpiry($type, $documentCode);
    $expiresAt = $document ? $docService->expiryDate($document) : null;
    $needsUpdate = $document ? $docService->isExpired($document) : false;
    $expiresField = $fieldName.'_expires_at';
    $guideText = __('borrower.document_upload.guide_document_compact');
@endphp

<div x-data="{
        replaceMode: false,
        sourceOpen: false,
        captureOpen: {{ $document ? 'false' : 'true' }},
        inlineUploading: false,
        inlineProgress: null,
        inlineMessage: @js(__('borrower.apply.document_saving')),
        openCapture(source) {
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
     @document-source="openCapture($event.detail?.source)"
     @kf-inline-document-upload-start="
        inlineUploading = true;
        inlineProgress = null;
        if ($event.detail?.message) inlineMessage = $event.detail.message;
     "
     @kf-inline-document-upload-progress.window="
        if ($event.detail?.hostId && $event.detail.hostId !== @js($hostId)) return;
        inlineUploading = true;
        if ($event.detail?.percent != null) inlineProgress = $event.detail.percent;
        if ($event.detail?.message) inlineMessage = $event.detail.message;
     "
     data-document-holder
     class="space-y-3">
    @if ($document)
        <div @class([
            'rounded-2xl px-4 py-3.5 ring-1 shadow-sm bg-white',
            'ring-amber-200' => $needsUpdate,
            'ring-gray-200' => ! $needsUpdate,
        ])>
            <div class="flex items-start gap-3">
                @if ($previewUrl)
                    <div class="size-14 rounded-xl overflow-hidden bg-brand-muted/40 ring-1 ring-brand/10 shrink-0 grid place-items-center">
                        @if ($isImage)
                            <button type="button"
                                    onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), 'image')"
                                    class="size-full block cursor-zoom-in">
                                <img src="{{ $previewUrl }}" alt="" class="size-full object-cover">
                            </button>
                        @else
                            <button type="button"
                                    onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), 'pdf')"
                                    class="size-full flex flex-col items-center justify-center text-brand cursor-zoom-in">
                                <span class="text-[10px] font-bold tracking-wide">{{ $fileExt ?: 'PDF' }}</span>
                            </button>
                        @endif
                    </div>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-bold text-gray-900 truncate">{{ $label ?: __('borrower.profile.document_uploaded') }}</p>
                        @if ($required)
                            <span class="inline-flex rounded-full bg-rose-50 text-rose-700 ring-1 ring-rose-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">{{ __('borrower.application.status_required') }}</span>
                        @endif
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1',
                            'bg-amber-50 text-amber-900 ring-amber-200' => $needsUpdate,
                            'bg-emerald-50 text-emerald-800 ring-emerald-200' => ! $needsUpdate,
                        ])>{{ $statusLabel }}</span>
                    </div>
                    @if ($fileName !== '')
                        <p class="mt-1 text-xs text-gray-600 truncate" title="{{ $fileName }}">{{ $fileName }}</p>
                    @endif
                    @if ($mode === 'multi' && $pageCount > 1)
                        <p class="mt-0.5 text-xs text-gray-500">{{ __('borrower.profile.document_page_count') }}: {{ $pageCount }}</p>
                    @endif
                </div>
                @unless ($readOnly)
                    <div x-show="replaceMode" class="shrink-0">
                        <x-site.document-source-picker open="sourceOpen" />
                    </div>
                @endunless
            </div>

            @if ($document->file_path)
                <div class="mt-3 flex flex-wrap gap-2" x-show="!replaceMode">
                    @if ($previewUrl)
                        <button type="button"
                                onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), @js($isPdf ? 'pdf' : 'image'))"
                                class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                            {{ __('borrower.profile.view_document') }}
                        </button>
                    @endif
                    @if ($allowReplace && ($replaceOpensEdit || ! $readOnly))
                        <button type="button"
                                @click="{{ $replaceOpensEdit ? 'open = true' : 'replaceMode = true; captureOpen = true' }}"
                                class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                            {{ __('borrower.profile.replace_document') }}
                        </button>
                    @endif
                    @if ($allowRemove && ($removeUrl ?? null))
                        <form method="POST" action="{{ $removeUrl }}"
                              @click.stop
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('borrower.profile.remove_document_confirm_title')),
                                  message: @js(__('borrower.profile.remove_document_confirm_named', ['document' => $label ?: __('borrower.profile.document_uploaded')])),
                                  confirmLabel: @js(__('borrower.profile.remove_document_confirm_cta')),
                                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                  tone: 'warning'
                              })">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center rounded-full bg-white ring-1 ring-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                {{ __('borrower.profile.remove_document') }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    @elseif ($readOnly)
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">{{ $label ?: __('borrower.profile.document_uploaded') }}</p>
                    <p class="text-sm font-semibold text-amber-700 mt-1">{{ __('borrower.profile.missing') }}</p>
                </div>
                @if ($allowReplace && $replaceOpensEdit)
                    <button type="button" @click="open = true" class="kf-request-add" aria-label="{{ __('borrower.documents_page.add_document') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    @else
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-bold text-gray-900">{{ $label ?: __('borrower.documents_page.add_document') }}</p>
                        @if ($required)
                            <span class="inline-flex rounded-full bg-rose-50 text-rose-700 ring-1 ring-rose-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">{{ __('borrower.application.status_required') }}</span>
                        @else
                            <span class="inline-flex rounded-full bg-gray-50 text-gray-600 ring-1 ring-gray-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">{{ __('borrower.application.status_optional') }}</span>
                        @endif
                    </div>
                </div>
                <div class="shrink-0">
                    <x-site.document-source-picker open="sourceOpen" />
                </div>
            </div>
        </div>
    @endif

    @unless ($readOnly || $replaceOpensEdit)
    <div x-show="(!@js((bool) $document) && captureOpen) || replaceMode" x-cloak class="space-y-3">
        @if ($mode === 'single')
            <x-site.single-image-document-upload
                :name="$fieldName"
                :input-host-id="$hostId"
                :labels="$labels"
                facing="environment"
                :required="$required"
                :guide="$guideText"
                :source-driven="true"
            />
        @else
            <x-site.multi-page-document-upload
                :name="$pagesName"
                :input-host-id="$hostId"
                :labels="$labels"
                :required="$required"
                :camera-first="true"
                :source-driven="true"
            />
            <p class="text-xs text-gray-500">{{ $guideText }}</p>
        @endif

        @if ($requiresExpiry)
            <div>
                <x-site.date-input
                    :name="$expiresField"
                    :label="__('borrower.profile.expiry_date')"
                    :value="old($expiresField, $expiresAt?->format('Y-m-d'))"
                    :required="$required || (bool) $document"
                    :min="now()->toDateString()"
                    :max="now()->addYears(20)->format('Y-m-d')"
                    :default="now()->addYear()->format('Y-m-d')"
                    input-class="kf-field max-w-xs inline-flex items-center justify-between gap-3 text-left"
                />
            </div>
        @endif

        @if ($document)
            <button type="button" @click="replaceMode = false; captureOpen = false" class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                {{ __('borrower.profile.cancel_update') }}
            </button>
        @endif
    </div>
    @endunless

    @error($fieldName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error($pagesName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error($pagesName.'.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

    <div x-show="inlineUploading" x-cloak
         class="rounded-xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3 space-y-2">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-brand"
               x-text="inlineProgress === null ? @js(__('borrower.document_upload.processing')) : (inlineMessage || @js(__('borrower.apply.document_saving')))"></p>
            <p class="text-sm font-bold tabular-nums text-brand"
               x-show="inlineProgress !== null"
               x-text="(inlineProgress ?? 0) + '%'"></p>
        </div>
        <div class="h-2 rounded-full bg-white overflow-hidden ring-1 ring-brand/10"
             x-show="inlineProgress !== null">
            <div class="h-full bg-brand transition-[width] duration-150"
                 :style="'width:' + (inlineProgress ?? 0) + '%'"></div>
        </div>
        <div class="h-2 rounded-full bg-white overflow-hidden ring-1 ring-brand/10"
             x-show="inlineProgress === null">
            <div class="h-full w-1/3 bg-brand animate-pulse rounded-full"></div>
        </div>
    </div>
</div>
