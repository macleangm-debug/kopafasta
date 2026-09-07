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
@endphp

<div x-data="{ replaceMode: false }" class="space-y-3">
    @if ($document)
        <div @class([
            'rounded-xl p-4 ring-1',
            'bg-amber-50 ring-amber-200' => $needsUpdate,
            'bg-emerald-50 ring-emerald-200' => ! $needsUpdate,
        ])>
            <div class="flex flex-col sm:flex-row sm:items-start gap-3 sm:gap-4">
                <div class="flex items-start gap-3 min-w-0 flex-1">
                    <div class="shrink-0">
                        @if ($isImage && $previewUrl)
                            <button type="button" onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), 'image')"
                                    class="h-16 w-16 sm:h-24 sm:w-24 rounded-lg ring-1 ring-emerald-200 overflow-hidden bg-white cursor-zoom-in block"
                                    title="{{ __('borrower.profile.view_document') }}">
                                <img src="{{ $previewUrl }}" alt="" class="h-full w-full object-cover object-center">
                            </button>
                        @elseif ($isPdf)
                            <button type="button" onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), 'pdf')"
                                    class="h-16 w-16 sm:h-24 sm:w-24 rounded-lg ring-1 ring-emerald-200 bg-white flex flex-col items-center justify-center text-emerald-800 cursor-zoom-in"
                                    title="{{ __('borrower.profile.view_document') }}">
                                <svg class="h-8 w-8 sm:h-10 sm:w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[10px] font-bold mt-0.5 sm:mt-1">PDF</span>
                            </button>
                        @else
                            <div class="h-16 w-16 sm:h-24 sm:w-24 rounded-lg ring-1 ring-emerald-200 bg-white flex items-center justify-center text-emerald-800 text-xs font-semibold">
                                {{ $fileExt }}
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-emerald-900 truncate {{ $nested ? 'hidden sm:block' : '' }}">{{ $label ?: __('borrower.profile.document_uploaded') }}</p>
                        <p class="mt-1 sm:hidden text-xs text-emerald-800">
                            <span class="font-semibold">{{ $statusLabel }}</span>
                            <span class="text-emerald-700/80"> · {{ $document->created_at?->format('d M Y') ?? '—' }}</span>
                            @if ($requiresExpiry && $expiresAt)
                                <span class="text-emerald-700/80"> · {{ $expiresAt->format('d M Y') }}</span>
                            @endif
                        </p>
                        <dl class="hidden sm:block mt-2 space-y-1 text-xs text-emerald-800">
                            @if ($fileName !== '')
                                <div class="truncate" title="{{ $fileName }}">
                                    <span class="font-medium">{{ __('borrower.profile.document_file_name') }}:</span>
                                    {{ $fileName }}
                                </div>
                            @endif
                            <div><span class="font-medium">{{ __('borrower.profile.uploaded_on') }}</span> {{ $document->created_at?->format('d M Y, H:i') ?? '—' }}</div>
                            @if ($mode === 'multi' && $pageCount > 1)
                                <div><span class="font-medium">{{ __('borrower.profile.document_page_count') }}:</span> {{ $pageCount }}</div>
                            @endif
                            <div><span class="font-medium">{{ __('borrower.profile.document_status_label') }}:</span> {{ $statusLabel }}</div>
                            @if ($requiresExpiry && $expiresAt)
                                <div>
                                    <span class="font-medium">{{ __('borrower.profile.valid_until') }}</span>
                                    {{ $expiresAt->format('d M Y') }}
                                </div>
                            @endif
                            @if ($needsUpdate)
                                <div class="font-bold text-amber-900">{{ __('borrower.documents_page.status_expired') }}</div>
                            @endif
                        </dl>
                    </div>
                </div>

                @if ($document->file_path)
                    <div class="flex items-center gap-2 shrink-0 flex-wrap w-full sm:w-auto">
                        @if ($previewUrl)
                            <button type="button"
                                    onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), @js($isPdf ? 'pdf' : 'image'))"
                                    class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                                {{ __('borrower.profile.view_document') }}
                            </button>
                        @endif
                        @if ($allowReplace && ($replaceOpensEdit || ! $readOnly))
                            <button type="button"
                                    @click="{{ $replaceOpensEdit ? 'open = true' : 'replaceMode = true' }}"
                                    class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                                {{ __('borrower.profile.replace_document') }}
                            </button>
                        @endif
                        @if ($allowRemove && ($removeUrl ?? null))
                            <button type="button"
                                    class="inline-flex items-center rounded-full bg-white ring-1 ring-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                    @click="
                                        if (! confirm(@js(__('borrower.profile.remove_document_confirm')))) return;
                                        const f = document.createElement('form');
                                        f.method = 'POST';
                                        f.action = @js($removeUrl);
                                        f.style.display = 'none';
                                        const token = document.createElement('input');
                                        token.name = '_token';
                                        token.value = @js(csrf_token());
                                        f.appendChild(token);
                                        const method = document.createElement('input');
                                        method.name = '_method';
                                        method.value = 'DELETE';
                                        f.appendChild(method);
                                        document.body.appendChild(f);
                                        f.submit();
                                    ">
                                {{ __('borrower.profile.remove_document') }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @elseif ($readOnly)
        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
            <p class="text-sm font-semibold text-gray-900">{{ $label ?: __('borrower.profile.document_uploaded') }}</p>
            <p class="text-sm font-semibold text-amber-700 mt-1">{{ __('borrower.profile.missing') }}</p>
            @if ($allowReplace && $replaceOpensEdit)
                <button type="button" @click="open = true"
                        class="mt-3 inline-flex items-center justify-center rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 text-sm shadow-sm">
                    {{ __('borrower.documents_page.add_document') }}
                </button>
            @endif
        </div>
    @endif

    @unless ($readOnly || $replaceOpensEdit)
    <div @if($document) x-show="replaceMode" x-cloak @endif class="space-y-3">
        @if ($mode === 'single')
            <x-site.single-image-document-upload
                :name="$fieldName"
                :input-host-id="$hostId"
                :labels="$labels"
                facing="environment"
                :required="$required"
            />
        @else
            <x-site.multi-page-document-upload
                :name="$pagesName"
                :input-host-id="$hostId"
                :labels="$labels"
                :required="$required"
            />
        @endif

        @if ($requiresExpiry)
            <div>
                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.profile.expiry_date') }} <span class="text-red-500">*</span></label>
                <input type="date" name="{{ $expiresField }}" value="{{ old($expiresField, $expiresAt?->format('Y-m-d')) }}"
                       class="kf-field max-w-xs" @if($required || $document) required @endif>
                @error($expiresField)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        @if ($document)
            <button type="button" @click="replaceMode = false" class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                {{ __('borrower.profile.cancel_update') }}
            </button>
        @endif
    </div>
    @endunless

    @error($fieldName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error($pagesName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error($pagesName.'.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
</div>
