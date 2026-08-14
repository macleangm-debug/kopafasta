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
    $incomeOptions = income_range_select_options();
    $fields = activity_fields_localized();
    $locations = config('tanzania_locations');
    $details = old('activity_details', $activityDetails ?? []);
@endphp

<div x-data="activityForm(@js($fields), @js($details), @js(old('activity_type', $activityType)), @js($locations), @js([
    'selectOption' => __('borrower.profile.select_option'),
    'selectRegion' => __('borrower.profile.select_region'),
    'selectDistrict' => __('borrower.profile.select_district'),
    'selectActivity' => __('borrower.profile.select_activity'),
]), @js($groupedSections), @js($types))">

    @if ($groupedSections)
        <div class="space-y-8">
            <section>
                <h2 class="font-semibold mb-1">{{ __('borrower.profile.activity_info') }}</h2>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.activity_info_hint') }}</p>
                <div class="grid sm:grid-cols-2 gap-4" x-data="{ fieldList: [] }" x-effect="fieldList = activityFields">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.what_do_you_do') }} <span class="text-red-500">*</span></label>
                        <div class="lg:hidden mb-0">
                            <button type="button" @click="activityPickerOpen = true"
                                    class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                                <span class="flex-1 text-left truncate" x-text="activityTypeLabel()"></span>
                                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                            </button>
                            <x-site.bottom-sheet :title="__('borrower.profile.what_do_you_do')" open="activityPickerOpen">
                                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                                    @foreach ($types as $key => $label)
                                        <button type="button" @click="pickActivity(@js($key))"
                                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                                :class="activityType === @js($key) ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </x-site.bottom-sheet>
                        </div>
                        <select name="activity_type" x-model="activityType" @change="onTypeChange()" required
                                class="hidden lg:block w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
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
                            <template x-if="field.type === 'select' || field.type === 'region' || field.type === 'district'">
                                <div>
                                    <div class="lg:hidden">
                                        <button type="button" @click="openDetailPicker(field)"
                                                class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                                            <span class="flex-1 text-left truncate" x-text="detailFieldLabel(field)"></span>
                                            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                        </button>
                                    </div>
                                    <select :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                                            @change="field.type === 'region' && onRegionChange()"
                                            class="hidden lg:block w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="field.required">
                                        <option value="" x-text="field.type === 'region' ? labels.selectRegion : (field.type === 'district' ? labels.selectDistrict : labels.selectOption)"></option>
                                        <template x-if="field.type === 'select'">
                                            <template x-for="(label, value) in field.options" :key="value">
                                                <option :value="value" x-text="label" :selected="details[field.key] === value"></option>
                                            </template>
                                        </template>
                                        <template x-if="field.type === 'region'">
                                            <template x-for="(districts, region) in locations" :key="region">
                                                <option :value="region" x-text="region" :selected="details[field.key] === region"></option>
                                            </template>
                                        </template>
                                        <template x-if="field.type === 'district'">
                                            <template x-for="district in districtsForRegion(details.region)" :key="district">
                                                <option :value="district" x-text="district" :selected="details[field.key] === district"></option>
                                            </template>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <template x-if="field.type !== 'select' && field.type !== 'region' && field.type !== 'district' && field.type !== 'document'">
                                <input type="text"
                                       :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                                       :placeholder="field.placeholder || ''"
                                       :required="field.required">
                            </template>
                        </div>
                    </template>
                    <div class="sm:col-span-2">
                        <x-site.profile-select
                            name="income_range"
                            :label="__('borrower.profile.income_range')"
                            :options="$incomeOptions"
                            :value="old('income_range', $incomeRange)"
                            :required="true"
                            :placeholder="__('borrower.profile.select_income')"
                        />
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
                                   :placeholder="field.placeholder || ''"
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
                <div class="lg:hidden">
                    <button type="button" @click="activityPickerOpen = true"
                            class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                        <span class="flex-1 text-left truncate" x-text="activityTypeLabel()"></span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <x-site.bottom-sheet :title="__('borrower.profile.what_do_you_do')" open="activityPickerOpen">
                        <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                            @foreach ($types as $key => $label)
                                <button type="button" @click="pickActivity(@js($key))"
                                        class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                        :class="activityType === @js($key) ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </x-site.bottom-sheet>
                </div>
                <select name="activity_type" x-model="activityType" @change="onTypeChange()" required
                        class="hidden lg:block w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
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

                    <template x-if="field.type === 'select' || field.type === 'region' || field.type === 'district'">
                        <div>
                            <div class="lg:hidden">
                                <button type="button" @click="openDetailPicker(field)"
                                        class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                                    <span class="flex-1 text-left truncate" x-text="detailFieldLabel(field)"></span>
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                </button>
                            </div>
                            <select :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                                    @change="field.type === 'region' && onRegionChange()"
                                    class="hidden lg:block w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="field.required">
                                <option value="" x-text="field.type === 'region' ? labels.selectRegion : (field.type === 'district' ? labels.selectDistrict : labels.selectOption)"></option>
                                <template x-if="field.type === 'select'">
                                    <template x-for="(label, value) in field.options" :key="value">
                                        <option :value="value" x-text="label" :selected="details[field.key] === value"></option>
                                    </template>
                                </template>
                                <template x-if="field.type === 'region'">
                                    <template x-for="(districts, region) in locations" :key="region">
                                        <option :value="region" x-text="region" :selected="details[field.key] === region"></option>
                                    </template>
                                </template>
                                <template x-if="field.type === 'district'">
                                    <template x-for="district in districtsForRegion(details.region)" :key="district">
                                        <option :value="district" x-text="district" :selected="details[field.key] === district"></option>
                                    </template>
                                </template>
                            </select>
                        </div>
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
                               :placeholder="field.placeholder || ''"
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
                <x-site.profile-select
                    name="income_range"
                    :label="__('borrower.profile.income_range')"
                    :options="$incomeOptions"
                    :value="old('income_range', $incomeRange)"
                    :required="true"
                    :placeholder="__('borrower.profile.select_income')"
                />
            </div>
        </div>
    @endif

    <x-site.bottom-sheet :title="__('borrower.profile.select_option')" open="detailPickerOpen">
        <div class="space-y-1 max-h-[60vh] overflow-y-auto">
            <p class="px-1 pb-2 text-xs font-semibold uppercase tracking-widest text-gray-400" x-text="detailPickerTitle()"></p>
            <template x-for="option in detailPickerOptions()" :key="option.value">
                <button type="button" @click="pickDetail(option.value)"
                        class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                        :class="details[detailPickerField?.key] === option.value ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                        x-text="option.label"></button>
            </template>
        </div>
    </x-site.bottom-sheet>
