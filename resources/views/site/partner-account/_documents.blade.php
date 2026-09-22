@props([
    'documents',
    'uploadRoute',
    'canUpload' => true,
    'documentTypes' => null,
])

@php
    $documentTypes = $documentTypes ?? [
        'business_license' => __('site.partner_account.doc_types.business_license'),
        'tin_certificate' => __('site.partner_account.doc_types.tin_certificate'),
        'vat_certificate' => __('site.partner_account.doc_types.vat_certificate'),
        'registration' => __('site.partner_account.doc_types.registration'),
        'other' => __('site.partner_account.doc_types.other'),
    ];
@endphp

<p class="text-sm text-gray-600 mb-4">{{ __('site.partner_account.docs_page_intro') }}</p>
<div class="grid lg:grid-cols-3 gap-6">
    @if ($canUpload)
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 h-fit"
             x-data="{ docType: '', customLabel: '', uploading: false, types: @js($documentTypes) }">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_account.upload_new') }}</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1 mb-4">{{ __('site.partner_account.add_document') }}</h2>
            <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="space-y-3"
                  data-inline-document-progress data-saving-message="{{ __('borrower.profile.uploading_documents') }}"
                  @submit="
                      if (!docType) { $event.preventDefault(); return; }
                      const labelInput = $el.querySelector('[data-doc-label]');
                      if (labelInput) labelInput.value = docType === 'other' ? customLabel : (types[docType] || docType);
                      uploading = true;
                  ">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.doc_type') }}</label>
                    <select x-model="docType" required
                            class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand bg-white">
                        <option value="">{{ __('site.partner_account.doc_type_placeholder') }}</option>
                        @foreach ($documentTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="docType === 'other'" x-cloak>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.doc_label') }}</label>
                    <input x-model="customLabel" maxlength="80"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand"
                           placeholder="{{ __('site.partner_account.doc_label_placeholder') }}">
                </div>
                <input type="hidden" name="label" value="" data-doc-label>
                <input type="hidden" name="doc_type" :value="docType">
                <div x-show="docType" x-cloak>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.doc_file') }}</label>
                    <x-site.single-image-document-upload name="file" facing="environment" :required="false" />
                </div>
                <button type="submit" :disabled="!docType"
                        class="w-full rounded-xl bg-brand-gold hover:brightness-95 disabled:opacity-50 text-brand text-sm font-bold py-2.5">
                    {{ __('site.partner_account.upload') }}
                </button>
            </form>
            <p class="text-xs text-gray-500 mt-3">{{ __('site.partner_account.docs_admin_hint') }}</p>
        </div>
    @endif

    <div @class(['glass-card rounded-2xl ring-1 ring-brand/10 p-5', 'lg:col-span-2' => $canUpload, 'lg:col-span-3' => ! $canUpload])>
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_account.my_documents') }}</p>
        <h2 class="text-lg font-bold text-gray-900 mt-1 mb-4">{{ __('site.partner_account.uploaded_files') }}</h2>
        @if ($documents->isEmpty())
            <p class="text-sm text-gray-500 py-8 text-center">{{ __('site.partner_account.no_documents') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($documents as $doc)
                    @php
                        $previewUrl = asset('storage/'.$doc->file_path);
                        $isPdf = str_ends_with(strtolower((string) $doc->file_path), '.pdf');
                        $docTypeKey = (string) ($doc->doc_type ?? '');
                    @endphp
                    <li class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $doc->label }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                @if ($doc->task ?? null){{ __('site.partner_account.task_ref', ['id' => $doc->task->id]) }} · @endif
                                {{ $doc->created_at?->format('d M Y') ?? $doc->created_at?->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 flex-wrap">
                            <button type="button"
                                    onclick="window.kfSiteOpenDocumentPreview?.(@js($previewUrl), @js($doc->label), @js($isPdf ? 'pdf' : 'image'))"
                                    class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                                {{ __('site.partner_account.view') }}
                            </button>
                            @if ($canUpload && $docTypeKey !== '')
                                <button type="button"
                                        @click="
                                            const root = $el.closest('.grid')?.querySelector('[x-data]');
                                            if (root && window.Alpine?.\$data) {
                                                window.Alpine.\$data(root).docType = @js($docTypeKey);
                                            }
                                            root?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                        "
                                        class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                                    {{ __('borrower.profile.replace_document') }}
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if (method_exists($documents, 'links'))
                <div class="mt-4">{{ $documents->links() }}</div>
            @endif
        @endif
    </div>
</div>
