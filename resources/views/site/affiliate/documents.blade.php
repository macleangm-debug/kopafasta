@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.affiliate.profile')],
        ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.affiliate.documents')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.affiliate.settings')],
    ];
@endphp

<x-site.affiliate-layout :title="brand_title(__('site.partner_account.documents_title'))" active="profile">

    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.title')"
        :title="__('site.partner_account.documents_title')"
        :subtitle="__('site.affiliate_portal.kyc_documents')"
    />

    <x-site.partner-account-tabs active="documents" :tabs="$accountTabs" />

    @include('site.affiliate._kyc_overview', ['vendor' => $vendor])

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('site.affiliate.documents.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <x-site.profile-section-card
            :title="__('site.affiliate_portal.kyc_documents')"
            :complete="filled($vendor->affiliate_selfie_path) && filled($vendor->affiliate_id_path)"
            :collapsible="false"
            :default-open="true">
            <p class="text-sm text-gray-600 mb-4">{{ __('site.affiliate_portal.kyc_status', ['status' => ucfirst($vendor->affiliate_kyc_status ?? 'pending')]) }}</p>
            <p class="text-xs text-gray-500 mb-4">{{ __('site.affiliate_portal.kyc_camera_hint') }}</p>
            <div class="grid sm:grid-cols-3 gap-4">
                @foreach ([
                    'affiliate_selfie' => ['label' => __('site.affiliate_portal.selfie'), 'path' => $vendor->affiliate_selfie_path, 'capture' => 'user'],
                    'affiliate_id'     => ['label' => __('site.affiliate_portal.national_id'), 'path' => $vendor->affiliate_id_path, 'capture' => 'environment'],
                    'affiliate_photo'  => ['label' => __('site.affiliate_portal.profile_photo'), 'path' => $vendor->affiliate_photo_path, 'capture' => 'user'],
                ] as $field => $meta)
                    <div class="rounded-xl ring-1 ring-gray-200 p-4 bg-white">
                        <label class="block text-xs font-semibold text-gray-700 mb-2">{{ $meta['label'] }}</label>
                        @if ($meta['path'])
                            <img src="{{ asset('storage/'.$meta['path']) }}" alt="" class="w-full h-28 object-cover rounded-lg mb-2 ring-1 ring-gray-100">
                        @else
                            <div class="w-full h-28 rounded-lg bg-gray-50 ring-1 ring-gray-100 grid place-items-center text-gray-400 text-xs mb-2">{{ __('site.affiliate_portal.no_upload') }}</div>
                        @endif
                        <input type="file" name="{{ $field }}" accept="image/*" capture="{{ $meta['capture'] }}"
                               class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold">
                    </div>
                @endforeach
            </div>
            <button type="submit" class="mt-6 bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm shadow-md">
                {{ __('site.affiliate_portal.save_profile') }}
            </button>
        </x-site.profile-section-card>
    </form>

</x-site.affiliate-layout>
