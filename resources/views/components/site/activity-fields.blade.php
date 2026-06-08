@props([
    'activityType' => '',
    'activityDetails' => [],
    'incomeRange' => '',
    'prefix' => '',
    'groupedSections' => false,
    'employmentContract' => null,
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
]), @js($groupedSections))">

    @if ($groupedSections)
        <div class="space-y-8">
            <section>
                <h2 class="font-semibold mb-1">{{ __('borrower.profile.activity_info') }}</h2>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.activity_info_hint') }}</p>
                <div class="grid sm:grid-cols-2 gap-4" x-data="{ fieldList: [] }" x-effect="fieldList = activityFields">
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
                    <template x-for="field in activityFields" :key="field.key">
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
                            <template x-if="field.type !== 'select' && field.type !== 'region' && field.type !== 'district' && field.type !== 'document'">
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
            </section>

            <section class="border-t border-gray-100 pt-6" x-show="activityType === 'employed'" x-cloak>
                <h2 class="font-semibold mb-1">{{ __('borrower.profile.employment_info') }}</h2>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.employment_info_hint') }}</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <template x-for="field in employmentFields" :key="field.key">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                <span x-text="field.label"></span>
                                <span x-show="field.required" class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                                   :required="field.required">
                        </div>
                    </template>
                    <div class="sm:col-span-2">
                        <x-site.profile-document-field
                            :document="$employmentContract"
                            field-name="employment_contract"
                            pages-field-name="employment_contract_pages"
                            mode="multi"
                            :label="__('borrower.profile.employment_contract')"
                            input-host-id="employment-contract-pages"
                            :required="true"
                            :labels="[
                                'hint' => __('borrower.profile.employment_contract_hint'),
                                'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                'capturePage' => __('borrower.profile.capture_pages'),
                            ]"
                        />
                    </div>
                </div>
            </section>
        </div>
    @else
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

                    <template x-if="field.type === 'document'">
                        <div class="sm:col-span-2 space-y-2">
                            <p class="text-xs text-gray-500" x-text="field.hint || ''"></p>
                        </div>
                    </template>

                    <template x-if="field.type !== 'select' && field.type !== 'region' && field.type !== 'district' && field.type !== 'document'">
                        <input type="text"
                               :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                               :required="field.required">
                    </template>
                </div>
            </template>

            <div class="sm:col-span-2" x-show="activityType === 'employed'" x-cloak>
                <p class="text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.employment_contract') }} <span class="text-red-500">*</span></p>
                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.profile.employment_contract_hint') }}</p>
                <x-site.multi-page-document-upload name="employment_contract_pages" input-host-id="employment-contract-pages" />
                <label class="mt-3 block text-xs text-gray-500">{{ __('borrower.profile.residence_letter_single') }}</label>
                <input type="file" name="employment_contract" accept="image/*,application/pdf" class="mt-1 block w-full text-sm text-gray-600">
            </div>

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
    @endif
</div>

@once
    @push('scripts')
    <script>
        function activityForm(fieldMap, initialDetails, initialType, locations, labels, groupedSections) {
            return {
                fieldMap,
                locations,
                labels: labels || {},
                details: initialDetails || {},
                activityType: initialType || '',
                activeFields: [],
                activityFields: [],
                employmentFields: [],
                groupedSections: !!groupedSections,
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
                    const all = (this.fieldMap[this.activityType] || []).filter(f => f.type !== 'document');
                    this.activeFields = all;
                    if (this.activityType === 'employed') {
                        this.employmentFields = all;
                        this.activityFields = [];
                    } else {
                        this.employmentFields = [];
                        this.activityFields = all;
                    }
                },
            };
        }
    </script>
    @endpush
@endonce
