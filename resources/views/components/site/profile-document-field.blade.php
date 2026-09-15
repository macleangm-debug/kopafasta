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
    /** @deprecated Replace always stays inline (Upload/Camera). Add-document CTAs open the section form. */
    'replaceOpensEdit' => false,
    'guide' => null,
    'guideFrame' => null,
    'startOpen' => false,
    'compactLabel' => false,
    'showReplaceButton' => true,
    'showViewButton' => true,
    'holderSide' => null,
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
    $previewUrl = ($document && $document->file_path) ? asset('storage/'.$document->file_path) : null;
    $fileExt = strtoupper(pathinfo($fileName !== '' ? $fileName : (string) ($document?->file_path ?? ''), PATHINFO_EXTENSION) ?: 'FILE');
    $type = $document?->documentType
        ?? \App\Models\DocumentType::query()->where('code', $documentCode)->first();
    $requiresExpiry = $docService->typeRequiresExpiry($type, $documentCode);
    $expiresAt = $document ? $docService->expiryDate($document) : null;
    $needsUpdate = $document ? $docService->isExpired($document) : false;
    $expiresField = $fieldName.'_expires_at';
    $guideText = $guide ?: __('borrower.document_upload.guide_document_compact');
    $guideFrame = in_array($guideFrame, ['id-card', 'oval'], true) ? $guideFrame : null;
    // Ordinary documents use multi-page. Only explicit mode=single stays directive/single-image.
    $mode = in_array($mode, ['single', 'multi'], true) ? $mode : 'multi';
    $startOpen = (bool) $startOpen;
    $showReplaceButton = (bool) $showReplaceButton;
    $showViewButton = (bool) $showViewButton;
    $holderSide = filled($holderSide) ? (string) $holderSide : null;
@endphp

