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
        <div x-show="current && current.code === 'EL'" class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 space-y-5">
            @foreach ($elBlock['fields'] as $field)
                @php
                    $label = ! empty($field['label_key']) ? __($field['label_key']) : ($field['label'] ?? '');
                    $docCode = $field['document_code'] ?? $field['key'] ?? 'admission_fee_letter';
                @endphp
                @if (($field['type'] ?? 'text') === 'document')
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold text-gray-700">
                            {{ $label }}
                            @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                        </label>
                        <p class="text-xs text-gray-500">{{ __('borrower.apply.education_details.admission_letter_hint') }}</p>

                        <div x-show="educationDocuments[@js($docCode)]?.customer_document_id" x-cloak
                             class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-emerald-900">{{ __('borrower.document_upload.submitted_short') }}</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-if="educationDocuments[@js($docCode)]?.view_url">
                                    <a :href="educationDocuments[@js($docCode)].view_url" target="_blank" rel="noopener"
                                       class="text-xs font-bold text-brand hover:underline">{{ __('borrower.document_upload.view') }}</a>
                                </template>
                                <label class="text-xs font-bold text-brand hover:underline cursor-pointer">
                                    {{ __('borrower.document_upload.replace') }}
                                    <input type="file" accept="image/*,application/pdf" class="sr-only"
                                           @change="uploadEducationDocument(@js($docCode), $event)">
                                </label>
                                <button type="button" @click="removeEducationDocument(@js($docCode))"
                                        class="text-xs font-bold text-rose-700 hover:underline">{{ __('borrower.document_upload.remove') }}</button>
                            </div>
                        </div>

                        <div x-show="!educationDocuments[@js($docCode)]?.customer_document_id" class="flex flex-wrap items-center gap-3">
                            <label class="inline-flex items-center justify-center bg-white hover:bg-brand-muted/40 text-brand font-bold px-5 py-3 rounded-xl text-sm cursor-pointer shadow-sm ring-1 ring-brand/20">
                                <span>{{ __('borrower.profile.upload') }}</span>
                                <input type="file" accept="image/*,application/pdf" class="sr-only"
                                       @change="uploadEducationDocument(@js($docCode), $event)">
                            </label>
                            <label class="inline-flex items-center justify-center rounded-xl bg-white text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40 px-5 py-3 text-sm font-bold shadow-sm cursor-pointer">
                                <span>{{ __('borrower.document_upload.camera') }}</span>
                                <input type="file" accept="image/*" capture="environment" class="sr-only"
                                       @change="uploadEducationDocument(@js($docCode), $event)">
                            </label>
                            <p x-show="educationDocumentUploading" x-cloak class="text-sm font-semibold text-gray-600 w-full">
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
    @endif
</div>
