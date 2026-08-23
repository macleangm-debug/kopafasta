@props([
    'partner',
    'portal',
    'profileRoute',
    'updateRoute',
    'layoutComponent',
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'accountTabs' => [],
])

@php
    $meta = $partner->metadata ?? [];
    $faces = is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];
    $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
    $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);

    if (empty($faces['front']) && $partner instanceof \App\Models\Partner && filled($partner->affiliate_selfie_path)) {
        $faces['front'] = $partner->affiliate_selfie_path;
    }

    $faceFields = [
        'face_front' => ['label' => __('site.affiliate_portal.face_front'), 'path' => $faces['front'] ?? null],
        'face_left'  => ['label' => __('site.affiliate_portal.face_left'), 'path' => $faces['left'] ?? null],
        'face_right' => ['label' => __('site.affiliate_portal.face_right'), 'path' => $faces['right'] ?? null],
    ];

    if (! $noPhysicalCard) {
        $faceFields['face_holding_id'] = ['label' => __('site.affiliate_portal.face_holding_id'), 'path' => $faces['holding_id'] ?? null];
    }

    $faceComplete = filled($faces['front'] ?? null)
        && filled($faces['left'] ?? null)
        && filled($faces['right'] ?? null)
        && ($noPhysicalCard || filled($faces['holding_id'] ?? null));
@endphp

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile">

    <x-site.borrower-page-header :eyebrow="$eyebrow" :title="$title" :subtitle="$subtitle" share="kf-psec-face" />

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'face',
        'profileRoute' => $profileRoute,
    ])

    <x-site.profile-section-card
        section-id="section-face"
        icon="🤳"
        :title="__('site.partner_account.face_section')"
        :complete="$faceComplete"
        :collapsible="true"
        :default-open="false">
        <p class="text-sm text-gray-600 mb-1">{{ __('site.partner_account.face_camera_intro') }}</p>
        <p class="text-xs text-gray-500 mb-1">{{ __('borrower.face_verification_page.intro_short') }}</p>
        <p class="text-xs text-gray-500 mb-4">{{ __('borrower.face_verification_page.oval_hint') }}</p>
        <form method="POST" action="{{ route($updateRoute, ['section' => 'face']) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach ($faceFields as $field => $info)
                    <div class="rounded-xl ring-1 ring-gray-200 p-4 bg-white">
                        <label class="block text-xs font-semibold text-gray-700 mb-2">{{ $info['label'] }}</label>
                        @if ($info['path'])
                            <img src="{{ asset('storage/'.$info['path']) }}" alt="" class="w-full h-28 object-cover rounded-lg mb-2 ring-1 ring-gray-100">
                        @else
                            <div class="w-full h-28 rounded-lg bg-gray-50 ring-1 ring-gray-100 grid place-items-center text-gray-400 text-xs mb-2">{{ __('site.affiliate_portal.no_upload') }}</div>
                        @endif
                        <x-site.single-image-document-upload
                            :name="$field"
                            facing="user"
                            :camera-only="true"
                            :required="! filled($info['path'])"
                        />
                        @error($field)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            @if ($noPhysicalCard)
                <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-3 py-2">{{ __('site.partner_account.face_no_card_note') }}</p>
            @endif
            <x-site.gated-submit class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm" :label="__('site.partner_account.save_profile')" :allow-empty="$faceComplete" />
        </form>
    </x-site.profile-section-card>

</x-dynamic-component>
