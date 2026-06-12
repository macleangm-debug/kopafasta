@props([
    'customer',
    'inputClass' => 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm',
    'required' => true,
])

@php
    $relationships = config('kin.relationships', []);
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

    $phone = old('nok_phone', $customer->nok_phone ?? '');
    $digits = preg_replace('/\D/', '', $phone);
    $country = old('nok_country', 'TZ');
    $dialCode = old('nok_dial_code', '+255');
    $localPhone = old('nok_local_phone', '');

    if ($digits !== '') {
        if (str_starts_with($digits, '254')) {
            $country = 'KE';
            $dialCode = '+254';
            $localPhone = ltrim(substr($digits, 3), '0');
        } elseif (str_starts_with($digits, '256')) {
            $country = 'UG';
            $dialCode = '+256';
            $localPhone = ltrim(substr($digits, 3), '0');
        } else {
            if (str_starts_with($digits, '255')) {
                $digits = substr($digits, 3);
            }
            $localPhone = ltrim($digits, '0');
        }
    }
@endphp

<div class="space-y-4" x-data="kinPhone(@js($country), @js($dialCode), @js($localPhone))">
    <div class="grid sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.first_name') }} @if($required)<span class="text-red-500">*</span>@endif</label>
            <input name="nok_first_name" value="{{ $first }}" @if($required) required @endif class="{{ $inputClass }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.middle_name') }}</label>
            <input name="nok_middle_name" value="{{ $middle }}" class="{{ $inputClass }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.last_name') }} @if($required)<span class="text-red-500">*</span>@endif</label>
            <input name="nok_last_name" value="{{ $last }}" @if($required) required @endif class="{{ $inputClass }}">
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.relationship') }} @if($required)<span class="text-red-500">*</span>@endif</label>
            <select name="nok_relationship" @if($required) required @endif class="{{ $inputClass }}">
                <option value="">{{ __('borrower.profile.select_relationship') }}</option>
                @foreach ($relationships as $relationship)
                    <option value="{{ $relationship }}" @selected(old('nok_relationship', $customer->nok_relationship) === $relationship)>{{ $relationship }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }} @if($required)<span class="text-red-500">*</span>@endif</label>
            <div class="flex gap-2">
                <select x-model="country" @change="onCountryChange()" class="w-28 shrink-0 rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-2 py-2 text-sm">
                    <template x-for="item in countries" :key="item.code">
                        <option :value="item.code" x-text="item.label + ' ' + item.prefix"></option>
                    </template>
                </select>
                <input type="tel" x-model="localPhone" inputmode="tel" placeholder="7XX XXX XXX"
                       @if($required) required @endif
                       class="flex-1 min-w-0 rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
            <input type="hidden" name="nok_phone" :value="fullPhone">
            <input type="hidden" name="nok_country" :value="country">
            <input type="hidden" name="nok_dial_code" :value="dialCode">
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function kinPhone(initialCountry, initialDial, initialLocal) {
            return {
                countries: [
                    { code: 'TZ', label: 'TZ', prefix: '+255' },
                    { code: 'KE', label: 'KE', prefix: '+254' },
                    { code: 'UG', label: 'UG', prefix: '+256' },
                ],
                country: initialCountry || 'TZ',
                localPhone: initialLocal || '',
                get dialCode() {
                    return (this.countries.find(c => c.code === this.country) || this.countries[0]).prefix;
                },
                get fullPhone() {
                    const local = (this.localPhone || '').replace(/^0+/, '').replace(/\s+/g, '');
                    return local ? this.dialCode + local : '';
                },
                onCountryChange() {
                    this.localPhone = (this.localPhone || '').replace(/^0+/, '');
                },
            };
        }
    </script>
    @endpush
@endonce
