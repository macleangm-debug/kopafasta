<x-site.borrower-layout title="Profile — KYC — Kopafasta" active="profile">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">Profile</h1>
        <p class="text-sm text-gray-500 mb-6">Identity verification status and documents.</p>

        @include('site.borrower.profile._tabs', ['active' => 'kyc'])

        @php
            $kycStatus = $kyc->status ?? 'pending';
            $statusLabel = match ($kycStatus) {
                'verified', 'approved' => ['Verified', 'bg-emerald-100 text-emerald-800'],
                'rejected'             => ['Rejected', 'bg-red-100 text-red-800'],
                'in_review'            => ['In review', 'bg-sky-100 text-sky-800'],
                default                => ['Pending', 'bg-amber-100 text-amber-800'],
            };
        @endphp

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-xs text-gray-500">Verification status</p>
                <span class="mt-2 inline-flex text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-xs text-gray-500">NIDA status</p>
                <span class="mt-2 inline-flex text-xs font-semibold rounded-full px-2.5 py-1 bg-gray-100 text-gray-700">
                    {{ $customer->national_id ? 'Submitted' : 'Not provided' }}
                </span>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-xs text-gray-500">Face match</p>
                <span class="mt-2 inline-flex text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-800">Pending</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="font-semibold mb-2">KYC documents</h2>
            <p class="text-sm text-gray-600 mb-4">Upload or manage your identity documents for verification.</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('site.borrower.kyc') }}" class="inline-flex items-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    Manage KYC documents
                </a>
                <a href="{{ route('site.borrower.documents') }}" class="inline-flex items-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-full text-sm">
                    All documents
                </a>
            </div>
        </div>
    </div>

</x-site.borrower-layout>
