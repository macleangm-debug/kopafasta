@props([
    'name' => 'national_id',
    'value' => '',
    'required' => false,
    'readonly' => false,
    'country' => null,
    'help' => null,
])

@php
    $countryCode = $country ?: app(\App\Services\CountrySettingsService::class)->defaultCountryCode();
    $settings = app(\App\Services\CountrySettingsService::class)->forCode($countryCode);
    $groups = \App\Support\NationalIdValidator::groups($countryCode);
    $label = $settings['national_id_label'] ?? 'National ID';
    $help = $help ?: \App\Support\NationalIdValidator::message($countryCode);
    $isReadonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN);
@endphp

<div>
    @if ($groups !== [])
        <x-site.nida-input
            :name="$name"
            :value="$value"
            :required="$required"
            :readonly="$isReadonly"
            :country="$countryCode"
            :groups="$groups"
        />
    @else
        <input
            type="text"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            @if ($isReadonly) readonly @endif
            maxlength="30"
            autocomplete="off"
            placeholder="{{ \App\Support\NationalIdValidator::placeholder($countryCode) }}"
            class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono uppercase {{ $isReadonly ? 'bg-gray-50 text-gray-500' : '' }}"
        >
    @endif
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
