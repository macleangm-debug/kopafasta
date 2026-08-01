@props([
    'name' => 'phone',
    'label' => 'Phone',
    'value' => null,
    'required' => false,
    'requiredWhen' => null,
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
     data-phone-input
     x-data="{
        prefix: @js($split['prefix']),
        local: @js($split['local']),
        full() {
            const digits = (this.prefix || '').replace(/\D/g, '') + (this.local || '').replace(/\D/g, '').replace(/^0+/, '');
            return digits || '';
        },
        syncHidden() {
            if (typeof window.syncSitePhoneInput === 'function') {
                window.syncSitePhoneInput(this.$root);
            }
        }
     }"
     x-init="syncHidden(); $watch('prefix', () => syncHidden()); $watch('local', () => syncHidden())">
    @if ($label)
        <label class="{{ $labelClass }}">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <div class="flex gap-2">
        <select x-model="prefix" data-phone-prefix class="{{ $selectClass }}" @change="syncHidden()">
            @foreach ($countries as $country)
                <option value="{{ $country['prefix'] }}">{{ $country['emoji'] }} {{ $country['prefix'] }}</option>
            @endforeach
        </select>
        <input type="tel" inputmode="numeric" x-model="local" data-phone-local placeholder="712 345 678"
               @input="syncHidden()"
               @if ($requiredWhen) data-required-when="{{ $requiredWhen }}" @endif
               @if ($required) required @endif
               class="{{ $inputClass }} @error($name) border-red-400 @enderror">
    </div>
    <input type="hidden" name="{{ $name }}" data-phone-hidden value="{{ $split['full'] }}" :value="full()">
    @if ($help)
        <p class="mt-1.5 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

@once
    <script>
        window.syncSitePhoneInput = window.syncSitePhoneInput || function (root) {
            if (!root) return '';
            const prefix = (root.querySelector('[data-phone-prefix]')?.value || '').replace(/\D/g, '');
            const local = (root.querySelector('[data-phone-local]')?.value || '').replace(/\D/g, '').replace(/^0+/, '');
            const full = prefix + local;
            const hidden = root.querySelector('[data-phone-hidden]');
            if (hidden) hidden.value = full;
            return full;
        };

        document.addEventListener('submit', function (event) {
            event.target.querySelectorAll('[data-phone-input]').forEach(window.syncSitePhoneInput);
        }, true);
    </script>
@endonce
