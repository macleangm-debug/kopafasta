@props([
    'name' => 'phone',
    'label' => 'Phone',
    'value' => null,
    'required' => false,
    'help' => null,
])

@php
    $split = \App\Support\PhoneNumber::split($value);
    $countries = app(\App\Services\CountrySettingsService::class)->forRegistration();
@endphp

<div @error($name) data-has-error="true" @enderror
     x-data="{
        prefix: @js($split['prefix']),
        local: @js($split['local']),
        countries: @js($countries),
        full() {
            const digits = (this.prefix || '').replace(/\D/g, '') + (this.local || '').replace(/\D/g, '').replace(/^0+/, '');
            return digits || '';
        }
     }">
    @if ($label)
        <label class="block text-xs font-semibold text-gray-700 mb-1">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <div class="flex gap-2">
        <select x-model="prefix" class="w-28 shrink-0 rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500">
            @foreach ($countries as $country)
                <option value="{{ $country['prefix'] }}">{{ $country['emoji'] }} {{ $country['prefix'] }}</option>
            @endforeach
        </select>
        <input type="tel" inputmode="numeric" x-model="local" placeholder="712 345 678"
               @if ($required) required @endif
               class="flex-1 rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500 @error($name) border-red-400 @enderror">
    </div>
    <input type="hidden" name="{{ $name }}" :value="full()">
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @else
        <p class="mt-1 text-xs text-gray-500">Enter the number without a leading zero — we add the country code.</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
