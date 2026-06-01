<x-site.borrower-layout :title="brand_title(__('borrower.profile.title'))" active="profile">

    <div class="max-w-3xl">
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.profile.kyc_subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'kyc'])

        @include('site.borrower.profile._completion')

        @php
            $kycStatus = $kyc->status ?? 'pending';
            $statusLabel = match ($kycStatus) {
                'verified', 'approved' => [__('borrower.kyc_tab.status.verified'), 'bg-emerald-100 text-emerald-800'],
                'rejected'             => [__('borrower.kyc_tab.status.rejected'), 'bg-red-100 text-red-800'],
                'in_review'            => [__('borrower.kyc_tab.status.in_review'), 'bg-sky-100 text-sky-800'],
                default                => [__('borrower.kyc_tab.status.pending'), 'bg-amber-100 text-amber-800'],
            };
        @endphp

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-xs text-gray-500">{{ __('borrower.kyc_tab.verification_status') }}</p>
                <span class="mt-2 inline-flex text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-xs text-gray-500">{{ __('borrower.kyc_tab.nida_status') }}</p>
                @php
                    $nidaStatus = $customer->nida_verification_status ?? 'unverified';
                    $nidaLabel = match ($nidaStatus) {
                        'verified' => [__('borrower.kyc_tab.status.verified'), 'bg-emerald-100 text-emerald-800'],
                        'multihit' => [__('borrower.nida.status.multihit'), 'bg-sky-100 text-sky-800'],
                        'failed'   => [__('borrower.kyc_tab.status.failed'), 'bg-red-100 text-red-800'],
                        default    => [$customer->national_id ? __('borrower.kyc_tab.not_verified') : __('borrower.kyc_tab.not_provided'), 'bg-amber-100 text-amber-800'],
                    };
                @endphp
                <span class="mt-2 inline-flex text-xs font-semibold rounded-full px-2.5 py-1 {{ $nidaLabel[1] }}">{{ $nidaLabel[0] }}</span>
                @if ($customer->nida_verified_at)
                    <p class="text-[11px] text-gray-500 mt-2">{{ __('borrower.kyc_tab.verified_ago', ['time' => $customer->nida_verified_at->diffForHumans(), 'source' => strtoupper($customer->nida_verified_source ?? 'CRB')]) }}</p>
                @endif
            </div>
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-xs text-gray-500">{{ __('borrower.kyc_tab.face_match') }}</p>
                @php
                    $faceLabel = match ($customer->face_verification_status ?? 'incomplete') {
                        'verified' => [__('borrower.nida.face_status.verified'), 'bg-emerald-100 text-emerald-800'],
                        'pending'  => [__('borrower.nida.face_status.pending'), 'bg-sky-100 text-sky-800'],
                        'rejected' => [__('borrower.nida.face_status.rejected'), 'bg-red-100 text-red-800'],
                        default    => [__('borrower.kyc_tab.status.incomplete'), 'bg-amber-100 text-amber-800'],
                    };
                @endphp
                <span class="mt-2 inline-flex text-xs font-semibold rounded-full px-2.5 py-1 {{ $faceLabel[1] }}">{{ $faceLabel[0] }}</span>
                @if ($customer->face_verified_at)
                    <p class="text-[11px] text-gray-500 mt-2">{{ __('borrower.kyc_tab.approved_ago', ['time' => $customer->face_verified_at->diffForHumans()]) }}</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="font-semibold mb-2">{{ __('borrower.kyc_tab.documents_title') }}</h2>
            <p class="text-sm text-gray-600 mb-4">{{ __('borrower.kyc_tab.documents_hint') }}</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('site.borrower.kyc') }}" class="inline-flex items-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.kyc_tab.manage_documents') }}
                </a>
                <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex items-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.kyc_tab.face_verification') }}
                </a>
                <a href="{{ route('site.borrower.documents') }}" class="inline-flex items-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.kyc_tab.all_documents') }}
                </a>
            </div>
        </div>
    </div>

</x-site.borrower-layout>
