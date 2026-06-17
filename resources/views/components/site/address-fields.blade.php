@props([
    'prefix' => '',
    'formKey' => null,
    'region' => '',
    'district' => '',
    'ward' => '',
    'street' => '',
    'required' => true,
    'locations' => config('tanzania_locations'),
])

@php
    $fieldName = function (string $part) use ($prefix, $formKey): string {
        $base = ($prefix ? $prefix.'_' : '').$part;

        return $formKey ? "{$formKey}[{$base}]" : $base;
    };
    $oldKey = function (string $part) use ($prefix, $formKey): string {
        $base = ($prefix ? $prefix.'_' : '').$part;

        return $formKey ? "{$formKey}.{$base}" : $base;
    };
    $regionName = $fieldName('region');
    $districtName = $fieldName('district');
    $wardName = $fieldName('ward');
    $streetName = $fieldName('street');
    $initialRegion = old($oldKey('region'), $region);
    $initialDistrict = old($oldKey('district'), $district);
@endphp

<div class="grid sm:grid-cols-2 gap-4" x-data="tzAddress(@js($locations), @js($initialRegion), @js($initialDistrict), @js([
    'selectRegion' => __('borrower.profile.select_region'),
    'selectDistrict' => __('borrower.profile.select_district'),
]))">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.region') }} @if($required)<span class="text-red-500">*</span>@endif</label>
        <select name="{{ $regionName }}" x-model="region" @change="onRegionChange()" @if($required) required @endif
                class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
            <option value="">{{ __('borrower.profile.select_region') }}</option>
            @foreach ($locations as $regionLabel => $districts)
                <option value="{{ $regionLabel }}" @selected($initialRegion === $regionLabel)>{{ $regionLabel }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.district') }} @if($required)<span class="text-red-500">*</span>@endif</label>
        <select name="{{ $districtName }}" x-model="district" @if($required) required @endif
                class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm"
                :key="'district-' + region">
            <option value="">{{ __('borrower.profile.select_district') }}</option>
            <template x-for="d in districtOptions" :key="d">
                <option :value="d" x-text="d"></option>
            </template>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.ward') }}</label>
        <input name="{{ $wardName }}" value="{{ old($oldKey('ward'), $ward) }}"
               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm"
               placeholder="{{ __('borrower.profile.ward_placeholder') }}">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.street') }} @if($required)<span class="text-red-500">*</span>@endif</label>
        <input name="{{ $streetName }}" value="{{ old($oldKey('street'), $street) }}" @if($required) required @endif
               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm"
               placeholder="{{ __('borrower.profile.street_placeholder') }}">
    </div>
</div>

@once
    @push('scripts')
    <script>
        function tzAddress(locations, initialRegion, initialDistrict, labels) {
            return {
                locations,
                labels: labels || {},
                savedDistrict: initialDistrict || '',
                region: initialRegion || '',
                district: initialDistrict || '',
                districtOptions: [],
                init() {
                    this.refreshDistricts();
                    this.$nextTick(() => {
                        if (this.savedDistrict) {
                            this.district = this.savedDistrict;
                        }
                    });
                },
                onRegionChange() {
                    this.district = '';
                    this.savedDistrict = '';
                    this.refreshDistricts();
                },
                refreshDistricts() {
                    const districts = this.region && this.locations[this.region]
                        ? [...this.locations[this.region]]
                        : [];

                    const preserve = this.savedDistrict || this.district;
                    if (preserve && !districts.includes(preserve)) {
                        districts.unshift(preserve);
                    }

                    this.districtOptions = districts;
                },
            };
        }
    </script>
    @endpush
@endonce
