{{-- Education Loan details — after verified application fee. --}}
<div x-show="stepKey === 'education_details' && ! $data.feeGateOpen" class="p-6 sm:p-8" data-wizard-step="education_details">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.education_details.eyebrow')"
        :title="__('borrower.apply.education_details.title')"
        :subtitle="__('borrower.apply.education_details.subtitle')"
    />

    @php
        $elBlock = $productQuestions['EL'] ?? null;
    @endphp

    @if ($elBlock)
        <div x-show="current && current.code === 'EL'" class="space-y-5">
            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 space-y-5">
                @foreach ($elBlock['fields'] as $field)
                    @php
                        $label = ! empty($field['label_key']) ? __($field['label_key']) : ($field['label'] ?? '');
                        $docCode = $field['document_code'] ?? $field['key'] ?? 'admission_fee_letter';
                    @endphp
                    @if (($field['type'] ?? 'text') === 'document')
                        <div class="space-y-3" x-data="{ replaceMode: false }">
                            <label class="block text-sm font-semibold text-gray-700">
                                {{ $label }}
                                @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                            </label>
                            <p class="text-xs text-gray-500">{{ __('borrower.apply.education_details.admission_letter_hint') }}</p>

                            {{-- Canonical Document Holder (same presentation as profile-document-field) --}}
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
                                    if ($event.detail?.hostId === @js('education-'.$docCode.'-upload') && $event.detail?.file) {
                                        uploadEducationDocument(@js($docCode), { target: { files: [$event.detail.file], value: '' } })
                                            .then(() => { replaceMode = false; });
                                    }
                                 ">
                                <x-site.single-image-document-upload
                                    :name="'education_'.$docCode"
                                    :input-host-id="'education-'.$docCode.'-upload'"
                                    facing="environment"
                                    :required="empty($field['required']) ? false : true"
                                />
                                <button type="button"
                                        x-show="replaceMode && educationDocuments[@js($docCode)]?.customer_document_id"
                                        x-cloak
                                        @click="replaceMode = false"
                                        class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                                    {{ __('borrower.profile.cancel_update') }}
                                </button>
                                <p x-show="educationDocumentUploading" x-cloak class="text-sm font-semibold text-gray-600">
                                    {{ __('borrower.profile.uploading_documents') }}
                                </p>
                            </div>

                            <input type="hidden"
                                   name="product_question[admission_letter_document_id]"
                                   :value="educationDocuments[@js($docCode)]?.customer_document_id || ''"
                                   data-education-doc-required="1">
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                {{ $label }}
                                @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                            </label>
                            <input type="text"
                                   name="product_question[{{ $field['key'] }}]"
                                   placeholder="{{ ! empty($field['placeholder_key']) ? __($field['placeholder_key']) : ($field['placeholder'] ?? '') }}"
                                   @if (! empty($field['required'])) required @endif
                                   @input="scheduleDraftSave()"
                                   class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 space-y-4"
                 x-init="institutionPayment.method = 'bank'; institutionPayment.verified = false">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">{{ __('borrower.apply.education_details.destination_title') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('borrower.apply.education_details.destination_hint') }}</p>
                    <p class="mt-2 text-xs font-semibold text-brand">{{ __('borrower.apply.education_details.destination_verify_note') }}</p>
                </div>

                <p class="text-sm font-semibold text-gray-800">{{ __('borrower.payment_details.method_bank') }}</p>
                <input type="hidden" x-model="institutionPayment.method" value="bank">

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('borrower.payment_details.bank_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" x-model="institutionPayment.bank_name" @input="institutionPayment.method = 'bank'; institutionPayment.verified = false; scheduleDraftSave()"
                               class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand"
                               placeholder="{{ __('borrower.payment_details.bank_name_placeholder') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('borrower.payment_details.account_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" x-model="institutionPayment.account_name" @input="institutionPayment.method = 'bank'; institutionPayment.verified = false; scheduleDraftSave()"
                               class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand"
                               placeholder="{{ __('borrower.apply.education_details.institution_account_name_placeholder') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('borrower.payment_details.account_number') }} <span class="text-rose-500">*</span></label>
                        <input type="text" x-model="institutionPayment.account_number" @input="institutionPayment.method = 'bank'; institutionPayment.verified = false; scheduleDraftSave()"
                               class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                    </div>
                </div>

                <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-100 rounded-xl px-3 py-2">
                    {{ __('borrower.apply.education_details.destination_unverified') }}
                </p>
            </div>
        </div>
    @endif
</div>
