@props([
    'frontName' => 'front',
    'backName' => 'back',
    'frontHostId' => 'nida-front',
    'backHostId' => 'nida-back',
    'required' => true,
    'dbName' => 'kf-nida-card',
    'subjectName' => null,
])

@php
    $steps = [
        [
            'asset_id' => 0,
            'angle' => 'front',
            'label' => __('borrower.document_upload.front'),
            'guidance' => __('borrower.document_upload.nida_front_guide'),
            'required' => true,
            'inputName' => $frontName,
            'path' => null,
            'path_url' => null,
        ],
        [
            'asset_id' => 0,
            'angle' => 'back',
            'label' => __('borrower.document_upload.back'),
            'guidance' => __('borrower.document_upload.nida_back_guide'),
            'required' => true,
            'inputName' => $backName,
            'path' => null,
            'path_url' => null,
        ],
    ];
@endphp

<div x-data="valuationCamera(@js([
        'formMode' => true,
        'dbName' => $dbName,
        'facingMode' => 'environment',
        'orientation' => 'landscape',
        'guideFrame' => 'id-card',
        'subjectName' => $subjectName,
        'subjectLine' => filled($subjectName)
            ? __('borrower.document_upload.requested_for', ['name' => $subjectName])
            : '',
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'steps' => $steps,
    ]))"
     class="space-y-3">
    <input type="file" name="{{ $frontName }}" id="{{ $frontHostId }}" accept="image/jpeg,image/png,image/webp,image/jpg"
           class="sr-only" data-guided-input="{{ $frontName }}">
    <input type="file" name="{{ $backName }}" id="{{ $backHostId }}" accept="image/jpeg,image/png,image/webp,image/jpg"
           class="sr-only" data-guided-input="{{ $backName }}">
    <input type="hidden" value="" x-bind:value="requiredDone() >= requiredTotal() ? '1' : ''" @if ($required) required @endif aria-hidden="true" tabindex="-1" class="sr-only">

    <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-3.5 py-2.5">
        <p class="text-xs font-semibold text-brand leading-snug">{{ __('borrower.document_upload.identity_steps_hint') }}</p>
    </div>
    <button type="button" x-show="pendingRequired().length" @click="start()"
            class="w-full rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3 shadow-sm hover:bg-yellow-400">
        {{ __('borrower.document_upload.nida_start') }}
    </button>

    <div x-show="review || requiredDone() >= requiredTotal()" x-cloak class="space-y-3">
        <div class="grid grid-cols-2 gap-2">
            <template x-for="s in requiredSteps()" :key="key(s)">
                <button type="button" @click="thumbFor(s) ? (preview = thumbFor(s)) : retake(s)"
                        class="rounded-xl ring-1 ring-gray-200 p-2 text-left bg-white">
                    <div class="aspect-[1.586] rounded-lg overflow-hidden bg-gray-50 mb-1.5">
                        <img x-show="thumbFor(s)" :src="thumbFor(s)" alt="" class="h-full w-full object-cover">
                        <div x-show="!thumbFor(s)" class="h-full grid place-items-center text-xs text-gray-400">○</div>
                    </div>
                    <p class="text-xs font-bold truncate" x-text="(thumbFor(s) ? '✓ ' : '') + s.label"></p>
                </button>
            </template>
        </div>
        <button type="button" @click="start()" class="w-full text-sm font-bold text-brand py-2">
            {{ __('site.partner_portal.valuation_retake') }}
        </button>
    </div>

    {{ $slot }}

    <x-site.guided-camera-overlay />

    <template x-teleport="body">
        <div x-show="preview" x-cloak class="fixed inset-0 z-[90] bg-black/80 flex items-center justify-center p-4" @click="preview = null">
            <img :src="preview" alt="" class="max-h-[80vh] max-w-full rounded-xl object-contain">
        </div>
    </template>
</div>
