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
            $focus = request()->query('focus');
            $openActivity = ($wizardMode ?? false) || ($editing ?? false)
                || $errors->hasAny(['activity_type', 'income_range', 'employment_contract', 'activity_details']);
        @endphp

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
            :empty="! $activityComplete"
            :default-open="false"
            :default-edit="$openActivity">
            <x-slot:view>
                @php
                    $activityDetails = is_array($customer->activity_details) ? $customer->activity_details : [];
                    $activityTypeKey = $customer->activity_type ?? $customer->employment_type;
                    $activityFieldDefs = activity_fields_localized()[$activityTypeKey] ?? [];
                    $activityViewRows = [];
                    $activityViewRows[] = [
                        'label' => __('borrower.profile.activity_type'),
                        'value' => $activityLabel,
                    ];
                    foreach ($activityFieldDefs as $field) {
                        $key = $field['key'] ?? null;
                        if (! $key || ($field['type'] ?? '') === 'document') {
                            continue;
                        }
                        $raw = $activityDetails[$key] ?? null;
                        if (! filled($raw)) {
                            continue;
                        }
                        $display = $raw;
                        if (! empty($field['options'][$raw])) {
                            $display = $field['options'][$raw];
                        }
                        $activityViewRows[] = [
                            'label' => $field['label'] ?? $key,
                            'value' => $display,
                        ];
                    }
                    if ($incomeLabel) {
                        $activityViewRows[] = [
                            'label' => __('borrower.profile.income_range'),
                            'value' => $incomeLabel,
                        ];
                    }
                    if ($customer->monthly_income) {
                        $activityViewRows[] = [
                            'label' => __('borrower.profile.monthly_income'),
                            'value' => format_money($customer->monthly_income),
                        ];
                    }
                @endphp
                @if ($activityViewRows === [])
                    <p class="text-sm text-gray-600">{{ __('borrower.profile.section_empty') }}</p>
                    <button type="button" @click="openEdit()" class="mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                @else
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        @foreach ($activityViewRows as $row)
                            <div>
                                <dt class="text-gray-500">{{ $row['label'] }}</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
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
