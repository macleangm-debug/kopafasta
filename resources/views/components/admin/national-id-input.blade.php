@props([
    'name' => 'national_id',
    'label' => null,
    'value' => null,
    'required' => false,
    'help' => null,
    'country' => null,
])

@php
    $countryCode = $country ?: app(\App\Services\CountrySettingsService::class)->defaultCountryCode();
    $settings = app(\App\Services\CountrySettingsService::class)->forCode($countryCode);
    $label = $label ?: ($settings['national_id_label'] ?? 'National ID');
@endphp

<div @error($name) data-has-error="true" @enderror>
    @if ($label)
        <label class="block text-xs font-semibold text-gray-700 mb-1">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <x-site.national-id-input
        :name="$name"
        :value="$value"
        :required="$required"
        :country="$countryCode"
        :help="$help"
    />
</div>
