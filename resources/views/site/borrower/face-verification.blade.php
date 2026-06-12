<x-site.borrower-layout :title="brand_title('Face verification')" active="kyc">

    <div>
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">Identity verification</p>
        <h1 class="text-2xl sm:text-3xl font-bold mb-1">Face verification</h1>
        <p class="text-sm text-gray-500 mb-6">
            Confirm you are the same person on your NIDA ID before applying for a loan.
        </p>

        @if ($wizardMode ?? false)
            @include('site.borrower.profile._wizard_nav', ['customer' => $customer, 'currentKey' => $wizardKey ?? 'face', 'wizardMode' => true])
        @endif

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if ($customer->face_verification_status === 'rejected' && $customer->face_rejection_notes)
            <div class="mb-6 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">Previous submission rejected</p>
                <p class="mt-1">{{ $customer->face_rejection_notes }}</p>
                <p class="mt-2 text-xs">Please complete all four steps again.</p>
            </div>
        @endif

        @if ($customer->face_verification_status === 'verified')
            <x-site.face-verification-status :customer="$customer" :photos="$photos" :angles="$angles" />
            <div class="mt-6 text-center bg-white rounded-2xl ring-1 ring-gray-200 p-8">
                <p class="text-sm text-emerald-700 font-medium">Your face verification is approved. You can apply for a loan.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('site.borrower.apply') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                        Apply for a loan →
                    </a>
                    <a href="{{ route('site.borrower.profile', ['section' => 'kyc']) }}" class="inline-flex bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-6 py-2.5 rounded-full text-sm">
                        Back to verification documents
                    </a>
                </div>
            </div>

        @elseif ($customer->face_verification_status === 'pending')
            <x-site.face-verification-status :customer="$customer" :photos="$photos" :angles="$angles" />
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-400">Loan applications unlock after underwriting approves your photos.</p>
                <a href="{{ route('site.borrower.dashboard') }}" class="inline-flex mt-4 bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-6 py-2.5 rounded-full text-sm">
                    Back to dashboard
                </a>
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
