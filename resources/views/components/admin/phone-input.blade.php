@props([
    'name' => 'phone',
    'label' => 'Phone',
    'value' => null,
    'required' => false,
    'help' => null,
    'lockedCountry' => null,
])

@php
    $countryCode = strtoupper((string) ($lockedCountry ?: app(\App\Services\CountrySettingsService::class)->defaultCountryCode()));
    $country = app(\App\Services\CountrySettingsService::class)->forCode($countryCode);
    $lockedPrefix = $country['phone_prefix'] ?? '+255';
    $split = \App\Support\PhoneNumber::split($value, $countryCode);
    $split = [
        'prefix' => $lockedPrefix,
        'local' => $split['local'],
        'full' => \App\Support\PhoneNumber::normalizeForCountry($value, $countryCode) ?? '',
    ];
    $help = $help ?? 'Enter the number without a leading zero — the country prefix cannot be changed.';
@endphp

<div @error($name) data-has-error="true" @enderror
     data-phone-input
     data-phone-locked="1"
     x-data="{
        prefix: @js($split['prefix']),
        local: @js($split['local']),
        full() {
            const digits = (this.prefix || '').replace(/\D/g, '') + (this.local || '').replace(/\D/g, '').replace(/^0+/, '');
            return digits || '';
        },
        syncHidden() {
            if (this.$refs.full) {
                this.$refs.full.value = this.full();
            }
        }
     }"
     x-init="syncHidden(); $watch('local', () => syncHidden())">
    @if ($label)
        <label class="block text-xs font-semibold text-gray-700 mb-1">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <div class="flex gap-2">
        <div class="w-28 shrink-0 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 font-medium inline-flex items-center justify-center select-none" aria-hidden="true">
            <span>{{ $country['emoji'] ?? '' }} {{ $lockedPrefix }}</span>
        </div>
        <input type="hidden" data-phone-prefix value="{{ $lockedPrefix }}">
        <input type="tel" inputmode="numeric" pattern="[0-9]*" data-digits-only data-phone-local x-model="local" placeholder="712 345 678"
               @input="local = String(local || '').replace(/\D/g, '')"
               @if ($required) required @endif
               class="flex-1 rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand @error($name) border-red-400 @enderror">
    </div>
    <input type="hidden" name="{{ $name }}" x-ref="full" data-phone-hidden value="{{ $split['full'] }}">
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
