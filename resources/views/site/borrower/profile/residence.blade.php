<x-site.borrower-layout :title="brand_title('Profile — Residence')" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.residence'),
            'subtitle' => __('borrower.profile.residence_subtitle'),
            'customer' => $customer,
            'active' => 'residence',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'residence',
        ])

        @php
            $residenceComplete = app(\App\Services\ProfileCompletionService::class)->isResidenceComplete($customer);
            $residenceStale = in_array('residence', app(\App\Services\KycFreshnessService::class)->sectionsDueForRefresh($customer), true);
            $requiresLetter = app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter();
            $officerPhone = \App\Support\PhoneNumber::format($customer->lga_officer_phone);
            $hasOfficer = filled($customer->lga_officer_name)
                || filled($customer->lga_officer_position)
                || filled($customer->lga_officer_phone);
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

        <x-site.profile-section-card
            section-id="profile-residence"
            icon="🏠"
            :title="__('borrower.profile.residence')"
            :complete="$residenceComplete"
            :empty="! $residenceComplete"
            :allow-overflow="true"
            :default-open="($wizardMode ?? false) || ($editing ?? false) || $errors->any()">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    @foreach ([
                        ['label' => __('borrower.profile.region'), 'value' => $customer->region],
                        ['label' => __('borrower.profile.district'), 'value' => $customer->district],
                        ['label' => __('borrower.profile.ward'), 'value' => $customer->ward],
                        ['label' => __('borrower.profile.street'), 'value' => $customer->street ?: $customer->address, 'span' => true],
                    ] as $field)
                        <div @class(['sm:col-span-2' => ! empty($field['span'])])>
                            <dt class="text-gray-500">{{ $field['label'] }}</dt>
                            @if (filled($field['value']))
                                <dd class="font-medium mt-0.5">{{ $field['value'] }}</dd>
                            @else
                                <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                            @endif
                        </div>
                    @endforeach
                </dl>

                @if ($requiresLetter || $hasOfficer)
                    <div class="mt-5 pt-5 border-t border-gray-100">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('borrower.profile.residence_verification_section') }}</p>
                        <p class="text-xs text-gray-500 mt-1 mb-4">{{ __('borrower.profile.residence_verification_hint') }}</p>

                        @if ($requiresLetter)
                            @if ($residenceLetter ?? null)
                                <x-site.profile-document-field
                                    :document="$residenceLetter"
                                    field-name="residence_letter"
                                    mode="multi"
                                    :label="__('borrower.profile.residence_letter')"
                                    input-host-id="residence-letter-view"
                                />
                            @else
                                <p class="text-sm font-semibold text-amber-700">{{ __('borrower.profile.residence_letter') }} — {{ __('borrower.profile.missing') }}</p>
                                <button type="button" @click="open = true" class="mt-2 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                            @endif
                        @endif

                        <div class="mt-4 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-4">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.profile.residence_signed_by') }}</p>
                            <dl class="mt-3 grid sm:grid-cols-3 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.lga_officer_name') }}</dt>
                                    @if (filled($customer->lga_officer_name))
                                        <dd class="font-medium mt-0.5">{{ $customer->lga_officer_name }}</dd>
                                    @else
                                        <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                                    @endif
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.lga_officer_position') }}</dt>
                                    @if (filled($customer->lga_officer_position))
                                        <dd class="font-medium mt-0.5">{{ $customer->lga_officer_position }}</dd>
                                    @else
                                        <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                                    @endif
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.lga_officer_phone') }}</dt>
                                    @if ($officerPhone)
                                        <dd class="font-medium mt-0.5 tabular-nums">{{ $officerPhone }}</dd>
                                    @else
                                        <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                                    @endif
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'residence']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                      enctype="multipart/form-data"
                      @submit="document.querySelectorAll('[data-phone-input]').forEach((el) => window.syncSitePhoneInput?.(el))">
                    @csrf @method('PUT')
                    @if ($wizardMode ?? false)
                        <input type="hidden" name="wizard" value="1">
                    @endif
                    @if (! empty($returnUrl))
                        <input type="hidden" name="return" value="{{ $returnUrl }}">
                    @endif

                    <x-site.address-fields
                        :region="old('region', $customer->region)"
                        :district="old('district', $customer->district)"
                        :ward="old('ward', $customer->ward)"
                        :street="old('street', $customer->street ?? $customer->address)"
                    />

                    <div class="mt-6 pt-6 border-t border-gray-100 space-y-5">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.residence_verification_section') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.profile.residence_verification_hint') }}</p>
                        </div>

                        @if ($requiresLetter)
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.profile.residence_letter') }} <span class="text-red-500">*</span></label>
                                <x-site.profile-document-field
                                    :document="$residenceLetter ?? null"
                                    field-name="residence_letter"
                                    pages-field-name="residence_letter_pages"
                                    mode="multi"
                                    :label="__('borrower.profile.residence_letter')"
                                    input-host-id="residence-letter-pages"
                                    :labels="[
                                        'hint' => __('borrower.profile.residence_upload_hint'),
                                        'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                        'capturePage' => __('borrower.profile.capture_pages'),
                                    ]"
                                    :required="true"
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
                                variant="rounded"
                                :required="true"
                            />
                        </div>
                    </div>

                    <button type="submit" class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                        {{ ($wizardMode ?? false) ? __('borrower.profile_wizard.save_continue') : __('borrower.profile.save') }}
                    </button>
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'residence'])
    </div>

    @stack('scripts')
</x-site.borrower-layout>
