<x-site.borrower-layout :title="brand_title('Profile — Residence')" active="profile">

    <div>
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.profile.residence_subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'residence'])
        @include('site.borrower.profile._kyc_progress', ['customer' => $customer, 'active' => 'residence'])

        @include('site.borrower.profile._completion')

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'residence']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
              enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-6"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf @method('PUT')
            @if (! empty($returnUrl))
                <input type="hidden" name="return" value="{{ $returnUrl }}">
            @endif

            <h2 class="font-semibold mb-4">{{ __('borrower.profile.residence') }}</h2>
            <x-site.address-fields
                :region="old('region', $customer->region)"
                :district="old('district', $customer->district)"
                :ward="old('ward', $customer->ward)"
                :street="old('street', $customer->street ?? $customer->address)"
            />

            @php $requiresLetter = app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter(); @endphp
            @if ($requiresLetter)
            <div class="mt-6 pt-6 border-t border-gray-100">
                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.profile.residence_letter') }} <span class="text-red-500">*</span></label>
                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.profile.residence_letter_hint') }}</p>
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

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.profile.save_residence') }}</button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
