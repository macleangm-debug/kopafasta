<x-site.borrower-layout :title="brand_title('Profile — Residence')" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.residence'),
            'subtitle' => null,
            'customer' => $customer,
            'active' => 'residence',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'residence',
        ])

        @php
            $residenceAddressComplete = filled($customer->region) && filled($customer->district) && filled($customer->street ?: $customer->address);
            $hasResidenceAddress = filled($customer->region) || filled($customer->district) || filled($customer->ward) || filled($customer->street ?: $customer->address);
            $residenceComplete = app(\App\Services\ProfileCompletionService::class)->isResidenceComplete($customer);
            $residenceStale = in_array('residence', app(\App\Services\KycFreshnessService::class)->sectionsDueForRefresh($customer), true);
            $requiresLetter = app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter();
            $hasLetter = ($residenceLetter ?? null) !== null;
            $officerPhone = \App\Support\PhoneNumber::format($customer->lga_officer_phone);
            $hasOfficer = filled($customer->lga_officer_name)
                && filled($customer->lga_officer_position)
                && filled($customer->lga_officer_phone);
            $verificationComplete = (! $requiresLetter || $hasLetter) && $hasOfficer;
            $hasVerificationData = $hasLetter
                || filled($customer->lga_officer_name)
                || filled($customer->lga_officer_position)
                || filled($customer->lga_officer_phone);
            $focus = (string) request()->query('focus', '');
            $solo = request()->boolean('solo') && ! ($wizardMode ?? false);
            $openAddress = ($wizardMode ?? false)
                || $errors->hasAny(['region', 'district', 'ward', 'street'])
                || $focus === 'address';
            $openVerification = ($wizardMode ?? false)
                || $focus === 'verification'
                || $errors->hasAny(['lga_officer_name', 'lga_officer_position', 'lga_officer_phone', 'residence_letter', 'residence_letter_pages']);
        @endphp

        @if ($residenceStale && $residenceComplete)
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

        {{-- Card 1: Address --}}
        <x-site.profile-section-card
            @class(['hidden' => $solo && $focus === 'verification'])
            section-id="profile-residence-address"
            icon="🏠"
            :title="__('borrower.profile.residence_address_card')"
            :complete="$residenceAddressComplete"
            :stale="$residenceStale"
            :empty="! $hasResidenceAddress"
            :allow-overflow="true"
            :default-open="$focus === 'address'"
            :default-edit="$openAddress && $errors->hasAny(['region', 'district', 'ward', 'street'])">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm" data-kf-view-host>
                    @foreach ([
                        ['field' => 'region', 'label' => __('borrower.profile.region'), 'value' => $customer->region],
                        ['field' => 'district', 'label' => __('borrower.profile.district'), 'value' => $customer->district],
                        ['field' => 'ward', 'label' => __('borrower.profile.ward'), 'value' => $customer->ward],
                        ['field' => 'street', 'label' => __('borrower.profile.street'), 'value' => $customer->street ?: $customer->address, 'span' => true],
                    ] as $field)
                        <div @class(['sm:col-span-2' => ! empty($field['span']), 'hidden' => ! filled($field['value'])])>
                            <dt class="text-gray-500">{{ $field['label'] }}</dt>
                            <dd class="font-medium text-gray-900 mt-0.5" data-kf-view-field="{{ $field['field'] }}">{{ filled($field['value']) ? $field['value'] : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-slot:view>
            <x-slot:form>
                <form method="POST"
                      action="{{ route('site.borrower.profile.update', ['section' => 'residence']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                      novalidate
                      @unless ($wizardMode ?? false)
                          data-kf-autosave
                          data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                          data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                          data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                          data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}"
                      @endunless>
                    @csrf @method('PUT')
                    @if ($wizardMode ?? false)
                        <input type="hidden" name="wizard" value="1">
                    @endif
                    @if (! empty($returnUrl))
                        <input type="hidden" name="return" value="{{ $returnUrl }}">
                    @endif
                    <input type="hidden" name="focus" value="address">

                    <x-site.address-fields
                        :region="old('region', $customer->region)"
                        :district="old('district', $customer->district)"
                        :ward="old('ward', $customer->ward)"
                        :street="old('street', $customer->street ?? $customer->address)"
                    />

                    @if ($wizardMode ?? false)
                        <x-site.gated-submit class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm" :label="__('borrower.profile_wizard.save_continue')" />
                    @else
                    @endif
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        {{-- Card 2: Verification --}}
        <div class="mt-5">
            <x-site.profile-section-card
                @class(['hidden' => $solo && $focus === 'address'])
                section-id="profile-residence-verification"
                icon="✅"
                :title="__('borrower.profile.residence_verification_section')"
                :complete="$verificationComplete"
                :stale="$residenceStale"
                :empty="! $hasVerificationData"
                :allow-overflow="true"
            :default-open="$openVerification"
            :default-edit="$errors->hasAny(['lga_officer_name', 'lga_officer_position', 'lga_officer_phone', 'residence_letter', 'residence_letter_pages'])">
                <x-slot:view>
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.profile.residence_verification_hint') }}</p>

                    @if ($requiresLetter)
                        @if ($residenceLetter ?? null)
                            <form method="POST"
                                  action="{{ route('site.borrower.profile.update', ['section' => 'residence']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                                  enctype="multipart/form-data"
                                  data-inline-document-progress
                                  data-saving-message="{{ __('borrower.profile.uploading_documents') }}">
                                @csrf @method('PUT')
                                @if ($wizardMode ?? false)
                                    <input type="hidden" name="wizard" value="1">
                                @endif
                                @if (! empty($returnUrl))
                                    <input type="hidden" name="return" value="{{ $returnUrl }}">
                                @endif
                                <x-site.profile-document-field
                                    :document="$residenceLetter"
                                    field-name="residence_letter"
                                    pages-field-name="residence_letter_pages"
                                    mode="multi"
                                    :label="__('borrower.profile.residence_letter')"
                                    input-host-id="residence-letter-view"
                                    :read-only="false"
                                />
                            </form>
                        @else
                            <p class="text-sm font-semibold text-amber-700">{{ __('borrower.profile.residence_letter') }} — {{ __('borrower.profile.missing') }}</p>
                        @endif
                    @endif

                    <div class="mt-4 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-4">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.profile.residence_signed_by') }}</p>
                        <dl class="mt-3 grid sm:grid-cols-3 gap-3 text-sm" data-kf-view-host>
                            <div @class(['hidden' => ! filled($customer->lga_officer_name)])>
                                <dt class="text-xs text-gray-500">{{ __('borrower.profile.lga_officer_name') }}</dt>
                                <dd class="font-medium mt-0.5" data-kf-view-field="lga_officer_name">{{ $customer->lga_officer_name }}</dd>
                            </div>
                            <div @class(['hidden' => ! filled($customer->lga_officer_position)])>
                                <dt class="text-xs text-gray-500">{{ __('borrower.profile.lga_officer_position') }}</dt>
                                <dd class="font-medium mt-0.5" data-kf-view-field="lga_officer_position">{{ $customer->lga_officer_position }}</dd>
                            </div>
                            <div @class(['hidden' => ! $officerPhone])>
                                <dt class="text-xs text-gray-500">{{ __('borrower.profile.lga_officer_phone') }}</dt>
                                <dd class="font-medium mt-0.5 tabular-nums" data-kf-view-field="lga_officer_phone">{{ $officerPhone }}</dd>
                            </div>
                        </dl>
                    </div>
                </x-slot:view>
                <x-slot:form>
                    <form method="POST"
                          action="{{ route('site.borrower.profile.update', ['section' => 'residence']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                          enctype="multipart/form-data"
                          @unless ($wizardMode ?? false)
                              data-kf-autosave
                              data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                              data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                              data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                              data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}"
                              data-kf-autosave-uploading="{{ __('borrower.document_upload.uploading') }}"
                          @endunless
                          data-inline-document-progress
                          data-saving-message="{{ __('borrower.profile.uploading_documents') }}"
                          novalidate
                          @submit="document.querySelectorAll('[data-phone-input]').forEach((el) => window.syncSitePhoneInput?.(el))">
                        @csrf @method('PUT')
                        @if ($wizardMode ?? false)
                            <input type="hidden" name="wizard" value="1">
                        @endif
                        @if (! empty($returnUrl))
                            <input type="hidden" name="return" value="{{ $returnUrl }}">
                        @endif
                        <input type="hidden" name="focus" value="verification">
                        {{-- Keep existing address on verification-only saves --}}
                        <input type="hidden" name="region" value="{{ old('region', $customer->region) }}">
                        <input type="hidden" name="district" value="{{ old('district', $customer->district) }}">
                        <input type="hidden" name="ward" value="{{ old('ward', $customer->ward) }}">
                        <input type="hidden" name="street" value="{{ old('street', $customer->street ?? $customer->address) }}">

                        <p class="text-sm text-gray-600 mb-5">{{ __('borrower.profile.residence_verification_hint') }}</p>

                        @if ($requiresLetter)
                            <div class="mb-5 rounded-2xl ring-1 ring-brand/15 bg-white p-4 space-y-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.residence_letter') }} <span class="text-red-500">*</span></p>
                                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.profile.residence_upload_hint') }}</p>
                                </div>
                                <x-site.profile-document-field
                                    :document="$residenceLetter ?? null"
                                    field-name="residence_letter"
                                    pages-field-name="residence_letter_pages"
                                    mode="multi"
                                    :label="__('borrower.profile.residence_letter')"
                                    input-host-id="residence-letter-pages"
                                    :labels="[
                                        'hint' => __('borrower.profile.residence_upload_hint'),
                                        'uploadFile' => __('borrower.profile.upload_residence_letter'),
                                        'capturePage' => __('borrower.profile.capture_residence_letter'),
                                    ]"
                                    :required="! ($residenceLetter ?? null)"
                                />
                            </div>
                        @endif

                        <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-4 space-y-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.lga_officer_section') }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ __('borrower.profile.lga_officer_hint') }}</p>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.profile.lga_officer_name') }} <span class="text-red-500">*</span></label>
                                    <input type="text" name="lga_officer_name" required
                                           value="{{ old('lga_officer_name', $customer->lga_officer_name) }}"
                                           class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-base outline-none">
                                    @error('lga_officer_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.profile.lga_officer_position') }} <span class="text-red-500">*</span></label>
                                    <input type="text" name="lga_officer_position" required
                                           value="{{ old('lga_officer_position', $customer->lga_officer_position) }}"
                                           placeholder="{{ __('borrower.profile.lga_officer_position_placeholder') }}"
                                           class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-base outline-none">
                                    @error('lga_officer_position')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <x-site.phone-input
                                name="lga_officer_phone"
                                :label="__('borrower.profile.lga_officer_phone')"
                                :value="old('lga_officer_phone', $customer->lga_officer_phone)"
                                :locked-country="$customer->country_code ?? 'TZ'"
                                variant="rounded"
                                :required="true"
                            />
                        </div>

                        @if ($wizardMode ?? false)
                            <x-site.gated-submit
                                class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm"
                                :label="__('borrower.profile_wizard.save_continue')"
                                :allow-empty="$verificationComplete"
                            />
                        @else
                        @endif
                    </form>
                </x-slot:form>
            </x-site.profile-section-card>
        </div>

        @unless (request()->boolean('solo'))
            @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'residence'])
        @endunless
    </div>

    @stack('scripts')
</x-site.borrower-layout>
