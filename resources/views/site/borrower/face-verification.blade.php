<x-site.borrower-layout :title="brand_title(__('borrower.nida.face_title'))" active="kyc" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.nida.face_title'),
            'subtitle' => __('borrower.nida.face_capture_hint'),
            'customer' => $customer,
            'active' => 'personal',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'face',
        ])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if ($customer->face_verification_status === 'rejected' && $customer->face_rejection_notes)
            <div class="mb-6 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">{{ __('borrower.face_verification_page.rejected_title') }}</p>
                <p class="mt-1">{{ $customer->face_rejection_notes }}</p>
                <p class="mt-2 text-xs">{{ __('borrower.face_verification_page.rejected_hint') }}</p>
            </div>
        @endif

        @if ($customer->face_verification_status === 'verified')
            <x-site.face-verification-status :customer="$customer" :photos="$photos" :angles="$angles" />
            <div class="mt-6 text-center bg-white rounded-2xl ring-1 ring-gray-200 p-8">
                <p class="text-sm text-emerald-700 font-medium">{{ __('borrower.face_verification_page.approved_hint') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('site.products') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                        {{ __('borrower.face_verification_page.apply_cta') }}
                    </a>
                    <a href="{{ route('site.borrower.profile', ['section' => 'kyc']) }}" class="inline-flex bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-6 py-2.5 rounded-full text-sm">
                        {{ __('borrower.face_verification_page.back_to_documents') }}
                    </a>
                </div>
            </div>

        @elseif ($customer->face_verification_status === 'pending')
            <x-site.face-verification-status :customer="$customer" :photos="$photos" :angles="$angles" />
            <div class="mt-6 rounded-2xl bg-white ring-1 ring-gray-200 p-6 text-center">
                <p class="text-sm text-gray-700 font-medium">{{ __('borrower.nida.face_submitted_title') }}</p>
                <p class="text-xs text-gray-500 mt-2">{{ __('borrower.nida.face_submitted_body') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    @if ($wizardMode ?? false)
                        <a href="{{ app(\App\Services\ProfileWizardService::class)->navigation($customer, 'face')['next']['url'] ?? route('site.borrower.profile', ['section' => 'residence', 'wizard' => 1]) }}"
                           class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                            {{ __('borrower.profile_wizard.save_continue') }}
                        </a>
                    @else
                        <a href="{{ route('site.borrower.dashboard') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                            {{ __('borrower.profile_wizard.finish') }}
                        </a>
                    @endif
                </div>
            </div>

        @else
            <x-site.face-verification-wizard
                :customer="$customer"
                :angles="$angles"
                :wizard="$wizard"
                :photos="$photos"
                :steps="$steps"
                :upload-urls="$uploadUrls"
            />
        @endif
    </div>

    @if ($wizardMode ?? false)
        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => true, 'wizardKey' => $wizardKey ?? 'face'])
    @endif

    @stack('scripts')
</x-site.borrower-layout>
