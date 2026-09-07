{{-- Product-specific questions step (post-fee when fold_into is product_questions). --}}
<div x-show="stepKey === 'product_questions' && ! $data.feeGateOpen" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.product_questions')"
        :title="__('borrower.apply.product_questions.title')"
        :subtitle="__('borrower.apply.product_questions.subtitle')"
    />

    @foreach ($productQuestions as $code => $block)
        @continue(($block['fold_into'] ?? 'quote') !== 'product_questions')
        <div x-show="current && current.code === @js($code)" class="glass-card p-5 sm:p-6 ring-1 ring-brand/10 mb-4">
            <h3 class="text-sm font-bold text-gray-900 mb-4">{{ ! empty($block['title_key']) ? __($block['title_key']) : ($block['title'] ?? __('borrower.apply.product_questions.additional')) }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach ($block['fields'] as $field)
                    @php $label = ! empty($field['label_key']) ? __($field['label_key']) : ($field['label'] ?? ''); @endphp
                    @if (($field['type'] ?? 'text') === 'tz_address')
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">{{ $label }}</label>
                            <x-site.address-fields
                                form-key="product_question"
                                :prefix="$field['prefix'] ?? ''"
                                :required="$field['required'] ?? true"
                            />
                        </div>
                    @elseif (($field['type'] ?? 'text') === 'document')
                        {{-- Document fields live on dedicated post-fee steps (e.g. Education Details). --}}
                    @else
                        <div class="{{ ($field['type'] ?? 'text') === 'textarea' ? 'sm:col-span-2' : '' }}">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ $label }}</label>
                            @if (($field['type'] ?? 'text') === 'select')
                                <x-site.profile-select
                                    :name="'product_question['.$field['key'].']'"
                                    :options="$field['options'] ?? []"
                                    :required="$field['required'] ?? false"
                                    :placeholder="__('borrower.profile.select')"
                                    select-class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand"
                                />
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