<div x-data="{
        replaceMode: false,
        {{-- Closed until + → Take photo / Upload. Never show permanent Upload+Camera. --}}
        captureOpen: @js($startOpen && ! $document),
        inlineUploading: false,
        inlineProgress: null,
        inlineMessage: @js(__('borrower.apply.document_saving')),
        hasLiveDoc: @js((bool) $document),
        liveDoc: @js($document ? [
            'previewUrl' => $previewUrl,
            'fileName' => $fileName,
            'isPdf' => (bool) $isPdf,
            'label' => $label ?: __('borrower.profile.document_uploaded'),
        ] : null),
        startReplace() {
            this.replaceMode = true;
            this.captureOpen = true;
            this.$dispatch('clear-capture', { hostId: @js($hostId) });
        },
        openCapture(source) {
            this.captureOpen = true;
            this.replaceMode = true;
            this.$nextTick(() => {
                if (source === 'camera') {
                    this.$dispatch('document-open-camera', { hostId: @js($hostId), fresh: true });
                } else {
                    this.$dispatch('document-open-upload', { hostId: @js($hostId), fresh: true });
                }
            });
        },
        submitProfileDocumentForm() {
            let node = this.$el.parentElement;
            while (node) {
                if (node.tagName === 'FORM' && node.method && String(node.method).toLowerCase() === 'post') {
                    const methodField = node.querySelector('input[name=_method]');
                    const spoof = methodField ? String(methodField.value || '').toUpperCase() : '';
                    // Skip nested delete forms; only submit the profile update form.
                    if (spoof === 'DELETE') {
                        node = node.parentElement;
                        continue;
                    }
                    if (node.dataset.kfSubmitting === '1') return;
                    node.dataset.kfSubmitting = '1';
                    this.submitProfileDocumentViaFetch(node);
                    return;
                }
                node = node.parentElement;
            }
        },
        async submitProfileDocumentViaFetch(form) {
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            const fd = new FormData(form);
            this.inlineUploading = true;
            this.inlineProgress = null;
            if (typeof window.kfShowInlineSaving === 'function') {
                window.kfShowInlineSaving(this.inlineMessage || form.getAttribute('data-saving-message') || 'Saving…');
            }
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-KF-Autosave': '1',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    credentials: 'same-origin',
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (! res.ok || data.ok === false || data.saved === false) {
                    throw new Error(data.message || 'Upload failed');
                }
                // Apply uploaded document into this holder without page navigation.
                const docs = Array.isArray(data.documents) ? data.documents : [];
                const fieldName = @js($fieldName);
                const match = docs.find((d) => d.code === fieldName || d.field === fieldName) || docs[0];
                if (match) {
                    this.applyLiveDocument(match);
                }
                this.replaceMode = false;
                this.captureOpen = false;
                this.inlineUploading = false;
                this.inlineProgress = 100;
                if (typeof window.kfRefreshProfileCompletion === 'function') {
                    // Full JSON payload: completion + documents for live View paint.
                    window.kfRefreshProfileCompletion(data);
                }
                // Keep the Profile card open — never treat upload as Continue.
                if (typeof window.kfFlashInlineSaved === 'function') {
                    window.kfFlashInlineSaved(data.message || @js(__('borrower.document_upload.saved')));
                } else if (typeof window.kfHideSaving === 'function') {
                    window.kfHideSaving();
                }
                // Clear file inputs so a retry does not re-upload stale blobs.
                form.querySelectorAll('input[type=file]').forEach((input) => { input.value = ''; });
                this.$dispatch('clear-capture', { hostId: @js($hostId) });
            } catch (e) {
                this.inlineUploading = false;
                this.inlineProgress = null;
                if (typeof window.kfShowSaveError === 'function') {
                    window.kfShowSaveError(
                        e?.message || @js(__('borrower.document_upload.could_not_save')),
                        @js(__('borrower.document_upload.retry')),
                        () => this.submitProfileDocumentViaFetch(form),
                    );
                } else if (typeof window.kfHideSaving === 'function') {
                    window.kfHideSaving();
                }
            } finally {
                delete form.dataset.kfSubmitting;
            }
        },
        applyLiveDocument(doc) {
            const previewUrl = doc.preview_url || doc.previewUrl || null;
            const isPdf = !!(doc.is_pdf ?? doc.isPdf);
            this.liveDoc = {
                previewUrl,
                fileName: doc.file_name || doc.fileName || '',
                isPdf,
                label: doc.label || @js($label ?: __('borrower.profile.document_uploaded')),
            };
            this.hasLiveDoc = true;
            // Keep SSR preview in sync when replacing an already-rendered document.
            if (previewUrl && ! isPdf) {
                const img = this.$el.querySelector('img');
                if (img) {
                    img.src = previewUrl;
                }
            }
        },
        confirmRemoveDocument() {
            window.confirmForm(null, {
                title: @js(__('borrower.profile.remove_document_confirm_title')),
                message: @js(__('borrower.profile.remove_document_confirm_named', ['document' => $label ?: __('borrower.profile.document_uploaded')])),
                confirmLabel: @js(__('borrower.profile.remove_document_confirm_cta')),
                confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                tone: 'warning',
                onConfirm: () => {
                    this.$dispatch('clear-capture', { hostId: @js($hostId) });
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = @js($removeUrl);
                    form.style.display = 'none';
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = document.querySelector('meta[name=csrf-token]')?.content || '';
                    const method = document.createElement('input');
                    method.type = 'hidden';
                    method.name = '_method';
                    method.value = 'DELETE';
                    form.appendChild(csrf);
                    form.appendChild(method);
                    document.body.appendChild(form);
                    form.submit();
                },
            });
        },
     }"
     @document-source.window="
        if ($event.detail?.hostId && $event.detail.hostId !== @js($hostId)) return;
        openCapture($event.detail?.source);
     "
     @nida-holder-replace.window="
        if (@js($holderSide) && $event.detail?.side === @js($holderSide)) {
            startReplace();
            return;
        }
        if ($event.detail?.hostId && $event.detail.hostId === @js($hostId)) {
            startReplace();
        }
     "
     @kf-document-pages-ready.window="
        if ($event.detail?.hostId && $event.detail.hostId !== @js($hostId)) return;
        submitProfileDocumentForm();
     "
     @kf-document-file.window="
        if ($event.detail?.hostId && $event.detail.hostId !== @js($hostId)) return;
        // Single-image commit already submits non-apply forms; keep as safety net.
     "
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
        <div x-show="replaceMode" x-cloak class="rounded-2xl px-4 py-3.5 ring-1 ring-brand/20 bg-brand/5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">{{ $label ?: __('borrower.profile.document_uploaded') }}</p>
                    <p class="mt-1 text-xs text-gray-600">{{ __('borrower.profile.replace_document') }}</p>
                </div>
                @unless ($readOnly)
                    <div class="shrink-0">
                        <x-site.document-source-picker :host-id="$hostId" />
                    </div>
                @endunless
            </div>
        </div>
        <div x-show="!replaceMode" x-cloak @class([
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
                    </div>
                    @if ($fileName !== '')
                        <p class="mt-1 text-xs text-gray-600 truncate" title="{{ $fileName }}">{{ $fileName }}</p>
                    @endif
                    @if ($mode === 'multi' && $pageCount > 1)
                        <p class="mt-0.5 text-xs text-gray-500">{{ __('borrower.profile.document_page_count') }}: {{ $pageCount }}</p>
                    @endif
                </div>
            </div>

            @if ($document->file_path)
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($previewUrl && $showViewButton)
                        <button type="button"
                                onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), @js($isPdf ? 'pdf' : 'image'))"
                                class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                            {{ __('borrower.profile.view_document') }}
                        </button>
                    @endif
                    @if ($allowReplace && $showReplaceButton)
                        <button type="button"
                                @click="startReplace()"
                                class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                            {{ __('borrower.profile.replace_document') }}
                        </button>
                    @endif
                    @if ($allowRemove && ($removeUrl ?? null))
                        <button type="button"
                                @click.stop="confirmRemoveDocument()"
                                class="inline-flex items-center rounded-full bg-white ring-1 ring-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                            {{ __('borrower.profile.remove_document') }}
                        </button>
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
        <div x-show="!hasLiveDoc" class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-bold text-gray-900">{{ $label ?: __('borrower.documents_page.add_document') }}</p>
                    </div>
                </div>
                <div class="shrink-0">
                    <x-site.document-source-picker :host-id="$hostId" />
                </div>
            </div>
        </div>
        <div x-show="hasLiveDoc && liveDoc && !replaceMode" x-cloak class="rounded-2xl px-4 py-3.5 ring-1 ring-gray-200 shadow-sm bg-white">
            <div class="flex items-start gap-3">
                <div class="size-14 rounded-xl overflow-hidden bg-brand-muted/40 ring-1 ring-brand/10 shrink-0 grid place-items-center"
                     x-show="liveDoc?.previewUrl">
                    <template x-if="liveDoc && !liveDoc.isPdf && liveDoc.previewUrl">
                        <button type="button"
                                @click="window.kfSiteOpenDocumentPreview(liveDoc.previewUrl, liveDoc.label || '', 'image')"
                                class="size-full block cursor-zoom-in">
                            <img :src="liveDoc.previewUrl" alt="" class="size-full object-cover">
                        </button>
                    </template>
                    <template x-if="liveDoc?.isPdf && liveDoc?.previewUrl">
                        <button type="button"
                                @click="window.kfSiteOpenDocumentPreview(liveDoc.previewUrl, liveDoc.label || '', 'pdf')"
                                class="size-full flex flex-col items-center justify-center text-brand cursor-zoom-in">
                            <span class="text-[10px] font-bold tracking-wide">PDF</span>
                        </button>
                    </template>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900 truncate" x-text="liveDoc?.label || ''"></p>
                    <p class="mt-1 text-xs text-gray-600 truncate" x-show="liveDoc?.fileName" x-text="liveDoc?.fileName || ''"></p>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button"
                        x-show="liveDoc?.previewUrl"
                        @click="window.kfSiteOpenDocumentPreview(liveDoc.previewUrl, liveDoc.label || '', liveDoc.isPdf ? 'pdf' : 'image')"
                        class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                    {{ __('borrower.profile.view_document') }}
                </button>
                @if ($allowReplace && $showReplaceButton)
                    <button type="button"
                            @click="startReplace()"
                            class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                        {{ __('borrower.profile.replace_document') }}
                    </button>
                @endif
            </div>
        </div>
        <div x-show="hasLiveDoc && replaceMode" x-cloak class="rounded-2xl px-4 py-3.5 ring-1 ring-brand/20 bg-brand/5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">{{ $label ?: __('borrower.profile.document_uploaded') }}</p>
                    <p class="mt-1 text-xs text-gray-600">{{ __('borrower.profile.replace_document') }}</p>
                </div>
                <div class="shrink-0">
                    <x-site.document-source-picker :host-id="$hostId" />
                </div>
            </div>
        </div>
    @endif

    {{-- Capture UI: available for add (non-readonly) and for Replace on an existing document. --}}
    @if ((! $readOnly) || ($allowReplace && $document))
    <div x-show="((!hasLiveDoc && !@js((bool) $document) && captureOpen) || replaceMode)" x-cloak class="space-y-3">
        @if ($mode === 'single')
            <x-site.single-image-document-upload
                :name="$fieldName"
                :input-host-id="$hostId"
                :labels="$labels"
                facing="environment"
                :required="$required"
                :guide="$guideText"
                :guide-frame="$guideFrame"
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
                :auto-finish-upload="true"
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
    @endif

    @error($fieldName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error($pagesName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error($pagesName.'.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

    <div x-show="inlineUploading" x-cloak
         class="rounded-xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3 space-y-2">
        <div class="flex items-center justify-between gap-3">
            <p class="inline-flex items-center gap-2 text-sm font-semibold text-brand">
                <span class="size-3.5 rounded-full border-2 border-brand/30 border-t-brand animate-spin" aria-hidden="true"></span>
                <span x-text="inlineProgress === null ? (inlineMessage || @js(__('borrower.document_upload.saving'))) : (inlineMessage || @js(__('borrower.apply.document_saving')))"></span>
            </p>
            <p class="text-sm font-bold tabular-nums text-brand"
               x-show="inlineProgress !== null"
               x-text="(inlineProgress ?? 0) + '%'"></p>
        </div>
        <div class="h-2 rounded-full bg-white overflow-hidden ring-1 ring-brand/10"
             x-show="inlineProgress !== null">
            <div class="h-full bg-brand transition-[width] duration-150"
                 :style="'width:' + (inlineProgress ?? 0) + '%'"></div>
        </div>
    </div>
</div>
