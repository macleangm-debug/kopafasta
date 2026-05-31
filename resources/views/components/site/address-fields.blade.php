@props([
    'prefix' => '',
    'region' => '',
    'district' => '',
    'ward' => '',
    'street' => '',
    'required' => true,
    'locations' => config('tanzania_locations'),
])

@php
    $p = $prefix ? $prefix.'_' : '';
    $regionName = $p.'region';
    $districtName = $p.'district';
    $wardName = $p.'ward';
    $streetName = $p.'street';
@endphp

<div class="grid sm:grid-cols-2 gap-4" x-data="tzAddress(@js($locations), @js(old($regionName, $region)), @js(old($districtName, $district)))">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Region @if($required)<span class="text-red-500">*</span>@endif</label>
        <select name="{{ $regionName }}" x-model="region" @change="onRegionChange()" @if($required) required @endif
                class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
            <option value="">Select region</option>
            <template x-for="(districts, name) in locations" :key="name">
                <option :value="name" x-text="name" :selected="name === region"></option>
            </template>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">District @if($required)<span class="text-red-500">*</span>@endif</label>
        <select name="{{ $districtName }}" x-model="district" @if($required) required @endif
                class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
            <option value="">Select district</option>
            <template x-for="d in districtOptions" :key="d">
                <option :value="d" x-text="d"></option>
            </template>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Ward <span class="text-gray-400">(optional)</span></label>
        <input name="{{ $wardName }}" value="{{ old($wardName, $ward) }}"
               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm"
               placeholder="Ward or village">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Street / detailed address @if($required)<span class="text-red-500">*</span>@endif</label>
        <input name="{{ $streetName }}" value="{{ old($streetName, $street) }}" @if($required) required @endif
               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm"
               placeholder="Street, plot, house number">
    </div>
</div>

@once
    @push('scripts')
    <script>
        function tzAddress(locations, initialRegion, initialDistrict) {
            return {
                locations,
                region: initialRegion || '',
                district: initialDistrict || '',
                districtOptions: [],
                init() {
                    this.refreshDistricts();
                },
                onRegionChange() {
                    this.district = '';
                    this.refreshDistricts();
                },
                refreshDistricts() {
                    this.districtOptions = this.region && this.locations[this.region]
                        ? this.locations[this.region]
                        : [];
                },
            };
        }
    </script>
    @endpush
@endonce
