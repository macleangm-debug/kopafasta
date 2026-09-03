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
        'face_front' => [
            'label' => __('site.affiliate_portal.face_front'),
            'path' => $faces['front'] ?? null,
            'guide' => __('borrower.face_verification_page.angles.front.instruction'),
            'angle' => 'front',
        ],
        'face_left'  => [
            'label' => __('site.affiliate_portal.face_left'),
            'path' => $faces['left'] ?? null,
            'guide' => __('borrower.face_verification_page.angles.left.instruction'),
            'angle' => 'left',
        ],
        'face_right' => [
            'label' => __('site.affiliate_portal.face_right'),
            'path' => $faces['right'] ?? null,
            'guide' => __('borrower.face_verification_page.angles.right.instruction'),
            'angle' => 'right',
        ],
    ];

    if (! $noPhysicalCard) {
        $faceFields['face_holding_id'] = [
            'label' => __('site.affiliate_portal.face_holding_id'),
            'path' => $faces['holding_id'] ?? null,
            'guide' => __('borrower.face_verification_page.angles.holding_nida.instruction'),
            'angle' => 'holding_id',
        ];
    }

    $faceComplete = filled($faces['front'] ?? null)
        && filled($faces['left'] ?? null)
        && filled($faces['right'] ?? null)
        && ($noPhysicalCard || filled($faces['holding_id'] ?? null));

    $cameraSteps = collect($faceFields)->values()->map(fn ($info, $i) => [
        'asset_id' => 0,
        'angle' => $info['angle'],
        'label' => $info['label'],
        'guidance' => $info['guide'],
        'required' => blank($info['path']),
        'inputName' => array_keys($faceFields)[$i],
        'path' => $info['path'],
        'path_url' => filled($info['path']) ? asset('storage/'.$info['path']) : null,
    ])->all();

    foreach (array_keys($faceFields) as $i => $field) {
        $cameraSteps[$i]['inputName'] = $field;
        $cameraSteps[$i]['required'] = true;
    }
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
        :default-open="! $faceComplete">
        <div class="space-y-4">
            <p class="text-sm text-gray-600">{{ __('site.partner_account.face_camera_intro') }}</p>
            <p class="text-xs text-gray-500">{{ __('borrower.face_verification_page.oval_hint') }}</p>

            <form method="POST" action="{{ route($updateRoute, ['section' => 'face']) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PUT')
                <x-site.partner-face-camera :steps="$cameraSteps" :required="! $faceComplete" />
                @if ($noPhysicalCard)
                    <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-3 py-2">{{ __('site.partner_account.face_no_card_note') }}</p>
                @endif
                <x-site.gated-submit
                    class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5"
                    :label="__('site.partner_account.face_submit')"
                    :allow-empty="$faceComplete" />
            </form>
        </div>
    </x-site.profile-section-card>

</x-dynamic-component>