</div>

@once
    @push('scripts')
    <script>
        function activityForm(fieldMap, initialDetails, initialType, locations, labels, groupedSections, typeOptions) {
            return {
                fieldMap,
                locations,
                labels: labels || {},
                typeOptions: typeOptions || {},
                details: initialDetails || {},
                activityType: initialType || '',
                activityPickerOpen: false,
                detailPickerOpen: false,
                detailPickerField: null,
                activeFields: [],
                activityFields: [],
                employmentFields: [],
                groupedSections: !!groupedSections,
                init() {
                    this.refreshFields();
                },
                activityTypeLabel() {
                    return this.typeOptions[this.activityType] || this.labels.selectActivity || '';
                },
                pickActivity(key) {
                    this.activityType = key;
                    this.onTypeChange();
                    this.activityPickerOpen = false;
                },
                openDetailPicker(field) {
                    this.detailPickerField = field;
                    this.detailPickerOpen = true;
                },
                detailPickerTitle() {
                    return this.detailPickerField?.label || this.labels.selectOption || '';
                },
                detailPickerOptions() {
                    const field = this.detailPickerField;
                    if (! field) return [];
                    if (field.type === 'select') {
                        return Object.entries(field.options || {}).map(([value, label]) => ({ value, label }));
                    }
                    if (field.type === 'region') {
                        return Object.keys(this.locations || {}).map((region) => ({ value: region, label: region }));
                    }
                    if (field.type === 'district') {
                        return this.districtsForRegion(this.details.region).map((district) => ({ value: district, label: district }));
                    }
                    return [];
                },
                detailFieldLabel(field) {
                    const value = this.details[field.key];
                    if (! value) {
                        if (field.type === 'region') return this.labels.selectRegion || '';
                        if (field.type === 'district') return this.labels.selectDistrict || '';
                        return this.labels.selectOption || '';
                    }
                    if (field.type === 'select') {
                        return (field.options && field.options[value]) || value;
                    }
                    return value;
                },
                pickDetail(value) {
                    if (! this.detailPickerField) return;
                    const key = this.detailPickerField.key;
                    this.details[key] = value;
                    if (this.detailPickerField.type === 'region') {
                        this.onRegionChange();
                    }
                    this.detailPickerOpen = false;
                    this.detailPickerField = null;
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
