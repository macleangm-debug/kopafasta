{{-- One National ID holder: face-style 2-step Front→Back directive journey. --}}
@props([
    'nidaFront' => null,
    'nidaBack' => null,
    'nidaSaved' => false,
    'nationalId' => null,
    'returnUrl' => null,
    'uploadsComplete' => false,
])

@php
    $hasFront = filled($nidaFront?->file_path);
    $hasBack = filled($nidaBack?->file_path);
    $updateAction = route('site.borrower.profile.update', ['section' => 'personal'])
        .(! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '');
    $steps = [
        [
            'key' => 'front',
            'field' => 'national_id_front',
            'hostId' => 'nida-front-directive',
            'label' => __('borrower.profile.national_id_side_front'),
            'instruction' => __('borrower.profile.national_id_next_front'),
            'guide' => __('borrower.document_upload.nida_front_guide'),
            'done' => $hasFront,
            'previewUrl' => $hasFront ? asset('storage/'.$nidaFront->file_path) : null,
        ],
        [
            'key' => 'back',
            'field' => 'national_id_back',
            'hostId' => 'nida-back-directive',
            'label' => __('borrower.profile.national_id_side_back'),
            'instruction' => __('borrower.profile.national_id_next_back'),
            'guide' => __('borrower.document_upload.nida_back_guide'),
            'done' => $hasBack,
            'previewUrl' => $hasBack ? asset('storage/'.$nidaBack->file_path) : null,
        ],
    ];
@endphp

<div
    class="space-y-4"
    data-kf-national-id-holder
    x-data="nidaDirectiveJourney({
        steps: @js($steps),
        updateUrl: @js($updateAction),
        csrf: @js(csrf_token()),
        nationalId: @js($nidaSaved ? $nationalId : null),
        returnUrl: @js($returnUrl),
        savingLabel: @js(__('borrower.document_upload.saving')),
        savedLabel: @js(__('borrower.document_upload.saved')),
        failLabel: @js(__('borrower.document_upload.could_not_save').' · '.__('borrower.document_upload.retry')),
    })"
