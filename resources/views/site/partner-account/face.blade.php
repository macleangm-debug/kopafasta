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
    $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
    $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);
    $faceWizard = app(\App\Services\PartnerProfileService::class)->faceWizardViewData($partner);
    $faceRoutePrefix = preg_replace('/\.profile$/', '.face-verification', (string) $profileRoute);
    $faceUploadUrls = collect($faceWizard['wizard']['order'])->mapWithKeys(
        fn (string $key) => [$key => route($faceRoutePrefix.'.store', ['angle' => $key])]
    )->all();
    $faceDeleteUrls = collect($faceWizard['wizard']['order'])->mapWithKeys(
        fn (string $key) => [$key => route($faceRoutePrefix.'.destroy', ['angle' => $key])]
    )->all();
    $faceSubmitUrl = route($faceRoutePrefix.'.submit');
@endphp

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile" :hero="false">

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

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
        :complete="$faceWizard['complete']"
        :collapsible="true"
        :default-open="! $faceWizard['complete']">
        <div class="space-y-4">
            <p class="text-sm text-gray-600">{{ __('site.partner_account.face_camera_intro') }}</p>
            @if ($noPhysicalCard)
                <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-3 py-2">{{ __('site.partner_account.face_no_card_note') }}</p>
            @endif

            <x-site.face-verification-wizard
                :customer="$faceWizard['customer']"
                :angles="$faceWizard['angles']"
                :wizard="$faceWizard['wizard']"
                :photos="$faceWizard['photos']"
                :steps="$faceWizard['steps']"
                :upload-urls="$faceUploadUrls"
                :delete-urls="$faceDeleteUrls"
                :submit-url="$faceSubmitUrl"
            />
        </div>
    </x-site.profile-section-card>

</x-dynamic-component>
