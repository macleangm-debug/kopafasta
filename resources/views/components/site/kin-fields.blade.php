@props([
    'customer',
    'inputClass' => 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm',
    'required' => true,
])

@php
    $relationships = kin_relationship_options();
    $first = old('nok_first_name', $customer->nok_first_name);
    $middle = old('nok_middle_name', $customer->nok_middle_name);
    $last = old('nok_last_name', $customer->nok_last_name);

    if (! filled($first) && ! filled($last) && filled($customer->nok_name)) {
        $parts = preg_split('/\s+/', trim((string) $customer->nok_name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? array_pop($parts) : '';
        array_shift($parts);
        $middle = implode(' ', $parts);
    }
@endphp

{{-- Same primitives as Contact/Activity: named fields + profile-select + canonical phone-input. --}}
<div class="space-y-4">
    <div class="grid sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.first_name') }} @if($required)<span class="text-red-500">*</span>@endif</label>
            <input name="nok_first_name" value="{{ $first }}" @if($required) required @endif class="{{ $inputClass }}" autocomplete="off">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.middle_name') }}</label>
            <input name="nok_middle_name" value="{{ $middle }}" class="{{ $inputClass }}" autocomplete="off">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.last_name') }} @if($required)<span class="text-red-500">*</span>@endif</label>
            <input name="nok_last_name" value="{{ $last }}" @if($required) required @endif class="{{ $inputClass }}" autocomplete="off">
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <x-site.profile-select
                name="nok_relationship"
                :label="__('borrower.profile.fields.relationship')"
                :options="$relationships"
                :value="old('nok_relationship', $customer->nok_relationship)"
                :required="$required"
                :placeholder="__('borrower.profile.select_relationship')"
                :select-class="$inputClass"
            />
        </div>
        <div>
            <x-site.phone-input
                name="nok_phone"
                :label="__('borrower.profile.fields.phone')"
                :value="old('nok_phone', $customer->nok_phone)"
                :required="$required"
                :allow-country-change="true"
                :input-class="$inputClass"
                :help="null"
            />
        </div>
    </div>
</div>
