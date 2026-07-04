<x-site.borrower-layout :title="brand_title('Profile — Residence')" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.account_title'),
            'subtitle' => __('borrower.profile.residence_subtitle'),
            'customer' => $customer,
            'active' => 'residence',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'residence',
        ])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @php
            $editing = ($wizardMode ?? false) || ($editing ?? false);
            $editUrl = route('site.borrower.profile', ['section' => 'residence', 'edit' => 1]);
            $residenceComplete = app(\App\Services\ProfileCompletionService::class)->isResidenceComplete($customer);
            $requiresLetter = app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter();
        @endphp

        <x-site.profile-section-card
            :title="__('borrower.profile.residence')"
            :editing="$editing"
            :edit-url="$editUrl"
            :complete="$residenceComplete">
            @if ($editing)
                <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'residence']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                      enctype="multipart/form-data"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
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

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ ($wizardMode ?? false) ? __('borrower.profile_wizard.save_continue') : __('borrower.profile.save_residence') }}</button>
                        @unless ($wizardMode ?? false)
                            <a href="{{ route('site.borrower.profile', ['section' => 'residence']) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-800 px-3 py-2.5">{{ __('borrower.profile.cancel_edit') }}</a>
                        @endunless
                    </div>
                </form>
            @else
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">{{ __('borrower.profile.region') }}</dt><dd class="font-medium mt-0.5">{{ $customer->region ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('borrower.profile.district') }}</dt><dd class="font-medium mt-0.5">{{ $customer->district ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('borrower.profile.ward') }}</dt><dd class="font-medium mt-0.5">{{ $customer->ward ?: '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('borrower.profile.street') }}</dt><dd class="font-medium mt-0.5">{{ $customer->street ?: $customer->address ?: '—' }}</dd></div>
                </dl>
                @if ($requiresLetter)
                    <p class="mt-4 text-xs text-gray-500">
                        {{ __('borrower.profile.residence_letter') }}:
                        <span class="font-semibold {{ ($residenceLetter ?? null) ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ ($residenceLetter ?? null) ? __('borrower.profile.uploaded') : __('borrower.profile.missing') }}
                        </span>
                    </p>
                @endif
            @endif
        </x-site.profile-section-card>

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'residence'])
    </div>

    @stack('scripts')
</x-site.borrower-layout>
