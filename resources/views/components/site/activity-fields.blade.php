@props([
    'activityType' => '',
    'activityDetails' => [],
    'incomeRange' => '',
    'prefix' => '',
])

@php
    $types = activity_type_options();
    $fields = activity_fields_localized();
    $locations = config('tanzania_locations');
    $details = old('activity_details', $activityDetails ?? []);
@endphp

<div x-data="activityForm(@js($fields), @js($details), @js(old('activity_type', $activityType)), @js($locations), @js([
    'selectOption' => __('borrower.profile.select_option'),
    'selectRegion' => __('borrower.profile.select_region'),
    'selectDistrict' => __('borrower.profile.select_district'),
]))">
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.what_do_you_do') }} <span class="text-red-500">*</span></label>
            <select name="activity_type" x-model="activityType" @change="onTypeChange()" required
                    class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                <option value="">{{ __('borrower.profile.select_activity') }}</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected(old('activity_type', $activityType) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <template x-for="field in activeFields" :key="field.key">
            <div :class="(field.type === 'select' || field.type === 'region' || field.type === 'district') ? '' : 'sm:col-span-2'">
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    <span x-text="field.label"></span>
                    <span x-show="field.required" class="text-red-500">*</span>
                </label>

                <template x-if="field.type === 'select'">
                    <select :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                            class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="field.required">
                        <option value="" x-text="labels.selectOption"></option>
                        <template x-for="(label, value) in field.options" :key="value">
                            <option :value="value" x-text="label" :selected="details[field.key] === value"></option>
                        </template>
                    </select>
                </template>

                <template x-if="field.type === 'region'">
                    <select :name="'activity_details[' + field.key + ']'" x-model="details[field.key]" @change="onRegionChange(field.key)"
                            class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="field.required">
                        <option value="" x-text="labels.selectRegion"></option>
                        <template x-for="(districts, region) in locations" :key="region">
                            <option :value="region" x-text="region" :selected="details[field.key] === region"></option>
                        </template>
                    </select>
                </template>

                <template x-if="field.type === 'district'">
                    <select :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                            class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="field.required">
                        <option value="" x-text="labels.selectDistrict"></option>
                        <template x-for="district in districtsForRegion(details.region)" :key="district">
                            <option :value="district" x-text="district" :selected="details[field.key] === district"></option>
                        </template>
                    </select>
                </template>

                <template x-if="field.type !== 'select' && field.type !== 'region' && field.type !== 'district'">
                    <input type="text"
                           :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                           :required="field.required">
                </template>
            </div>
        </template>

        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.income_range') }} <span class="text-red-500">*</span></label>
            <select name="income_range" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                <option value="">{{ __('borrower.profile.select_income') }}</option>
                @foreach (income_range_options() as $key => $label)
                    <option value="{{ $key }}" @selected(old('income_range', $incomeRange) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function activityForm(fieldMap, initialDetails, initialType, locations, labels) {
            return {
                fieldMap,
                locations,
                labels: labels || {},
                details: initialDetails || {},
                activityType: initialType || '',
                activeFields: [],
                init() {
                    this.refreshFields();
                },
                onTypeChange() {
                    this.details = {};
                    this.refreshFields();
                },
                onRegionChange() {
                    this.details.district = '';
                },
                districtsForRegion(region) {
                    return region && this.locations[region] ? this.locations[region] : [];
                },
                refreshFields() {
                    this.activeFields = this.fieldMap[this.activityType] || [];
                },
            };
        }
    </script>
    @endpush
@endonce