>
    <p class="text-xs text-gray-500">{{ __('borrower.profile.national_id_holder_hint') }}</p>
    <p x-show="saving" x-cloak
       class="sticky top-2 z-10 inline-flex items-center gap-2 rounded-full bg-brand text-white px-3 py-1.5 text-sm font-bold shadow-md"
       data-kf-nida-saving>
        <span class="size-3.5 rounded-full border-2 border-white/30 border-t-white animate-spin" aria-hidden="true"></span>
        {{ __('borrower.document_upload.saving') }}
    </p>

    {{-- Complete: both sides saved --}}
    <div x-show="phase === 'complete'" x-cloak class="space-y-4">
        <div class="grid sm:grid-cols-2 gap-3">
            <template x-for="step in steps" :key="'done-' + step.key">
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                    <p class="text-xs text-gray-500" x-text="step.label"></p>
                    <div class="mt-2 flex items-start gap-3">
                        <button type="button"
                                class="h-28 w-24 shrink-0 rounded-lg ring-1 ring-brand/15 overflow-hidden bg-white cursor-zoom-in block shadow-sm relative"
                                @click="step.previewUrl && window.kfSiteOpenDocumentPreview(step.previewUrl, step.label, 'image')">
                            <img :src="step.previewUrl" :alt="step.label" class="absolute inset-0 w-full h-full object-cover">
                        </button>
                        <button type="button"
                                @click="beginReplace(step.key)"
                                class="text-[11px] font-semibold px-2 py-2 rounded-xl bg-brand-muted/60 hover:bg-brand-muted text-brand">
                            {{ __('borrower.profile.replace_document') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="step in steps" :key="'view-' + step.key">
                <button type="button"
                        x-show="step.previewUrl"
                        @click="window.kfSiteOpenDocumentPreview(step.previewUrl, step.label, 'image')"
                        class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                    <span x-text="'{{ __('borrower.profile.view_document') }} · ' + step.label"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Journey: sequential Front → Back (face-style stepper) --}}
    <div x-show="phase === 'journey'" class="space-y-4">
        <nav aria-label="{{ __('borrower.profile.id_images_title') }}">
            <ol class="flex items-center gap-0">
                <template x-for="(step, i) in steps" :key="'rail-' + step.key">
                    <li class="flex items-center min-w-0" :class="i < steps.length - 1 ? 'flex-1' : ''">
                        <div class="flex flex-col items-center gap-1.5 shrink-0">
                            <span class="size-8 rounded-full grid place-items-center text-xs font-bold transition ring-2"
                                  :class="step.done
                                      ? 'bg-emerald-500 text-white ring-emerald-500'
                                      : (i === stepIndex
                                          ? 'bg-brand text-white ring-brand shadow-sm'
                                          : 'bg-white text-gray-400 ring-gray-200')">
                                <span x-show="!step.done" x-text="i + 1"></span>
                                <span x-show="step.done" aria-hidden="true">✓</span>
                            </span>
                            <span class="text-[10px] uppercase tracking-widest font-semibold max-w-[4.5rem] text-center truncate"
                                  :class="step.done ? 'text-emerald-700' : (i === stepIndex ? 'text-brand' : 'text-gray-400')"
                                  x-text="step.label"></span>
                        </div>
                        <div x-show="i < steps.length - 1"
                             class="mx-1.5 sm:mx-2 h-px flex-1 min-w-[0.75rem] transition"
                             :class="step.done ? 'bg-emerald-400' : 'bg-gray-200'"
                             aria-hidden="true"></div>
                    </li>
                </template>
            </ol>
        </nav>

        <div class="rounded-2xl ring-1 ring-gray-200 bg-white px-5 py-5 space-y-4">
            <div>
                <p class="text-[11px] uppercase tracking-widest text-brand font-bold"
                   x-text="@js(__('borrower.face_verification_page.shot_of', ['current' => '__C__', 'total' => '__T__'])).replace('__C__', String(stepIndex + 1)).replace('__T__', String(steps.length))"></p>
                <p class="mt-1 text-sm font-semibold text-gray-900" x-text="current?.instruction"></p>
            </div>

            {{-- Front step --}}
            <div x-show="stepIndex === 0" class="space-y-3">
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm">
                    <p class="text-sm font-bold text-gray-900">{{ __('borrower.profile.national_id_side_front') }}</p>
                    <x-site.document-source-picker host-id="nida-front-directive" />
                </div>
                <x-site.single-image-document-upload
                    name="national_id_front"
                    input-host-id="nida-front-directive"
                    facing="environment"
                    guide-frame="id-card"
                    :guide="__('borrower.document_upload.nida_front_guide')"
                    :source-driven="true"
                />
            </div>

            {{-- Back step --}}
            <div x-show="stepIndex === 1" x-cloak class="space-y-3">
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm">
                    <p class="text-sm font-bold text-gray-900">{{ __('borrower.profile.national_id_side_back') }}</p>
                    <x-site.document-source-picker host-id="nida-back-directive" />
                </div>
                <x-site.single-image-document-upload
                    name="national_id_back"
                    input-host-id="nida-back-directive"
                    facing="environment"
                    guide-frame="id-card"
                    :guide="__('borrower.document_upload.nida_back_guide')"
                    :source-driven="true"
                />
            </div>

            <div x-show="notice" x-cloak class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold text-amber-800" x-text="notice"></p>
                <button type="button" @click="notice = null"
                        class="text-sm font-bold text-brand hover:underline">{{ __('borrower.document_upload.retry') }}</button>
            </div>
        </div>

        <template x-if="steps.some(s => s.done && s.previewUrl)">
            <div class="grid sm:grid-cols-2 gap-3">
                <template x-for="step in steps" :key="'thumb-' + step.key">
                    <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-3 py-3" x-show="step.done && step.previewUrl">
                        <p class="text-xs font-semibold text-emerald-800">✓ <span x-text="step.label"></span></p>
                        <img :src="step.previewUrl" alt="" class="mt-2 h-20 w-16 object-cover rounded-lg ring-1 ring-emerald-100">
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
