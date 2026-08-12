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

@if (session('status'))
    <div
        x-data
        x-init="
            $nextTick(() => window.showBorrowerFeedback && window.showBorrowerFeedback({
                title: @js(__('borrower.feedback.saved_title')),
                message: @js(session('status')),
                tone: 'success',
            }));
        "
        class="sr-only"
        aria-hidden="true"
    ></div>
@endif

<div class="grid lg:grid-cols-3 gap-6">
    @if ($canUpload)
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 h-fit"
             x-data="{ docType: '', customLabel: '', uploading: false, types: @js($documentTypes) }">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_account.upload_new') }}</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1 mb-4">{{ __('site.partner_account.add_document') }}</h2>
            <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="space-y-3"
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
                    <p class="mt-2 text-xs text-gray-500">{{ __('borrower.profile.or_take_picture_hint') }}</p>
                </div>
                <button type="submit" :disabled="!docType"
                        class="w-full rounded-xl bg-brand-gold hover:brightness-95 disabled:opacity-50 text-brand text-sm font-bold py-2.5">
                    {{ __('site.partner_account.upload') }}
                </button>
            </form>
            <p class="text-xs text-gray-500 mt-3">{{ __('site.partner_account.docs_admin_hint') }}</p>
            <x-site.upload-busy-overlay />
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
                    <li class="py-3.5 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $doc->label }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                @if ($doc->task ?? null){{ __('site.partner_account.task_ref', ['id' => $doc->task->id]) }} · @endif
                                {{ $doc->created_at?->diffForHumans() }}
                            </p>
                        </div>
                        <x-site.document-view-button :url="asset('storage/'.$doc->file_path)" :label="__('site.partner_account.view')" class="text-brand hover:underline text-xs font-semibold shrink-0" />
                    </li>
                @endforeach
            </ul>
            @if (method_exists($documents, 'links'))
                <div class="mt-4">{{ $documents->links() }}</div>
            @endif
        @endif
    </div>
</div>
