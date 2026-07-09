{{-- Product-specific questions step --}}
<div x-show="stepKey === 'product_questions'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.product_questions')"
        :title="__('borrower.apply.product_questions.title')"
        :subtitle="__('borrower.apply.product_questions.subtitle')"
    />

    @foreach ($productQuestions as $code => $block)
        <div x-show="current && current.code === @js($code)" class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 mb-4">
            <h3 class="text-sm font-bold text-gray-900 mb-4">{{ $block['title'] ?? __('borrower.apply.product_questions.additional') }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach ($block['fields'] as $field)
                    @if (($field['type'] ?? 'text') === 'tz_address')
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">{{ $field['label'] }}</label>
                            <x-site.address-fields
                                form-key="product_question"
                                :prefix="$field['prefix'] ?? ''"
                                :required="$field['required'] ?? true"
                            />
                        </div>
                    @else
                        <div class="{{ ($field['type'] ?? 'text') === 'textarea' ? 'sm:col-span-2' : '' }}">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ $field['label'] }}</label>
                            @if (($field['type'] ?? 'text') === 'select')
                                <select name="product_question[{{ $field['key'] }}]" class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                                    <option value="">{{ __('borrower.profile.select') }}</option>
                                    @foreach ($field['options'] ?? [] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            @elseif (($field['type'] ?? 'text') === 'textarea')
                                <textarea name="product_question[{{ $field['key'] }}]" rows="3" placeholder="{{ $field['placeholder'] ?? '' }}"
                                          class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand"></textarea>
                            @else
                                <input type="text" name="product_question[{{ $field['key'] }}]" placeholder="{{ $field['placeholder'] ?? '' }}"
                                       class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</div>
