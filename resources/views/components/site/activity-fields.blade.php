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
    $currentType = old('activity_type', $activityType);
@endphp

<div data-kf-activity-fields
     x-data="activityForm(@js($fields), @js($currentType))"
     @profile-select="onProfileSelect($event.detail)">

    @if ($groupedSections)
        <div class="space-y-8">
            <section>
                <h2 class="font-semibold mb-1">{{ __('borrower.profile.activity_info') }}</h2>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.activity_info_hint') }}</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-site.profile-select
                            name="activity_type"
                            :label="__('borrower.profile.what_do_you_do')"
                            :options="$types"
                            :value="$currentType"
                            :required="true"
                            :placeholder="__('borrower.profile.select_activity')"
                        />
                    </div>
                    @include('components.site._activity-typed-fields', [
                        'fields' => $fields,
                        'details' => $details,
                        'skipTypes' => ['employed'],
                    ])
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
                    @include('components.site._activity-typed-fields', [
                        'fields' => ['employed' => $fields['employed'] ?? []],
                        'details' => $details,
                        'skipTypes' => [],
                    ])
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
                                <input type="text" name="activity_details[tin_number]" value="{{ $details['tin_number'] ?? '' }}"
                                       autocomplete="off" data-lpignore="true" data-1p-ignore="true"
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
                                <input type="text" name="activity_details[licence_number]" value="{{ $details['licence_number'] ?? '' }}"
                                       autocomplete="off" data-lpignore="true" data-1p-ignore="true"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.licence_authority') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="activity_details[licence_authority]" value="{{ $details['licence_authority'] ?? '' }}"
                                       autocomplete="off" data-lpignore="true" data-1p-ignore="true"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.licence_issued_on') }} <span class="text-red-500">*</span></label>
                                <input type="date" name="activity_details[licence_issued_on]" value="{{ $details['licence_issued_on'] ?? '' }}"
                                       autocomplete="off"
                                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.licence_expires_on') }}</label>
                                <input type="date" name="activity_details[licence_expires_on]" value="{{ $details['licence_expires_on'] ?? '' }}"
                                       autocomplete="off"
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
                <x-site.profile-select
                    name="activity_type"
                    :label="__('borrower.profile.what_do_you_do')"
                    :options="$types"
                    :value="$currentType"
                    :required="true"
                    :placeholder="__('borrower.profile.select_activity')"
                />
            </div>
            @include('components.site._activity-typed-fields', [
                'fields' => $fields,
                'details' => $details,
                'skipTypes' => [],
            ])
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
</div>

@once
    @push('scripts')
    <script>
        function activityForm(fieldMap, initialType) {
            return {
                fieldMap,
                activityType: initialType || '',
                init() {
                    this.syncLocationEnabled();
                },
                hasLocationFields() {
                    return (this.fieldMap[this.activityType] || []).some((f) => f.type === 'region' || f.type === 'district');
                },
                onProfileSelect(detail) {
                    if (! detail || detail.name !== 'activity_type') return;
                    this.activityType = detail.value || '';
                    this.$nextTick(() => this.syncLocationEnabled());
                },
                syncTypedFieldEnabled(el, typeKey) {
                    const on = this.activityType === typeKey;
                    (el.querySelectorAll('input, select, textarea') || []).forEach((input) => {
                        input.disabled = ! on;
                    });
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
            };
        }
    </script>
    @endpush
@endonce
