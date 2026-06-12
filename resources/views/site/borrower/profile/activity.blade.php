<x-site.borrower-layout :title="brand_title('Profile — Activity')" active="profile">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.profile.activity_subtitle'),
            'customer' => $customer,
            'active' => 'activity',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'activity',
        ])

        @php
            $editing = ($wizardMode ?? false) || ($editing ?? false);
            $editUrl = route('site.borrower.profile', ['section' => 'activity', 'edit' => 1]);
            $activityComplete = app(\App\Services\ProfileCompletionService::class)->isActivityComplete($customer);
            $activityLabel = config('activity_profiles.types.'.$customer->activity_type, $customer->activity_type);
            $incomeLabel = config('income_ranges.'.$customer->income_range.'.label', $customer->income_range);
        @endphp

        <x-site.profile-section-card
            :title="__('borrower.profile.activity')"
            :editing="$editing"
            :edit-url="$editUrl"
            :complete="$activityComplete">
            @if ($editing)
                <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'activity']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
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

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ ($wizardMode ?? false) ? __('borrower.profile_wizard.save_continue') : __('borrower.profile.save_activity') }}</button>
                        @unless ($wizardMode ?? false)
                            <a href="{{ route('site.borrower.profile', ['section' => 'activity']) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-800 px-3 py-2.5">{{ __('borrower.profile.cancel_edit') }}</a>
                        @endunless
                    </div>
                </form>
            @else
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">{{ __('borrower.profile.activity_type') }}</dt><dd class="font-medium mt-0.5">{{ $activityLabel ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('borrower.profile.income_range') }}</dt><dd class="font-medium mt-0.5">{{ $incomeLabel ?: '—' }}</dd></div>
                    @if ($customer->monthly_income)
                        <div><dt class="text-gray-500">{{ __('borrower.profile.monthly_income') }}</dt><dd class="font-medium mt-0.5">{{ format_money($customer->monthly_income) }}</dd></div>
                    @endif
                </dl>
            @endif
        </x-site.profile-section-card>

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'activity'])
    </div>

    @stack('scripts')
</x-site.borrower-layout>
