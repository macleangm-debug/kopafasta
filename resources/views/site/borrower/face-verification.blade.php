<x-site.borrower-layout title="Face verification — Kopafasta" active="kyc">

    <div class="max-w-2xl mx-auto">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">Identity verification</p>
        <h1 class="text-2xl sm:text-3xl font-bold mb-1">Face verification</h1>
        <p class="text-sm text-gray-500 mb-6">
            Confirm you are the same person on your NIDA ID before applying for a loan.
        </p>

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
            {{-- Approved --}}
            <div class="text-center bg-white rounded-2xl ring-1 ring-gray-200 p-10">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-100 flex items-center justify-center">
                    <svg class="w-8 h-8 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Verification complete</h2>
                <p class="text-sm text-emerald-700 mt-2">Your face verification is approved. You can apply for a loan.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('site.borrower.apply') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                        Apply for a loan →
                    </a>
                    <a href="{{ route('site.borrower.profile', ['section' => 'kyc']) }}" class="inline-flex bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-6 py-2.5 rounded-full text-sm">
                        Back to KYC
                    </a>
                </div>
            </div>

        @elseif ($customer->face_verification_status === 'pending')
            {{-- Submitted, awaiting review --}}
            <div class="text-center bg-white rounded-2xl ring-1 ring-gray-200 p-10">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-sky-100 flex items-center justify-center">
                    <svg class="w-8 h-8 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Verification complete</h2>
                <p class="text-sm text-gray-600 mt-2">All four photos submitted. Our underwriting team is reviewing them — usually within 24 hours.</p>
                <p class="text-xs text-gray-400 mt-3">You cannot start a loan application until verification is approved.</p>
                <a href="{{ route('site.borrower.dashboard') }}" class="inline-flex mt-6 bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-6 py-2.5 rounded-full text-sm">
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

    @stack('scripts')
</x-site.borrower-layout>
