@props([
    'activityType' => '',
    'activityDetails' => [],
    'incomeRange' => '',
    'prefix' => '',
    'groupedSections' => false,
    'employmentContract' => null,
    'tinCertificate' => null,
    'businessLicense' => null,
    'requireTin' => false,
    'requireLicence' => false,
])

@php
    $types = activity_type_options();
    $incomeOptions = income_range_select_options();
    $fields = activity_fields_localized();
    $details = old('activity_details', $activityDetails ?? []);
@endphp

<div x-data="activityForm(@js($fields), @js($details), @js(old('activity_type', $activityType)), @js([
    'selectOption' => __('borrower.profile.select_option'),
    'selectActivity' => __('borrower.profile.select_activity'),
]), @js($groupedSections), @js($types))">

    @if ($groupedSections)
        <div class="space-y-8">
            <section>
                <h2 class="font-semibold mb-1">{{ __('borrower.profile.activity_info') }}</h2>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.activity_info_hint') }}</p>
                <div class="grid sm:grid-cols-2 gap-4">
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
                        <select name="activity_type" x-model="activityType" @change="onTypeChange(); $dispatch('profile-select', { name: 'activity_type', value: activityType })" required
                                class="hidden lg:block w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                            <option value="">{{ __('borrower.profile.select_activity') }}</option>
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}" @selected(old('activity_type', $activityType) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- Must bind on parent activityForm scope — nested x-data broke activityFields and left only Type + Income. --}}
                    <template x-for="field in activityFields" :key="activityType + '-' + field.key">
                        <div :class="field.type === 'select' ? '' : 'sm:col-span-2'">
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                <span x-text="field.label"></span>
                                <span x-show="field.required" class="text-red-500">*</span>
                            </label>
                            <template x-if="field.type === 'select'">
                                <div>
                                    <div class="lg:hidden">
                                        <button type="button" @click="openDetailPicker(field)"
                                                class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                                            <span class="flex-1 text-left truncate" x-text="detailFieldLabel(field)"></span>
                                            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                        </button>
                                    </div>
                                    <select :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                                            @change="$dispatch('profile-select', { name: 'activity_details[' + field.key + ']', value: details[field.key] })"
                                            class="hidden lg:block w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="field.required">
                                        <option value="" x-text="labels.selectOption"></option>
                                        <template x-for="(label, value) in field.options" :key="value">
                                            <option :value="value" x-text="label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <template x-if="field.type !== 'select' && field.type !== 'document'">
                                <input type="text"
                                       :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                                       :placeholder="field.placeholder || ''"
                                       :required="field.required">
                            </template>
                        </div>
                    </template>
                    @include('components.site._activity-location-fields', ['details' => $details])
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
                    <template x-for="field in employmentFields" :key="activityType + '-' + field.key">
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
                                'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                'capturePage' => __('borrower.profile.capture_pages'),
                            ]"
                        />
                    </div>
                </div>
            </section>

            @if ($requireTin || $requireLicence)
                <section id="profile-business-verification" class="border-t border-gray-100 pt-6" x-show="activityType === 'business_owner'" x-cloak>
                    <h2 class="font-semibold mb-1">{{ __('borrower.profile.business_verification') }}</h2>
                    <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.business_verification_hint') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        @if ($requireTin)
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.tin_number') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="activity_details[tin_number]" x-model="details.tin_number"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <x-site.profile-document-field
                                    :document="$tinCertificate"
                                    field-name="tin_certificate"
                                    pages-field-name="tin_certificate_pages"
                                    mode="multi"
                                    :label="__('borrower.profile.tin_certificate')"
                                    input-host-id="tin-certificate-pages"
                                />
                            </div>
                        @endif
                        @if ($requireLicence)
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.licence_number') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="activity_details[licence_number]" x-model="details.licence_number"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.licence_authority') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="activity_details[licence_authority]" x-model="details.licence_authority"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.licence_issued_on') }} <span class="text-red-500">*</span></label>
                                <input type="date" name="activity_details[licence_issued_on]" x-model="details.licence_issued_on"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.licence_expires_on') }}</label>
                                <input type="date" name="activity_details[licence_expires_on]" x-model="details.licence_expires_on"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <x-site.profile-document-field
                                    :document="$businessLicense"
                                    field-name="business_license"
                                    pages-field-name="business_license_pages"
                                    mode="multi"
                                    :label="__('borrower.profile.business_license')"
                                    input-host-id="business-license-pages"
                                />
                            </div>
                        @endif
                    </div>
                </section>
            @endif
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
                <select name="activity_type" x-model="activityType" @change="onTypeChange(); $dispatch('profile-select', { name: 'activity_type', value: activityType })" required
                        class="hidden lg:block w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                    <option value="">{{ __('borrower.profile.select_activity') }}</option>
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('activity_type', $activityType) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <template x-for="field in activeFields" :key="activityType + '-' + field.key">
                <div :class="field.type === 'select' ? '' : 'sm:col-span-2'">
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        <span x-text="field.label"></span>
                        <span x-show="field.required" class="text-red-500">*</span>
                    </label>

                    <template x-if="field.type === 'select'">
                        <div>
                            <div class="lg:hidden">
                                <button type="button" @click="openDetailPicker(field)"
                                        class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                                    <span class="flex-1 text-left truncate" x-text="detailFieldLabel(field)"></span>
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                </button>
                            </div>
                            <select :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                                    class="hidden lg:block w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="field.required">
                                <option value="" x-text="labels.selectOption"></option>
                                <template x-for="(label, value) in field.options" :key="value">
                                    <option :value="value" x-text="label"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                    <template x-if="field.type === 'document'">
                        <div class="sm:col-span-2 space-y-2">
                            <p class="text-xs text-gray-500" x-text="field.hint || ''"></p>
                        </div>
                    </template>
                    <template x-if="field.type !== 'select' && field.type !== 'document'">
                        <input type="text"
                               :name="'activity_details[' + field.key + ']'" x-model="details[field.key]"
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                               :placeholder="field.placeholder || ''"
                               :required="field.required">
                    </template>
                </div>
            </template>
            @include('components.site._activity-location-fields', ['details' => $details])

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
            <template x-for="option in pickerOptions" :key="option.value">
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
        function activityForm(fieldMap, initialDetails, initialType, labels, groupedSections, typeOptions) {
            return {
                fieldMap,
                labels: labels || {},
                typeOptions: typeOptions || {},
                details: Object.assign({}, initialDetails || {}),
                activityType: initialType || '',
                activityPickerOpen: false,
                detailPickerOpen: false,
                detailPickerField: null,
                pickerOptions: [],
                activeFields: [],
                activityFields: [],
                employmentFields: [],
                groupedSections: !!groupedSections,
                init() {
                    this.refreshFields();
                    this.syncLocationEnabled();
                },
                hasLocationFields() {
                    return (this.fieldMap[this.activityType] || []).some((f) => f.type === 'region' || f.type === 'district');
                },
                locationKeysForType() {
                    return (this.fieldMap[this.activityType] || [])
                        .filter((f) => f.type === 'region' || f.type === 'district')
                        .map((f) => f.key);
                },
                syncLocationEnabled() {
                    const on = this.hasLocationFields();
                    const root = this.$root && this.$root.querySelector
                        ? this.$root.querySelector('[data-kf-activity-location]')
                        : null;
                    if (! root) return;
                    root.querySelectorAll('[name="activity_details[region]"], [name="activity_details[district]"]').forEach((el) => {
                        el.disabled = ! on;
                        el.required = on;
                    });
                },
                activityTypeLabel() {
                    return this.typeOptions[this.activityType] || this.labels.selectActivity || '';
                },
                pickActivity(key) {
                    this.activityType = key;
                    this.activityPickerOpen = false;
                    this.onTypeChange();
                    this.$nextTick(() => {
                        const sel = this.$root.querySelector('select[name="activity_type"]');
                        if (sel) {
                            sel.value = key;
                            sel.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        this.$dispatch('profile-select', { name: 'activity_type', value: key });
                    });
                },
                openDetailPicker(field) {
                    this.detailPickerField = field;
                    this.refreshPickerOptions();
                    this.detailPickerOpen = true;
                },
                detailPickerTitle() {
                    return this.detailPickerField?.label || this.labels.selectOption || '';
                },
                refreshPickerOptions() {
                    const field = this.detailPickerField;
                    if (! field || field.type !== 'select') {
                        this.pickerOptions = [];
                        return;
                    }
                    this.pickerOptions = Object.entries(field.options || {}).map(([value, label]) => ({ value, label }));
                },
                detailFieldLabel(field) {
                    const value = this.details[field.key];
                    if (! value) {
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
                    this.details = Object.assign({}, this.details, { [key]: value });
                    this.detailPickerOpen = false;
                    this.detailPickerField = null;
                    this.pickerOptions = [];
                    this.$nextTick(() => {
                        const sel = this.$root.querySelector('select[name="activity_details[' + key + ']"]');
                        if (sel) {
                            sel.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        this.$dispatch('profile-select', { name: 'activity_details[' + key + ']', value });
                    });
                },
                onTypeChange() {
                    this.refreshFields();
                    const allowed = new Set((this.activeFields || []).map((f) => f.key));
                    this.locationKeysForType().forEach((k) => allowed.add(k));
                    const next = {};
                    Object.keys(this.details || {}).forEach((k) => {
                        if (allowed.has(k)) next[k] = this.details[k];
                    });
                    this.details = next;
                    this.$nextTick(() => this.syncLocationEnabled());
                },
                refreshFields() {
                    const all = (this.fieldMap[this.activityType] || []).filter((f) => (
                        f.type !== 'document' && f.type !== 'region' && f.type !== 'district'
                    ));
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
