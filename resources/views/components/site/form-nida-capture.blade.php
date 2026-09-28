{{-- Form-local NIDA front→back journey. Reuses single-image-document-upload / standard camera. --}}
@props([
    'frontName' => 'doc_national_id_front',
    'backName' => 'doc_national_id_back',
    'required' => true,
])

@php
    $frontHost = 'form-doc-'.md5((string) $frontName);
    $backHost = 'form-doc-'.md5((string) $backName);
@endphp

<div class="space-y-3 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4"
     data-kf-form-nida-capture
     x-data="{
        phase: 'start',
        side: 'front',
        frontDone: false,
        backDone: false,
        frontName: '',
        backName: '',
        start() { this.phase = 'journey'; this.side = 'front'; },
        markFront(name) {
            this.frontDone = true;
            this.frontName = name || @js(__('borrower.profile.national_id_side_front'));
            this.side = 'back';
        },
        markBack(name) {
            this.backDone = true;
            this.backName = name || @js(__('borrower.profile.national_id_side_back'));
            this.phase = 'complete';
        },
        retake(which) {
            this.phase = 'journey';
            this.side = which;
            if (which === 'front') { this.frontDone = false; this.frontName = ''; }
            if (which === 'back') { this.backDone = false; this.backName = ''; }
        },
     }"
     @kf-document-file.window="
        if ($event.detail?.hostId === @js($frontHost) && $event.detail?.file) {
            markFront($event.detail.file.name);
        }
        if ($event.detail?.hostId === @js($backHost) && $event.detail?.file) {
            markBack($event.detail.file.name);
        }
     "
     @kf-document-pages-ready.window="
        if ($event.detail?.hostId === @js($frontHost)) markFront();
        if ($event.detail?.hostId === @js($backHost)) markBack();
     ">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.affiliate_apply.nida_title') }}</p>
        <p class="mt-1 text-sm text-gray-600">{{ __('site.affiliate_apply.nida_hint') }}</p>
    </div>

    <div x-show="phase === 'start'" x-cloak>
        <button type="button"
                @click="start()"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3 text-sm shadow-sm">
            <span class="text-lg leading-none" aria-hidden="true">+</span>
            {{ __('site.affiliate_apply.nida_capture_cta') }}
        </button>
    </div>

    <div x-show="phase === 'complete'" x-cloak class="grid sm:grid-cols-2 gap-3">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 px-3 py-3">
            <p class="text-xs text-gray-500">{{ __('borrower.profile.national_id_side_front') }}</p>
            <p class="mt-1 text-sm font-semibold text-emerald-800 truncate" x-text="frontName || '✓'"></p>
            <button type="button" @click="retake('front')" class="mt-2 text-[11px] font-semibold text-brand">{{ __('borrower.profile.replace_document') }}</button>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 px-3 py-3">
            <p class="text-xs text-gray-500">{{ __('borrower.profile.national_id_side_back') }}</p>
            <p class="mt-1 text-sm font-semibold text-emerald-800 truncate" x-text="backName || '✓'"></p>
            <button type="button" @click="retake('back')" class="mt-2 text-[11px] font-semibold text-brand">{{ __('borrower.profile.replace_document') }}</button>
        </div>
    </div>

    <div x-show="phase === 'journey'" x-cloak class="space-y-3">
        <ol class="flex items-center gap-2 text-xs font-semibold">
            <li class="rounded-full px-3 py-1" :class="side === 'front' ? 'bg-brand text-white' : (frontDone ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600')">1. {{ __('borrower.profile.national_id_side_front') }}</li>
            <li class="text-gray-300" aria-hidden="true">→</li>
            <li class="rounded-full px-3 py-1" :class="side === 'back' ? 'bg-brand text-white' : (backDone ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600')">2. {{ __('borrower.profile.national_id_side_back') }}</li>
        </ol>

        <div x-show="side === 'front'" x-cloak>
            <p class="text-sm font-semibold text-gray-900 mb-2">{{ __('borrower.profile.national_id_next_front') }}</p>
            <x-site.form-document-field
                :name="$frontName"
                :label="__('borrower.profile.national_id_side_front')"
                :required="$required"
                capture="nida"
            />
        </div>
        <div x-show="side === 'back'" x-cloak>
            <p class="text-sm font-semibold text-gray-900 mb-2">{{ __('borrower.profile.national_id_next_back') }}</p>
            <x-site.form-document-field
                :name="$backName"
                :label="__('borrower.profile.national_id_side_back')"
                :required="$required"
                capture="nida"
            />
        </div>
    </div>

    <input type="hidden" name="nida_front_captured" :value="frontDone ? '1' : ''" @if ($required) :required="!frontDone" @endif>
    <input type="hidden" name="nida_back_captured" :value="backDone ? '1' : ''" @if ($required) :required="!backDone" @endif>
</div>
