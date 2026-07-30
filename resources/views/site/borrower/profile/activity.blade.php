<x-site.borrower-layout :title="brand_title('Profile — Activity')" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.activity'),
            'subtitle' => __('borrower.profile.activity_subtitle'),
            'customer' => $customer,
            'active' => 'activity',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'activity',
        ])

        @php
            $activityComplete = app(\App\Services\ProfileCompletionService::class)->isActivityComplete($customer);
            $activityStale = in_array('activity', app(\App\Services\KycFreshnessService::class)->sectionsDueForRefresh($customer), true);
            $activityLabel = activity_type_label($customer->activity_type ?? $customer->employment_type);
            $incomeLabel = income_range_label($customer->income_range);
            $saveConfirm = [
                'title' => __('borrower.profile.save_confirm_title'),
                'message' => __('borrower.profile.save_confirm_message'),
                'confirmLabel' => __('borrower.profile.save'),
                'confirmClass' => 'bg-amber-500 hover:bg-amber-400 text-gray-900',
            ];
        @endphp

        @if ($activityStale && $activityComplete)
            <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p>{{ __('borrower.profile.kyc_freshness_banner') }}</p>
                <a href="{{ route('site.borrower.kyc-reconfirm') }}" class="inline-flex shrink-0 font-semibold underline">
                    {{ __('borrower.profile.kyc_freshness_cta') }}
                </a>
            </div>
        @endif

        <x-site.profile-section-card
            section-id="profile-activity"
            icon="💼"
            :title="__('borrower.profile.activity')"
            :complete="$activityComplete"
            :empty="! $activityComplete"
            :default-open="($wizardMode ?? false) || ($editing ?? false)">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('borrower.profile.activity_type') }}</dt>
                        @if ($activityLabel)
                            <dd class="font-medium mt-0.5">{{ $activityLabel }}</dd>
                        @else
                            <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('borrower.profile.income_range') }}</dt>
                        @if ($incomeLabel)
                            <dd class="font-medium mt-0.5">{{ $incomeLabel }}</dd>
                        @else
                            <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                        @endif
                    </div>
                    @if ($customer->monthly_income)
                        <div><dt class="text-gray-500">{{ __('borrower.profile.monthly_income') }}</dt><dd class="font-medium mt-0.5">{{ format_money($customer->monthly_income) }}</dd></div>
                    @endif
                </dl>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'activity']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data"
                      @submit.prevent="window.confirmForm($el, @js($saveConfirm))">
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
                        :income-range="old('income_range', $customer->income_range)"
                        :employment-contract="$employmentContract ?? null"
                        :grouped-sections="true"
                    />

                    <button type="submit" class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                        {{ ($wizardMode ?? false) ? __('borrower.profile_wizard.save_continue') : __('borrower.profile.save') }}
                    </button>
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'activity'])
    </div>

    @stack('scripts')
</x-site.borrower-layout>
