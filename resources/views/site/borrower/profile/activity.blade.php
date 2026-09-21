<x-site.borrower-layout :title="brand_title('Profile — Activity')" active="profile" content-width="wide">

    <div class="space-y-4">
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.activity'),
            'subtitle' => null,
            'customer' => $customer,
            'active' => 'activity',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'activity',
        ])

        @php
            $activityComplete = app(\App\Services\ProfileCompletionService::class)->isActivityFieldsComplete($customer);
            $activityStale = in_array('activity', app(\App\Services\KycFreshnessService::class)->sectionsDueForRefresh($customer), true);
            $activityLabel = activity_type_label($customer->activity_type ?? $customer->employment_type);
            $incomeLabel = income_range_label($customer->income_range);
            $activityDetails = is_array($customer->activity_details) ? $customer->activity_details : [];
            $hasActivityData = filled($customer->activity_type ?? $customer->employment_type)
                || filled($customer->income_range)
                || collect($activityDetails)->contains(fn ($v, $k) => ! str_starts_with((string) $k, '_') && filled($v) && ! is_array($v));
            $focus = request()->query('focus');
            $kycFlags = \App\Models\Setting::group('kyc');
            $requireTin = (bool) ($kycFlags['require_tin'] ?? false);
            $requireLicence = (bool) ($kycFlags['require_business_licence'] ?? false);
            $isBusinessOwner = ($customer->activity_type ?? $customer->employment_type) === 'business_owner';
            $openActivity = ($wizardMode ?? false) || ($editing ?? false)
                || $errors->hasAny(['activity_type', 'income_range', 'employment_contract', 'activity_details'])
                || request()->boolean('edit')
                || filled(request()->query('field'))
                || $focus === 'activity';        @endphp

        @if ($activityStale && $activityComplete)
            <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p>{{ __('borrower.profile.kyc_freshness_banner') }}</p>
                <a href="{{ route('site.borrower.kyc-reconfirm') }}" class="inline-flex shrink-0 font-semibold underline">
                    {{ __('borrower.profile.kyc_freshness_cta') }}
                </a>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @php
            $solo = request()->boolean('solo') && ! ($wizardMode ?? false);
            $soloFocus = (string) ($focus ?? request()->query('focus', ''));
        @endphp

        {{-- 1. Activity details --}}
        <x-site.profile-section-card
            @class(['hidden' => $solo && ! in_array($soloFocus, ['activity', ''], true)])
            section-id="profile-activity"
            icon="💼"
            :title="__('borrower.profile.activity')"
            :complete="$activityComplete"
            :stale="$activityStale"
            :empty="! $hasActivityData"
            :default-open="false"
            :default-edit="$openActivity">
            <x-slot:view>
                @php
                    $activityDetails = is_array($customer->activity_details) ? $customer->activity_details : [];
                    $activityTypeKey = $customer->activity_type ?? $customer->employment_type;
                    $activityFieldDefs = activity_fields_localized()[$activityTypeKey] ?? [];
                    $activityViewRows = [];
                    $seenDetailKeys = [];
                    $activityViewRows[] = [
                        'field' => 'activity_type',
                        'label' => __('borrower.profile.activity_type'),
                        'value' => $activityLabel,
                    ];
                    foreach ($activityFieldDefs as $field) {
                        $key = $field['key'] ?? null;
                        if (! $key || ($field['type'] ?? '') === 'document') {
                            continue;
                        }
                        $raw = $activityDetails[$key] ?? null;
                        $seenDetailKeys[$key] = true;
                        $display = filled($raw) && ! is_array($raw) ? $raw : null;
                        if ($display !== null && ! empty($field['options'][$raw])) {
                            $display = $field['options'][$raw];
                        }
                        $activityViewRows[] = [
                            'field' => 'activity_details['.$key.']',
                            'label' => $field['label'] ?? $key,
                            'value' => $display,
                            'label_map' => ! empty($field['options']) ? $field['options'] : null,
                        ];
                    }
                    foreach ($activityDetails as $key => $raw) {
                        if (isset($seenDetailKeys[$key]) || is_array($raw) || ! filled($raw)) {
                            continue;
                        }
                        if (str_starts_with((string) $key, '_') || in_array($key, ['income_proof_method'], true)) {
                            continue;
                        }
                        $activityViewRows[] = [
                            'field' => 'activity_details['.$key.']',
                            'label' => display_label((string) $key, 'activity'),
                            'value' => $raw,
                        ];
                    }
                    $activityViewRows[] = [
                        'field' => 'income_range',
                        'label' => __('borrower.profile.income_range'),
                        'value' => $incomeLabel,
                        'label_map' => income_range_select_options(),
                    ];
                    $hasAnyActivityValue = collect($activityViewRows)->contains(fn ($row) => filled($row['value'] ?? null));
                @endphp
                <dl class="grid sm:grid-cols-2 gap-4 text-sm" data-kf-view-host data-kf-activity-view @class(['hidden' => ! $hasAnyActivityValue])>
                    @foreach ($activityViewRows as $row)
                        <div @class(['hidden' => ! filled($row['value'] ?? null) && ($row['field'] ?? '') !== 'activity_type' && ($row['field'] ?? '') !== 'income_range'])>
                            <dt class="text-gray-500">{{ $row['label'] }}</dt>
                            <dd class="font-medium text-gray-900 mt-0.5"
                                @if (! empty($row['field'])) data-kf-view-field="{{ $row['field'] }}" @endif
                                @if (! empty($row['label_map'])) data-kf-view-label-map='@json($row['label_map'])' @endif
                            >{{ filled($row['value'] ?? null) ? $row['value'] : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($isBusinessOwner && ($requireTin || $requireLicence))
                    <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'activity']) }}" enctype="multipart/form-data" class="mt-4 space-y-4"
                          data-kf-autosave
                          data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                          data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                          data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                          data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}"
                          data-inline-document-progress
                          data-saving-message="{{ __('borrower.profile.uploading') }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="activity_type" value="{{ $customer->activity_type ?? $customer->employment_type }}">
                        <input type="hidden" name="income_range" value="{{ $customer->income_range }}">
                        @foreach ($activityDetails as $detailKey => $detailValue)
                            @if (is_scalar($detailValue) && filled($detailValue) && ! str_starts_with((string) $detailKey, '_'))
                                <input type="hidden" name="activity_details[{{ $detailKey }}]" value="{{ $detailValue }}">
                            @endif
                        @endforeach
                        @if ($requireTin)
                            <x-site.profile-document-field
                                :document="$tinCertificate ?? null"
                                field-name="tin_certificate"
                                pages-field-name="tin_certificate_pages"
                                mode="multi"
                                :label="__('borrower.profile.tin_certificate')"
                                input-host-id="tin-certificate-view"
                            />
                        @endif
                        @if ($requireLicence)
                            <x-site.profile-document-field
                                :document="$businessLicense ?? null"
                                field-name="business_license"
                                pages-field-name="business_license_pages"
                                mode="multi"
                                :label="__('borrower.profile.business_license')"
                                input-host-id="business-license-view"
                            />
                        @endif
                    </form>
                @endif
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'activity']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data"
                      @unless ($wizardMode ?? false)
                          data-kf-autosave
                          data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                          data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                          data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                          data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}"
                          data-kf-autosave-uploading="{{ __('borrower.document_upload.uploading') }}"
                          data-kf-activity-type-map='@json(activity_type_options())'
                          data-kf-income-map='@json(income_range_select_options())'
                      @endunless
                      data-inline-document-progress data-saving-message="{{ __('borrower.profile.uploading') }}">
                    @csrf @method('PUT')
                    @if ($wizardMode ?? false)
                        <input type="hidden" name="wizard" value="1">
                    @endif
                    @if (! empty($returnUrl))
                        <input type="hidden" name="return" value="{{ $returnUrl }}">
                    @endif

                    @error('employment_contract')<p class="text-xs text-red-600 mb-3">{{ $message }}</p>@enderror

                    <x-site.activity-fields
                        :activity-type="old('activity_type', $customer->activity_type ?? $customer->employment_type)"
                        :activity-details="old('activity_details', $customer->activity_details ?? [])"
                        :income-range="old('income_range', normalize_income_range_key($customer->income_range) ?? $customer->income_range)"
                        :employment-contract="$employmentContract ?? null"
                        :tin-certificate="$tinCertificate ?? null"
                        :business-license="$businessLicense ?? null"
                        :require-tin="$requireTin"
                        :require-licence="$requireLicence"
                        :grouped-sections="true"
                    />

                    @if ($wizardMode ?? false)
                        <x-site.gated-submit class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm" :label="__('borrower.profile_wizard.save_continue')" />
                    @endif
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        {{-- 2. Account / bank statement (proof of income) --}}
        <div @class(['hidden' => $solo && $soloFocus === 'additional'])>
            @include('site.borrower.profile._income_statement_card')
        </div>

        {{-- 3. Additional documents (type dropdown → attach) --}}
        <div @class(['hidden' => $solo && in_array($soloFocus, ['income', 'statement', 'documents'], true)])>
            @include('site.borrower.profile._additional_documents_card')
        </div>

        @unless (request()->boolean('solo'))
            @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'activity'])
        @endunless
    </div>

    @stack('scripts')
</x-site.borrower-layout>
