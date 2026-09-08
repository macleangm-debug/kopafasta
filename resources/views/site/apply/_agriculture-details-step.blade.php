{{-- Agriculture Details — after verified application fee (product KB/AG or purpose Agriculture). --}}
<div x-show="stepKey === 'agriculture_details' && ! $data.feeGateOpen" class="p-6 sm:p-8" data-wizard-step="agriculture_details">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.agriculture_details.eyebrow')"
        :title="__('borrower.apply.agriculture_details.title')"
        :subtitle="__('borrower.apply.agriculture_details.subtitle')"
    />

    @php
        $agBlock = $productQuestions['AG'] ?? null;
    @endphp

    @if ($agBlock)
        <div class="space-y-5">
            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 space-y-5">
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($agBlock['fields'] as $field)
                        @php
                            $label = ! empty($field['label_key']) ? __($field['label_key']) : ($field['label'] ?? '');
                            $type = $field['type'] ?? 'text';
                            $docCode = $field['document_code'] ?? $field['key'] ?? 'document';
                            $hint = ! empty($field['hint_key']) ? __($field['hint_key']) : '';
                            $options = ! empty($field['options_key']) ? __($field['options_key']) : ($field['options'] ?? []);
                            if (! is_array($options)) {
                                $options = [];
                            }
                        @endphp
                        @if ($type === 'document')
                            @include('site.apply._apply-document-holder', [
                                'docCode' => $docCode,
                                'label' => $label,
                                'required' => ! empty($field['required']),
                                'hint' => $hint,
                                'hostPrefix' => 'agriculture',
                            ])
                        @elseif ($type === 'select')
                            <div class="sm:col-span-2">
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
                        @elseif ($type === 'date')
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    {{ $label }}
                                    @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                                </label>
                                <input type="date"
                                       name="product_question[{{ $field['key'] }}]"
                                       @if (! empty($field['required'])) required @endif
                                       x-on:input="scheduleDraftSave()"
                                       class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                            </div>
                        @elseif ($type === 'number')
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    {{ $label }}
                                    @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                                </label>
                                <input type="number" min="0" step="1000"
                                       name="product_question[{{ $field['key'] }}]"
                                       @if (! empty($field['required'])) required @endif
                                       x-on:input="scheduleDraftSave()"
                                       class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                            </div>
                        @else
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    {{ $label }}
                                    @if (! empty($field['required'])) <span class="text-rose-500">*</span> @endif
                                </label>
                                <input type="text"
                                       name="product_question[{{ $field['key'] }}]"
                                       placeholder="{{ ! empty($field['placeholder_key']) ? __($field['placeholder_key']) : '' }}"
                                       @if (! empty($field['required'])) required @endif
                                       x-on:input="scheduleDraftSave()"
                                       class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            <p class="text-xs text-gray-500">{{ __('borrower.apply.agriculture_details.screening_note') }}</p>
        </div>
    @endif
</div>
