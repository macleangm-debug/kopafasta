@props([
    'fields' => [],
    'details' => [],
    'skipTypes' => [],
])

@foreach ($fields as $typeKey => $typeFields)
    @continue(in_array($typeKey, $skipTypes, true))
    @foreach ($typeFields as $field)
        @php
            $kind = $field['type'] ?? 'text';
            $key = $field['key'] ?? '';
        @endphp
        @continue($key === '' || in_array($kind, ['document', 'region', 'district'], true))
        <div class="sm:col-span-2"
             data-kf-activity-typed-field
             x-show="activityType === @js($typeKey)"
             x-cloak
             x-effect="syncTypedFieldEnabled($el, @js($typeKey))">
            @if ($kind === 'select')
                <x-site.profile-select
                    :name="'activity_details['.$key.']'"
                    :label="$field['label'] ?? $key"
                    :options="$field['options'] ?? []"
                    :value="old('activity_details.'.$key, $details[$key] ?? '')"
                    :required="(bool) ($field['required'] ?? false)"
                    :placeholder="__('borrower.profile.select_option')"
                />
            @else
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    {{ $field['label'] ?? $key }}
                    @if (! empty($field['required']))<span class="text-red-500">*</span>@endif
                </label>
                <input type="text"
                       name="activity_details[{{ $key }}]"
                       value="{{ old('activity_details.'.$key, $details[$key] ?? '') }}"
                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                       placeholder="{{ $field['placeholder'] ?? '' }}"
                       autocomplete="off"
                       autocorrect="off"
                       autocapitalize="off"
                       spellcheck="false"
                       data-lpignore="true"
                       data-1p-ignore="true"
                       data-form-type="other"
                       @if (! empty($field['required'])) required @endif>
            @endif
        </div>
    @endforeach
@endforeach
