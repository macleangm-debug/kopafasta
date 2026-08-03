@props([
    'prefix' => '',
    'formKey' => null,
    'region' => '',
    'district' => '',
    'ward' => '',
    'street' => '',
    'required' => true,
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
@endphp

<div class="grid sm:grid-cols-2 gap-4" x-data="{
    ...tzAddress(@js($locations), @js($initialRegion), @js($initialDistrict), @js([
        'selectRegion' => __('borrower.profile.select_region'),
        'selectDistrict' => __('borrower.profile.select_district'),
    ])),
    regionPickerOpen: false,
    districtPickerOpen: false,
    pickRegion(value) {
        this.region = value;
        this.onRegionChange();
        this.regionPickerOpen = false;
    },
    pickDistrict(value) {
        this.district = value;
        this.districtPickerOpen = false;
    },
}">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.region') }} @if($required)<span class="text-red-500">*</span>@endif</label>

        <div class="lg:hidden">
            <button type="button" @click="regionPickerOpen = true"
                    class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                <span class="flex-1 text-left truncate" x-text="region || @js(__('borrower.profile.select_region'))"></span>
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

        <select name="{{ $regionName }}" x-model="region" @change="onRegionChange()" @if($required) required @endif
                class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm max-lg:sr-only">
            <option value="">{{ __('borrower.profile.select_region') }}</option>
            @foreach ($locations as $regionLabel => $districts)
                <option value="{{ $regionLabel }}" @selected($initialRegion === $regionLabel)>{{ $regionLabel }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.district') }} @if($required)<span class="text-red-500">*</span>@endif</label>

        <div class="lg:hidden">
            <button type="button" @click="districtPickerOpen = true" :disabled="!region"
                    class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition disabled:opacity-50">
                <span class="flex-1 text-left truncate" x-text="district || @js(__('borrower.profile.select_district'))"></span>
                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
            <x-site.bottom-sheet :title="__('borrower.profile.fields.district')" open="districtPickerOpen">
                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                    <template x-for="d in districtOptions" :key="d">
                        <button type="button" @click="pickDistrict(d)"
                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                :class="district === d ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                                x-text="d"></button>
                    </template>
                </div>
            </x-site.bottom-sheet>
        </div>

        {{-- Visible control (mobile sheet + desktop select). Hidden field is the submitted value. --}}
        <input type="hidden" name="{{ $districtName }}" :value="district" x-ref="districtHidden">
        <select x-model="district" @if($required) required @endif
                class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm max-lg:sr-only"
                @change="district = $event.target.value">
            <option value="">{{ __('borrower.profile.select_district') }}</option>
            <template x-for="d in districtOptions" :key="'opt-' + d">
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
