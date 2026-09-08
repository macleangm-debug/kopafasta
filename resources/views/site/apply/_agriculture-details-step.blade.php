{{-- Agriculture Details — Overview + Documents tabs (after verified application fee). --}}
<div x-show="stepKey === 'agriculture_details' && ! $data.feeGateOpen" class="p-6 sm:p-8" data-wizard-step="agriculture_details"
     x-data="{
        agroTab: 'overview',
        requiredDocCodes: ['farm_activity_photos', 'land_use_evidence'],
        docDone() {
            return this.requiredDocCodes.filter((code) => !!educationDocuments[code]?.customer_document_id).length;
        },
     }">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.agriculture_details.eyebrow')"
        :title="__('borrower.apply.agriculture_details.title')"
        :subtitle="__('borrower.apply.agriculture_details.subtitle')"
    />

    @php
        $agBlock = $productQuestions['AG'] ?? null;
        $overviewFields = collect($agBlock['fields'] ?? [])->reject(fn ($f) => ($f['type'] ?? '') === 'document')->values();
        $documentFields = collect($agBlock['fields'] ?? [])->filter(fn ($f) => ($f['type'] ?? '') === 'document')->values();
        $budgetRangeOptions = agriculture_budget_range_options();
        $salesRangeOptions = agriculture_sales_range_options();
    @endphp

    @if ($agBlock)
        <div class="space-y-5">
            <div class="flex gap-1 rounded-xl bg-gray-100/80 p-1 ring-1 ring-gray-200/80">
                <button type="button" @click="agroTab = 'overview'"
                        class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition"
                        :class="agroTab === 'overview' ? 'bg-white text-brand shadow-sm ring-1 ring-brand/10' : 'text-gray-600 hover:text-gray-900'">
                    {{ __('borrower.apply.agriculture_details.tab_overview') }}
                </button>
                <button type="button" @click="agroTab = 'documents'"
                        class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition"
                        :class="agroTab === 'documents' ? 'bg-white text-brand shadow-sm ring-1 ring-brand/10' : 'text-gray-600 hover:text-gray-900'">
                    {{ __('borrower.apply.agriculture_details.tab_documents') }}
                </button>
            </div>

            <div x-show="agroTab === 'overview'" x-cloak class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 space-y-5">
                <div class="grid sm:grid-cols-2 gap-4 sm:gap-x-5 sm:gap-y-5 sm:items-start">
                    @foreach ($overviewFields as $field)
                        @php
                            $label = ! empty($field['label_key']) ? __($field['label_key']) : ($field['label'] ?? '');
                            $type = $field['type'] ?? 'text';
                            $options = ! empty($field['options_key']) ? __($field['options_key']) : ($field['options'] ?? []);
                            if (! is_array($options)) {
                                $options = [];
                            }
                            if ($type === 'budget_range') {
                                $options = $budgetRangeOptions;
                            } elseif ($type === 'sales_range' || ($type === 'income_range' && ($field['key'] ?? '') === 'expected_revenue')) {
                                $options = $salesRangeOptions;
                                $type = 'sales_range';
                            }
                            $isHalf = in_array($type, ['date', 'budget_range', 'sales_range'], true)
                                || (($type === 'select') && in_array($field['key'] ?? '', ['production_stage'], true));
                        @endphp
                        @if (in_array($type, ['select', 'budget_range', 'sales_range'], true))
                            <div @class(['sm:col-span-2' => ! $isHalf, 'min-w-0' => true])>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    {{ $label }}
                                    @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                                </label>
                                <x-site.profile-select
                                    :name="'product_question['.$field['key'].']'"
                                    :options="$options"
                                    :required="$field['required'] ?? false"
                                    :placeholder="__('borrower.profile.select')"
                                    select-class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand"
                                />
                            </div>
                        @elseif ($type === 'location')
                            <div class="sm:col-span-2 space-y-3 min-w-0"
                                 x-data="{
                                    syncFarmLocation() {
                                        const root = $el;
                                        const val = (name) => root.querySelector(`[name=\"${name}\"]`)?.value?.trim() || '';
                                        const composed = [val('product_question[farming_region]'), val('product_question[farming_district]'), val('product_question[farming_ward]')].filter(Boolean).join(', ');
                                        const hidden = root.querySelector('[name=\"product_question[farming_location]\"]');
                                        if (hidden) hidden.value = composed;
                                        scheduleDraftSave();
                                    }
                                 }"
                                 @change="syncFarmLocation()">
                                <label class="block text-sm font-semibold text-gray-700">
                                    {{ $label }}
                                    @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                                </label>
                                <x-site.address-fields
                                    form-key="product_question"
                                    prefix="farming"
                                    :required="! empty($field['required'])"
                                />
                                <input type="hidden" name="product_question[farming_location]" value="">
                            </div>
                        @elseif ($type === 'date')
                            <div class="min-w-0">
                                <x-site.date-input
                                    :name="'product_question['.$field['key'].']'"
                                    :label="$label"
                                    :required="! empty($field['required'])"
                                    :min="now()->subYear()->format('Y-m-d')"
                                    :max="now()->addYears(5)->format('Y-m-d')"
                                    :default="now()->addMonths(3)->format('Y-m-d')"
                                />
                            </div>
                        @else
                            <div class="sm:col-span-2 min-w-0">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    {{ $label }}
                                    @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                                </label>
                                <input type="text"
                                       name="product_question[{{ $field['key'] }}]"
                                       @if (! empty($field['required'])) required @endif
                                       x-on:input="scheduleDraftSave()"
                                       class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div x-show="agroTab === 'documents'" x-cloak class="space-y-4">
                <p class="text-sm font-semibold text-gray-700"
                   x-text="@js(__('borrower.apply.agriculture_details.documents_progress')).replace(':done', String(docDone())).replace(':total', String(requiredDocCodes.length))"></p>

                <div class="space-y-3">
                    @foreach ($documentFields as $field)
                        @php
                            $label = ! empty($field['label_key']) ? __($field['label_key']) : ($field['label'] ?? '');
                            $docCode = $field['document_code'] ?? $field['key'] ?? 'document';
                            $hint = ! empty($field['hint_key']) ? __($field['hint_key']) : '';
                            $multiPage = ($field['capture'] ?? '') === 'multi_page';
                        @endphp
                        @include('site.apply._apply-document-holder', [
                            'docCode' => $docCode,
                            'label' => $label,
                            'required' => ! empty($field['required']),
                            'hint' => $hint,
                            'hostPrefix' => 'agriculture',
                            'multiPage' => $multiPage,
                            'guideCompact' => true,
                        ])
                    @endforeach
                </div>
                <p x-show="docDone() < requiredDocCodes.length" x-cloak class="text-xs text-rose-600">
                    {{ __('borrower.apply.agriculture_details.docs_required_incomplete') }}
                </p>
            </div>

            <p class="text-xs text-gray-500">{{ __('borrower.apply.agriculture_details.screening_note') }}</p>
        </div>
    @endif
</div>
