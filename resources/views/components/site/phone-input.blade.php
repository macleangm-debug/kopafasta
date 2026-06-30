@props([
    'name' => 'phone',
    'label' => 'Phone',
    'value' => null,
    'required' => false,
    'help' => null,
    'variant' => 'default',
    'id' => null,
    'inputClass' => null,
    'selectClass' => null,
])

@php
    $split = \App\Support\PhoneNumber::split($value);
    $countries = app(\App\Services\CountrySettingsService::class)->forRegistration();
    $selectClass = $selectClass ?? ($variant === 'rounded'
        ? 'w-28 shrink-0 px-3.5 py-3 rounded-xl bg-white border border-gray-300 text-sm outline-none transition focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10'
        : 'w-28 shrink-0 rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500');
    $inputClass = $inputClass ?? ($variant === 'rounded'
        ? 'flex-1 px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-sm outline-none transition'
        : 'flex-1 rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500');
    $labelClass = $variant === 'rounded'
        ? 'block text-sm font-medium text-gray-700 mb-1.5'
        : 'block text-xs font-medium text-gray-600 mb-1';
@endphp

<div @error($name) data-has-error="true" @enderror
     @if ($id) id="{{ $id }}" @endif
     x-data="{
        prefix: @js($split['prefix']),
        local: @js($split['local']),
        full() {
            const digits = (this.prefix || '').replace(/\D/g, '') + (this.local || '').replace(/\D/g, '').replace(/^0+/, '');
            return digits || '';
        }
     }">
    @if ($label)
        <label class="{{ $labelClass }}">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <div class="flex gap-2">
        <select x-model="prefix" class="{{ $selectClass }}">
            @foreach ($countries as $country)
                <option value="{{ $country['prefix'] }}">{{ $country['emoji'] }} {{ $country['prefix'] }}</option>
            @endforeach
        </select>
        <input type="tel" inputmode="numeric" x-model="local" placeholder="712 345 678"
               @if ($required) required @endif
               class="{{ $inputClass }} @error($name) border-red-400 @enderror">
    </div>
    <input type="hidden" name="{{ $name }}" :value="full()">
    @if ($help)
        <p class="mt-1.5 text-xs text-gray-500">{{ $help }}</p>
    @else
        <p class="mt-1.5 text-xs text-gray-500">Enter your number without the leading zero — we add the country code automatically.</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
