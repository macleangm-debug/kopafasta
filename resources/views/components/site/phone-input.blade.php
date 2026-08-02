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
        ? 'w-28 shrink-0 px-3.5 py-3 rounded-xl bg-white border border-gray-300 text-base outline-none transition focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10'
        : 'w-28 shrink-0 rounded-lg border-gray-300 text-base focus:border-amber-500 focus:ring-amber-500');
    $inputClass = $inputClass ?? ($variant === 'rounded'
        ? 'flex-1 px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-base outline-none transition'
        : 'flex-1 rounded-lg border-gray-300 text-base focus:border-amber-500 focus:ring-amber-500');
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
        <div class="lg:hidden shrink-0" x-data="{ pickerOpen: false }">
            <button type="button" @click="pickerOpen = true"
                    class="{{ $selectClass }} inline-flex items-center justify-between gap-1 text-left">
                <span class="truncate" x-text="(@js(collect($countries)->mapWithKeys(fn ($c) => [$c['prefix'] => ($c['emoji'] ?? '').' '.$c['prefix']])->all()))[prefix] || prefix"></span>
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
            <x-site.bottom-sheet :title="$label ?: 'Country'" open="pickerOpen">
                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                    @foreach ($countries as $country)
                        <button type="button"
                                @click="prefix = @js($country['prefix']); syncHidden(); pickerOpen = false"
                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                :class="prefix === @js($country['prefix']) ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                            {{ $country['emoji'] ?? '' }} {{ $country['prefix'] }}
                        </button>
                    @endforeach
                </div>
            </x-site.bottom-sheet>
        </div>
        <select x-model="prefix" data-phone-prefix class="{{ $selectClass }} max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden" @change="syncHidden()">
            @foreach ($countries as $country)
                <option value="{{ $country['prefix'] }}">{{ $country['emoji'] }} {{ $country['prefix'] }}</option>
            @endforeach
        </select>
        <input type="tel" inputmode="numeric" x-model="local" data-phone-local
               placeholder="712 345 678"
               autocomplete="tel-national"
               name="{{ $name }}_local"
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
