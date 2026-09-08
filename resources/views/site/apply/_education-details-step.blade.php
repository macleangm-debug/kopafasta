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
        <div class="space-y-5">
            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 space-y-5">
                @foreach ($elBlock['fields'] as $field)
                    @php
                        $label = ! empty($field['label_key']) ? __($field['label_key']) : ($field['label'] ?? '');
                        $docCode = $field['document_code'] ?? $field['key'] ?? 'admission_fee_letter';
                        $fieldKey = $field['key'] ?? 'field';
                    @endphp
                    @if (($field['type'] ?? 'text') === 'document')
                        @include('site.apply._apply-document-holder', [
                            'docCode' => $docCode,
                            'label' => $label,
                            'required' => ! empty($field['required']),
                            'hint' => __('borrower.apply.education_details.admission_letter_hint'),
                            'hostPrefix' => 'education',
                            'errorKey' => 'admission_letter',
                            'hiddenName' => 'admission_letter_document_id',
                        ])
                    @else
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                {{ $label }}
                                @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                            </label>
                            <input type="text"
                                   name="product_question[{{ $fieldKey }}]"
                                   placeholder="{{ ! empty($field['placeholder_key']) ? __($field['placeholder_key']) : ($field['placeholder'] ?? '') }}"
                                   @if (! empty($field['required'])) required @endif
                                   x-on:input="form.product_question_{{ $fieldKey }} = String($event.target.value || '').trim(); if (educationErrors.{{ $fieldKey }}) educationErrors.{{ $fieldKey }} = ''; scheduleDraftSave();"
                                   :class="educationErrors.{{ $fieldKey }} ? 'ring-rose-300' : 'ring-gray-200'"
                                   class="w-full rounded-xl border-gray-300 ring-1 px-4 py-3 text-sm focus:ring-brand">
                            <p x-show="educationErrors.{{ $fieldKey }}" x-cloak class="mt-1.5 text-sm text-rose-600 font-medium" x-text="educationErrors.{{ $fieldKey }}"></p>
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
                        <input type="text" x-model="institutionPayment.bank_name"
                               x-on:input="institutionPayment.method = 'bank'; institutionPayment.verified = false; if (educationErrors.bank_name) educationErrors.bank_name = ''; scheduleDraftSave()"
                               :class="educationErrors.bank_name ? 'ring-rose-300' : 'ring-gray-200'"
                               class="w-full rounded-xl border-gray-300 ring-1 px-4 py-3 text-sm focus:ring-brand"
                               placeholder="{{ __('borrower.payment_details.bank_name_placeholder') }}">
                        <p x-show="educationErrors.bank_name" x-cloak class="mt-1.5 text-sm text-rose-600 font-medium" x-text="educationErrors.bank_name"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('borrower.payment_details.account_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" x-model="institutionPayment.account_name"
                               x-on:input="institutionPayment.method = 'bank'; institutionPayment.verified = false; if (educationErrors.account_name) educationErrors.account_name = ''; scheduleDraftSave()"
                               :class="educationErrors.account_name ? 'ring-rose-300' : 'ring-gray-200'"
                               class="w-full rounded-xl border-gray-300 ring-1 px-4 py-3 text-sm focus:ring-brand"
                               placeholder="{{ __('borrower.apply.education_details.institution_account_name_placeholder') }}">
                        <p x-show="educationErrors.account_name" x-cloak class="mt-1.5 text-sm text-rose-600 font-medium" x-text="educationErrors.account_name"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('borrower.payment_details.account_number') }} <span class="text-rose-500">*</span></label>
                        <input type="text" x-model="institutionPayment.account_number"
                               x-on:input="institutionPayment.method = 'bank'; institutionPayment.verified = false; if (educationErrors.account_number) educationErrors.account_number = ''; scheduleDraftSave()"
                               :class="educationErrors.account_number ? 'ring-rose-300' : 'ring-gray-200'"
                               class="w-full rounded-xl border-gray-300 ring-1 px-4 py-3 text-sm focus:ring-brand">
                        <p x-show="educationErrors.account_number" x-cloak class="mt-1.5 text-sm text-rose-600 font-medium" x-text="educationErrors.account_number"></p>
                    </div>
                </div>

                <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-100 rounded-xl px-3 py-2">
                    {{ __('borrower.apply.education_details.destination_unverified') }}
                </p>
            </div>
        </div>
    @endif
</div>
