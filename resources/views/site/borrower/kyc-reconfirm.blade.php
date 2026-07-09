<x-site.borrower-layout :title="brand_title(__('borrower.kyc.reconfirm_title'))" active="profile" content-width="wide">

    <div x-data="{ residenceUnchanged: @js((bool) old('residence_unchanged')) }">
        <h1 class="text-2xl font-bold mb-1">{{ __('borrower.kyc.reconfirm_title') }}</h1>
        <p class="text-sm text-gray-500 mb-6">{{ __('borrower.kyc.reconfirm_intro') }}</p>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('site.borrower.kyc-reconfirm.update') }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" class="space-y-6" enctype="multipart/form-data">
            @csrf @method('PUT')
            @if (! empty($returnUrl))
                <input type="hidden" name="return" value="{{ $returnUrl }}">
            @endif

            @if (in_array('residence', $staleSections ?? [], true))
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-4">{{ __('borrower.kyc.residence') }}</h2>
                <label class="flex items-start gap-3 mb-4 cursor-pointer">
                    <input type="checkbox" name="residence_unchanged" value="1" class="mt-1 rounded border-gray-300 text-amber-500 focus:ring-amber-500"
                           @change="residenceUnchanged = $event.target.checked" @checked(old('residence_unchanged'))>
                    <span class="text-sm text-gray-700">{{ __('borrower.kyc.residence_unchanged_confirm') }}</span>
                </label>
                <div :class="residenceUnchanged ? 'opacity-50 pointer-events-none' : ''">
                    <x-site.address-fields
                        :region="old('region', $customer->region)"
                        :district="old('district', $customer->district)"
                        :ward="old('ward', $customer->ward)"
                        :street="old('street', $customer->street ?? $customer->address)"
                    />
                </div>
                @php $requiresLetter = app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter(); @endphp
                @if ($requiresLetter)
                    <div class="mt-6 pt-6 border-t border-gray-100" x-show="!residenceUnchanged" x-cloak>
                        <p class="text-xs text-gray-500 mb-3">{{ __('borrower.kyc.residence_letter_refresh_hint') }}</p>
                        <x-site.profile-document-field
                            :document="null"
                            field-name="residence_letter"
                            pages-field-name="residence_letter_pages"
                            mode="multi"
                            :label="__('borrower.profile.residence_letter')"
                            input-host-id="reconfirm-residence-letter"
                        />
                    </div>
                @endif
            </div>
            @endif

            @if (in_array('activity', $staleSections ?? [], true))
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-4">{{ __('borrower.kyc.activity') }}</h2>
                <x-site.activity-fields
                    :activity-type="old('activity_type', $customer->activity_type ?? $customer->employment_type)"
                    :activity-details="old('activity_details', $customer->activity_details ?? [])"
                    :income-range="old('income_range', $customer->income_range)"
                    :grouped-sections="true"
                />
            </div>
            @endif

            @if (in_array('documents', $staleSections ?? [], true))
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-2">{{ __('borrower.profile.proof_of_income_title') }}</h2>
                <p class="text-sm text-gray-600 mb-4">{{ __('borrower.kyc.documents_refresh_hint') }}</p>
                <a href="{{ route('site.borrower.profile', ['section' => 'kyc', 'edit' => 1]) }}"
                   class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.profile.edit_section') }}
                </a>
            </div>
            @endif

            @if (in_array('kin', $staleSections ?? [], true))
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-2">{{ __('borrower.profile.kin_info') }}</h2>
                <p class="text-sm text-gray-600 mb-4">{{ __('borrower.kyc.kin_refresh_hint') }}</p>
                <a href="{{ route('site.borrower.profile', ['section' => 'personal', 'edit' => 1, 'focus' => 'kin']).'#next-of-kin' }}"
                   class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.profile.edit_section') }}
                </a>
            </div>
            @endif

            <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                {{ __('borrower.kyc.save') }}
            </button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
