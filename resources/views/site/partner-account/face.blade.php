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
        ],
        'face_left'  => [
            'label' => __('site.affiliate_portal.face_left'),
            'path' => $faces['left'] ?? null,
            'guide' => __('borrower.face_verification_page.angles.left.instruction'),
        ],
        'face_right' => [
            'label' => __('site.affiliate_portal.face_right'),
            'path' => $faces['right'] ?? null,
            'guide' => __('borrower.face_verification_page.angles.right.instruction'),
        ],
    ];

    if (! $noPhysicalCard) {
        $faceFields['face_holding_id'] = [
            'label' => __('site.affiliate_portal.face_holding_id'),
            'path' => $faces['holding_id'] ?? null,
            'guide' => __('borrower.face_verification_page.angles.holding_nida.instruction'),
        ];
    }

    $faceComplete = filled($faces['front'] ?? null)
        && filled($faces['left'] ?? null)
        && filled($faces['right'] ?? null)
        && ($noPhysicalCard || filled($faces['holding_id'] ?? null));

    $faceSteps = [];
    foreach ($faceFields as $field => $info) {
        $faceSteps[] = $info + ['field' => $field];
    }
    $firstOpen = collect($faceSteps)->search(fn ($step) => blank($step['path'] ?? null));
    $firstOpen = $firstOpen === false ? 0 : (int) $firstOpen;
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
        <div class="space-y-4"
             x-data="{
                step: {{ $firstOpen }},
                capturing: {{ $faceComplete ? 'false' : 'true' }},
                preview: null,
                total: {{ count($faceSteps) }},
                complete: {{ $faceComplete ? 'true' : 'false' }},
                advance(index) {
                    if (this.complete || index >= this.total - 1) {
                        this.$refs.faceForm.requestSubmit();
                        return;
                    }
                    this.step = index + 1;
                }
             }"
             @photo-carousel-retake="capturing = true; step = $event.detail.index"
             @photo-carousel-open="preview = $event.detail.url">
            <p class="text-sm text-gray-600">{{ __('site.partner_account.face_camera_intro') }}</p>
            <p class="text-xs text-gray-500">{{ __('borrower.face_verification_page.oval_hint') }}</p>

            @if ($faceComplete)
                <div x-show="!capturing" class="space-y-3">
                    <x-site.photo-carousel :photos="collect($faceSteps)->map(fn ($step, $i) => [
                        'url' => filled($step['path'] ?? null) ? asset('storage/'.$step['path']) : null,
                        'label' => $step['label'],
                        'index' => $i,
                    ])->all()" />
                </div>
            @endif

            <form x-ref="faceForm" x-show="capturing || ! {{ $faceComplete ? 'true' : 'false' }}" method="POST"
                  action="{{ route($updateRoute, ['section' => 'face']) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PUT')
                <p class="text-xs font-semibold uppercase tracking-widest text-brand"
                   x-text="'{{ __('site.partner_account.face_step', ['current' => '__C__', 'total' => count($faceSteps)]) }}'.replace('__C__', String(step + 1))"></p>
                <div class="flex items-center gap-1.5" role="list">
                    @foreach ($faceSteps as $i => $step)
                        <button type="button" @click="step = {{ $i }}"
                                class="size-2.5 rounded-full"
                                :class="step === {{ $i }} ? 'bg-brand scale-125' : '{{ filled($step['path']) ? 'bg-emerald-500' : 'bg-gray-300' }}'"
                                aria-label="{{ $step['label'] }}"></button>
                    @endforeach
                </div>
                @foreach ($faceSteps as $i => $step)
                    <div x-show="step === {{ $i }}" x-cloak class="space-y-3">
                        <h3 class="text-lg font-bold text-gray-900">{{ $step['label'] }}</h3>
                        <p class="text-sm text-gray-500">{{ $step['guide'] }}</p>
                        <div @doc-preview="if ($event.detail.filled && $event.detail.name === @js($step['field'])) advance({{ $i }})">
                            <x-site.single-image-document-upload
                                :name="$step['field']"
                                :input-host-id="$step['field'].'-host'"
                                facing="user"
                                :camera-only="true"
                                :required="blank($step['path'])"
                                :guide="$step['guide']"
                                :show-oval="true"
                            />
                        </div>
                        @error($step['field'])<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        <div class="flex items-center justify-between gap-3">
                            @if ($i > 0)
                                <button type="button" @click="step = {{ $i - 1 }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">{{ __('site.partner_portal.valuation_photo_back') }}</button>
                            @elseif ($faceComplete)
                                <button type="button" @click="capturing = false" class="text-sm font-semibold text-gray-600 hover:text-gray-900">{{ __('site.partner_portal.valuation_photo_back') }}</button>
                            @else
                                <span></span>
                            @endif
                        </div>
                    </div>
                @endforeach
                @if ($noPhysicalCard)
                    <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-3 py-2">{{ __('site.partner_account.face_no_card_note') }}</p>
                @endif
            </form>

            <div x-show="preview" x-cloak class="fixed inset-0 z-[80] bg-black/80 flex items-center justify-center p-4" @click.self="preview = null">
                <img :src="preview" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl">
            </div>
        </div>
    </x-site.profile-section-card>

</x-dynamic-component>
