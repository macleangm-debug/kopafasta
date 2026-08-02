@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.affiliate.profile')],
        ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.affiliate.documents')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.affiliate.settings')],
    ];
    $faces = $vendor->metadata['face_captures'] ?? [];
    $faceComplete = filled($faces['front'] ?? null)
        && filled($faces['left'] ?? null)
        && filled($faces['right'] ?? null)
        && filled($faces['holding_id'] ?? null);
    $idComplete = filled($vendor->affiliate_id_path);
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
            :title="__('site.affiliate_portal.face_section')"
            :complete="$faceComplete"
            :collapsible="true"
            :default-open="true">
            <p class="text-sm text-gray-600 mb-4">{{ __('site.affiliate_portal.face_hint') }}</p>
            <p class="text-xs text-gray-500 mb-4">{{ __('site.affiliate_portal.kyc_camera_hint') }}</p>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach ([
                    'face_front' => ['label' => __('site.affiliate_portal.face_front'), 'path' => $faces['front'] ?? null, 'capture' => 'user'],
                    'face_left' => ['label' => __('site.affiliate_portal.face_left'), 'path' => $faces['left'] ?? null, 'capture' => 'user'],
                    'face_right' => ['label' => __('site.affiliate_portal.face_right'), 'path' => $faces['right'] ?? null, 'capture' => 'user'],
                    'face_holding_id' => ['label' => __('site.affiliate_portal.face_holding_id'), 'path' => $faces['holding_id'] ?? null, 'capture' => 'user'],
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
        </x-site.profile-section-card>

        <x-site.profile-section-card
            :title="__('site.affiliate_portal.national_id')"
            :complete="$idComplete"
            :collapsible="true"
            :default-open="true">
            <p class="text-sm text-gray-600 mb-4">{{ __('site.affiliate_portal.national_id_hint') }}</p>
            <div class="rounded-xl ring-1 ring-gray-200 p-4 bg-white max-w-md">
                @if ($vendor->affiliate_id_path)
                    <img src="{{ asset('storage/'.$vendor->affiliate_id_path) }}" alt="" class="w-full h-36 object-cover rounded-lg mb-2 ring-1 ring-gray-100">
                @else
                    <div class="w-full h-36 rounded-lg bg-gray-50 ring-1 ring-gray-100 grid place-items-center text-gray-400 text-xs mb-2">{{ __('site.affiliate_portal.no_upload') }}</div>
                @endif
                <input type="file" name="affiliate_id" accept="image/*" capture="environment"
                       class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold">
            </div>
        </x-site.profile-section-card>

        <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm shadow-md">
            {{ __('site.affiliate_portal.save_profile') }}
        </button>
    </form>

</x-site.affiliate-layout>
