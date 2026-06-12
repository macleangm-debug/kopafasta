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

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'activity']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-6"
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

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ ($wizardMode ?? false) ? __('borrower.profile_wizard.save_continue') : __('borrower.profile.save_activity') }}</button>
        </form>
        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'activity'])
    </div>

    @stack('scripts')
</x-site.borrower-layout>
