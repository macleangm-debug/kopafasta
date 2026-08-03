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
])

@php
    $pagesName = $pagesFieldName ?? ($fieldName.'_pages');
    $hostId = $inputHostId ?? ($fieldName.'-upload');
    $documentCode = $documentCode ?? $fieldName;
    $removeUrl = $removeUrl ?? ($document ? route('site.borrower.profile.documents.destroy', ['code' => $documentCode]) : null);
    $isPdf = $document && $document->file_path && str_ends_with(strtolower($document->file_path), '.pdf');
    $isImage = $document && $document->file_path && ! $isPdf;
    $meta = $document ? app(\App\Services\ProfileDocumentService::class)->metadata($document) : [];
    $pageCount = (int) ($meta['page_count'] ?? 1);
    $fileName = (string) ($meta['original_name'] ?? ($document?->file_path ? basename($document->file_path) : ''));
    $statusLabel = $document ? app(\App\Services\ProfileDocumentService::class)->statusLabel($document) : '';
    $previewUrl = ($document && $document->file_path) ? asset('storage/'.$document->file_path) : null;
@endphp

<div x-data="{ replaceMode: false }" class="space-y-3">
    @if ($document)
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4">
            <div class="flex items-start gap-4 flex-wrap">
                <div class="shrink-0">
                    @if ($isImage && $previewUrl)
                        <button type="button" onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), 'image')"
                                class="h-24 w-24 rounded-lg ring-1 ring-emerald-200 overflow-hidden bg-white cursor-zoom-in block"
                                title="{{ __('borrower.profile.view_document') }}">
                            <img src="{{ $previewUrl }}" alt="" class="h-full w-full object-cover object-center">
                        </button>
                    @elseif ($isPdf)
                        <button type="button" onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), 'pdf')"
                                class="h-24 w-24 rounded-lg ring-1 ring-emerald-200 bg-white flex flex-col items-center justify-center text-emerald-800 cursor-zoom-in"
                                title="{{ __('borrower.profile.view_document') }}">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span class="text-[10px] font-bold mt-1">PDF</span>
                        </button>
                    @else
                        <div class="h-24 w-24 rounded-lg ring-1 ring-emerald-200 bg-white flex items-center justify-center text-emerald-800 text-xs font-semibold">
                            {{ strtoupper(pathinfo($document->file_path, PATHINFO_EXTENSION) ?: 'FILE') }}
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-emerald-900">{{ $label ?: __('borrower.profile.document_uploaded') }}</p>
                    <dl class="mt-2 space-y-1 text-xs text-emerald-800">
                        @if ($fileName !== '')
                            <div><span class="font-medium">{{ __('borrower.profile.document_file_name') }}:</span> {{ $fileName }}</div>
                        @endif
                        <div><span class="font-medium">{{ __('borrower.profile.uploaded_on') }}</span> {{ $document->created_at?->format('d M Y, H:i') ?? '—' }}</div>
                        @if ($mode === 'multi' && $pageCount > 1)
                            <div><span class="font-medium">{{ __('borrower.profile.document_page_count') }}:</span> {{ $pageCount }}</div>
                        @endif
                        <div><span class="font-medium">{{ __('borrower.profile.document_status_label') }}:</span> {{ $statusLabel }}</div>
                    </dl>
                </div>

                @if ($document->file_path)
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($previewUrl)
                            <button type="button"
                                    onclick="window.kfSiteOpenDocumentPreview(@js($previewUrl), @js($label ?: __('borrower.profile.view_document')), @js($isPdf ? 'pdf' : 'image'))"
                                    class="inline-flex items-center rounded-full bg-white ring-1 ring-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">
                                {{ __('borrower.profile.view_document') }}
                            </button>
                        @endif
                        @unless ($readOnly)
                            <button type="button" @click="replaceMode = true"
                                    class="inline-flex items-center rounded-full bg-white ring-1 ring-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-50">
                                {{ __('borrower.profile.replace_document') }}
                            </button>
                            @if ($removeUrl ?? null)
                                <form method="POST" action="{{ $removeUrl }}" onsubmit="return confirm(@js(__('borrower.profile.remove_document_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center rounded-full bg-white ring-1 ring-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                        {{ __('borrower.profile.remove_document') }}
                                    </button>
                                </form>
                            @endif
                        @endunless
                    </div>
                @endif
            </div>
        </div>
    @endif

    @unless ($readOnly)
    <div @if($document) x-show="replaceMode" x-cloak @endif>
        @if ($mode === 'single')
            <x-site.single-image-document-upload
                :name="$fieldName"
                :input-host-id="$hostId"
                :labels="$labels"
            />
        @else
            <x-site.multi-page-document-upload
                :name="$pagesName"
                :input-host-id="$hostId"
                :labels="$labels"
                :required="$required"
            />
            <p class="text-xs text-gray-400 mt-3">{{ __('borrower.profile.or_upload_pdf') }}</p>
            <input type="file" name="{{ $fieldName }}" accept=".jpg,.jpeg,.png,.pdf"
                   class="mt-2 w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-800 file:font-semibold">
        @endif
        @if ($document)
            <button type="button" @click="replaceMode = false" class="mt-3 text-sm font-semibold text-gray-500 hover:text-gray-700">
                {{ __('borrower.profile.cancel_update') }}
            </button>
        @endif
    </div>
    @endunless

    @error($fieldName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error($pagesName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error($pagesName.'.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
</div>
