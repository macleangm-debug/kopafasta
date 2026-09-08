{{-- Emergency Details — after verified application fee (product EM or purpose Emergency). --}}
<div x-show="stepKey === 'emergency_details' && ! $data.feeGateOpen" class="p-6 sm:p-8" data-wizard-step="emergency_details">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.emergency_details.eyebrow')"
        :title="__('borrower.apply.emergency_details.title')"
        :subtitle="__('borrower.apply.emergency_details.subtitle')"
    />

    @php
        $emBlock = $productQuestions['EM'] ?? null;
        $typeOptions = __('borrower.apply.emergency_details.types');
        if (! is_array($typeOptions)) {
            $typeOptions = [
                'medical' => 'Medical emergency',
                'funeral' => 'Funeral / bereavement',
                'accident' => 'Accident',
                'education' => 'Urgent school fees',
                'other' => 'Other urgent need',
            ];
        }
    @endphp

    @if ($emBlock)
        <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 space-y-5">
            @foreach ($emBlock['fields'] as $field)
                @php
                    $label = ! empty($field['label_key']) ? __($field['label_key']) : ($field['label'] ?? '');
                    $docCode = $field['document_code'] ?? $field['key'] ?? 'supporting_evidence';
                    $hint = ! empty($field['hint_key']) ? __($field['hint_key']) : '';
                    $options = ! empty($field['options_key']) ? __($field['options_key']) : ($field['options'] ?? []);
                    if (! is_array($options)) {
                        $options = $typeOptions;
                    }
                @endphp
                @if (($field['type'] ?? 'text') === 'document')
                    @include('site.apply._apply-document-holder', [
                        'docCode' => $docCode,
                        'label' => $label,
                        'required' => ! empty($field['required']),
                        'hint' => $hint,
                        'hostPrefix' => 'emergency',
                    ])
                @elseif (($field['type'] ?? 'text') === 'select')
                    <div>
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
                @endif
            @endforeach
        </div>
    @endif
</div>
