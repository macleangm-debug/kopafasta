@props([
    'prefix' => '',
    'formKey' => null,
    'region' => '',
    'district' => '',
    'ward' => '',
    'street' => '',
    'required' => true,
    'requireStreet' => null,
    'showWard' => true,
    'showStreet' => true,
    /** When true, always show native selects (no mobile-only sheet). Use on wizard Overview where fields must never disappear. */
    'forceNative' => false,
    'locations' => location_tree('TZ'),
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
    $regionOptions = array_keys($locations instanceof \Illuminate\Support\Collection ? $locations->all() : (array) $locations);
    $streetRequired = ($showStreet && ($requireStreet ?? $required));
    $showWard = (bool) $showWard;
    $showStreet = (bool) $showStreet;
    $forceNative = (bool) $forceNative;
    $sheetClass = $forceNative ? 'hidden' : 'lg:hidden';
    $selectClass = $forceNative
        ? 'block w-full rounded-xl border-gray-300 ring-1 ring-gray-200 focus:ring-brand px-4 py-3 text-sm'
        : 'hidden lg:block w-full rounded-xl border-gray-300 ring-1 ring-gray-200 focus:ring-brand px-4 py-3 text-sm';
@endphp

<div class="grid sm:grid-cols-2 gap-4" data-kf-address-fields x-data="{
    ...tzAddress(@js($locations), @js($initialRegion), @js($initialDistrict), @js([
        'selectRegion' => __('borrower.profile.select_region'),
        'selectDistrict' => __('borrower.profile.select_district'),
        'loadingDistricts' => __('borrower.profile.loading_districts'),
        'districtsUnavailable' => __('borrower.profile.districts_unavailable'),
        'retryDistricts' => __('borrower.profile.retry_districts'),
        'selectRegionFirst' => __('borrower.profile.select_region_first'),
    ])),
    regionPickerOpen: false,
    districtPickerOpen: false,
    pickRegion(value) {
        this.region = value;
        this.onRegionChange();
        this.regionPickerOpen = false;
        this.$nextTick(() => {
            const el = this.$refs.regionSelect;
            if (el) el.dispatchEvent(new Event('change', { bubbles: true }));
            this.$dispatch('profile-select', { name: (el && el.name) ? el.name : '', value: value });
        });
    },
    pickDistrict(value) {
        this.district = value;
        this.districtPickerOpen = false;
        this.$nextTick(() => {
            const el = this.$refs.districtHidden;
            if (el) el.dispatchEvent(new Event('change', { bubbles: true }));
            this.$dispatch('profile-select', { name: (el && el.name) ? el.name : '', value: value });
        });
    },
}">
    <div data-address-region>
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('borrower.profile.fields.region') }} @if($required)<span class="text-red-500">*</span>@endif</label>

        <div class="{{ $sheetClass }}">
            <button type="button" @click="regionPickerOpen = true"
                    class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                <span class="flex-1 text-left truncate" x-text="region || labels.selectRegion"></span>
                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
            <x-site.bottom-sheet :title="__('borrower.profile.fields.region')" open="regionPickerOpen">
                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                    @foreach ($regionOptions as $regionLabel)
                        <button type="button" @click="pickRegion(@js($regionLabel))"
                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                :class="region === @js($regionLabel) ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                            {{ $regionLabel }}
                        </button>
                    @endforeach
                </div>
            </x-site.bottom-sheet>
        </div>

        <select name="{{ $regionName }}" x-model="region" x-ref="regionSelect" @change="onRegionChange()" @if($required) required @endif
                class="{{ $selectClass }}">
            <option value="">{{ __('borrower.profile.select_region') }}</option>
            @foreach ($locations as $regionLabel => $districts)
                <option value="{{ $regionLabel }}" @selected($initialRegion === $regionLabel)>{{ $regionLabel }}</option>
            @endforeach
        </select>
    </div>
    <div data-address-district>
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('borrower.profile.fields.district') }} @if($required)<span class="text-red-500">*</span>@endif</label>

        <div class="{{ $sheetClass }}">
            <button type="button" @click="openDistrictPicker()"
                    class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                <span class="flex-1 text-left truncate" x-text="district || districtPlaceholder()"></span>
                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
            <x-site.bottom-sheet :title="__('borrower.profile.fields.district')" open="districtPickerOpen">
                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                    <p x-show="!region" class="px-1 py-3 text-sm text-gray-500" x-text="labels.selectRegionFirst"></p>
                    <p x-show="districtStatus === 'loading'" class="px-1 py-3 text-sm text-gray-500" x-text="labels.loadingDistricts"></p>
                    <div x-show="region && (districtStatus === 'empty' || districtStatus === 'error')" class="px-1 py-3 space-y-2">
                        <p class="text-sm text-rose-600" x-text="labels.districtsUnavailable"></p>
                        <button type="button" class="text-sm font-semibold text-brand underline" @click="retryDistricts()" x-text="labels.retryDistricts"></button>
                    </div>
                    <template x-for="d in districtOptions" :key="d">
                        <button type="button" @click="pickDistrict(d)"
                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                :class="district === d ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                                x-text="d"></button>
                    </template>
                </div>
            </x-site.bottom-sheet>
        </div>

        <input type="hidden" name="{{ $districtName }}" :value="district" x-ref="districtHidden" @if($required) required @endif>
        <select x-model="district"
                x-ref="districtSelect"
                :key="'district-' + (region || '')"
                class="{{ $selectClass }}"
                @change="
                    district = $event.target.value;
                    $nextTick(() => {
                        const el = $refs.districtHidden;
                        if (el) el.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                ">
            <option value="" x-text="districtPlaceholder()"></option>
            <template x-for="d in districtOptions" :key="'opt-' + d">
                <option :value="d" x-text="d"></option>
            </template>
        </select>
        <p x-show="districtStatus === 'loading'" class="mt-1 text-xs text-gray-500" x-text="labels.loadingDistricts"></p>
        <div x-show="region && (districtStatus === 'empty' || districtStatus === 'error')" class="mt-1 flex flex-wrap items-center gap-2">
            <p class="text-xs text-rose-600" x-text="labels.districtsUnavailable"></p>
            <button type="button" class="text-xs font-semibold text-brand underline" @click="retryDistricts()" x-text="labels.retryDistricts"></button>
        </div>
    </div>
    @if ($showWard)
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.ward') }}</label>
        <input name="{{ $wardName }}" value="{{ old($oldKey('ward'), $ward) }}"
               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm"
               placeholder="{{ __('borrower.profile.ward_placeholder') }}">
    </div>
    @else
        <input type="hidden" name="{{ $wardName }}" value="{{ old($oldKey('ward'), $ward) }}">
    @endif
    @if ($showStreet)
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.street') }} @if($streetRequired)<span class="text-red-500">*</span>@endif</label>
        <input name="{{ $streetName }}" value="{{ old($oldKey('street'), $street) }}" @if($streetRequired) required @endif
               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm"
               placeholder="{{ __('borrower.profile.street_placeholder') }}">
    </div>
    @else
        <input type="hidden" name="{{ $streetName }}" value="{{ old($oldKey('street'), $street) }}">
    @endif
</div>
