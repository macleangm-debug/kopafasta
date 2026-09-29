{{-- Form-local NIDA front→back journey. Matches Borrower Profile holder camera path. --}}
@props([
    'frontName' => 'doc_national_id_front',
    'backName' => 'doc_national_id_back',
    'required' => true,
])

@php
    $frontHost = 'form-nida-front-'.md5((string) $frontName);
    $backHost = 'form-nida-back-'.md5((string) $backName);
@endphp

<div class="space-y-4 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4"
     data-kf-form-nida-capture
     data-document-attach-only
     x-data="{
        phase: 'start',
        side: 'front',
        frontDone: false,
        backDone: false,
        frontName: '',
        backName: '',
        frontPreview: '',
        backPreview: '',
        start() {
            this.phase = 'journey';
            this.side = 'front';
            this.$nextTick(() => {
                window.dispatchEvent(new CustomEvent('document-source', {
                    detail: { source: 'camera', hostId: @js($frontHost) },
                }));
            });
        },
        markFront(detail) {
            this.frontDone = true;
            this.frontName = detail?.file?.name || @js(__('borrower.profile.national_id_side_front'));
            if (detail?.file && String(detail.file.type || '').startsWith('image/')) {
                try { this.frontPreview = URL.createObjectURL(detail.file); } catch (e) { this.frontPreview = ''; }
            }
            this.side = 'back';
            this.$nextTick(() => {
                window.dispatchEvent(new CustomEvent('document-source', {
                    detail: { source: 'camera', hostId: @js($backHost) },
                }));
            });
        },
        markBack(detail) {
            this.backDone = true;
            this.backName = detail?.file?.name || @js(__('borrower.profile.national_id_side_back'));
            if (detail?.file && String(detail.file.type || '').startsWith('image/')) {
                try { this.backPreview = URL.createObjectURL(detail.file); } catch (e) { this.backPreview = ''; }
            }
            this.phase = 'complete';
        },
        retake(which) {
            this.phase = 'journey';
            this.side = which;
            if (which === 'front') {
                this.frontDone = false;
                this.frontName = '';
                if (this.frontPreview) { try { URL.revokeObjectURL(this.frontPreview); } catch (e) {} }
                this.frontPreview = '';
            }
            if (which === 'back') {
                this.backDone = false;
                this.backName = '';
                if (this.backPreview) { try { URL.revokeObjectURL(this.backPreview); } catch (e) {} }
                this.backPreview = '';
            }
            const hostId = which === 'front' ? @js($frontHost) : @js($backHost);
            this.$nextTick(() => {
                window.dispatchEvent(new CustomEvent('document-source', {
                    detail: { source: 'camera', hostId },
                }));
            });
        },
     }"
     @kf-document-file.window="
        if ($event.detail?.hostId === @js($frontHost) && $event.detail?.file) {
            markFront($event.detail);
        }
        if ($event.detail?.hostId === @js($backHost) && $event.detail?.file) {
            markBack($event.detail);
        }
     ">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.affiliate_apply.nida_title') }}</p>
        <p class="mt-1 text-sm text-gray-600">{{ __('site.affiliate_apply.nida_hint') }}</p>
    </div>

    <div x-show="phase === 'start'" x-cloak class="space-y-3">
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
            <div class="mt-2 flex items-start gap-3">
                <template x-if="frontPreview">
                    <img :src="frontPreview" alt="" class="h-16 w-28 object-cover rounded-lg ring-1 ring-gray-100 aspect-[1.586]">
                </template>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-emerald-800 truncate" x-text="frontName || '✓'"></p>
                    <button type="button" @click="retake('front')" class="mt-2 text-[11px] font-semibold text-brand">{{ __('borrower.profile.replace_document') }}</button>
                </div>
            </div>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 px-3 py-3">
            <p class="text-xs text-gray-500">{{ __('borrower.profile.national_id_side_back') }}</p>
            <div class="mt-2 flex items-start gap-3">
                <template x-if="backPreview">
                    <img :src="backPreview" alt="" class="h-16 w-28 object-cover rounded-lg ring-1 ring-gray-100 aspect-[1.586]">
                </template>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-emerald-800 truncate" x-text="backName || '✓'"></p>
                    <button type="button" @click="retake('back')" class="mt-2 text-[11px] font-semibold text-brand">{{ __('borrower.profile.replace_document') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="phase === 'journey'" x-cloak class="space-y-4">
        <ol class="flex items-center gap-2 text-xs font-semibold">
            <li class="rounded-full px-3 py-1" :class="side === 'front' ? 'bg-brand text-white' : (frontDone ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600')">1. {{ __('borrower.profile.national_id_side_front') }}</li>
            <li class="text-gray-300" aria-hidden="true">→</li>
            <li class="rounded-full px-3 py-1" :class="side === 'back' ? 'bg-brand text-white' : (backDone ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600')">2. {{ __('borrower.profile.national_id_side_back') }}</li>
        </ol>

        {{-- Keep both uploaders mounted (Borrower Profile pattern). Hide with x-show only. --}}
        <div x-show="side === 'front'" class="space-y-3">
            <div class="flex items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">{{ __('borrower.profile.national_id_side_front') }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ __('borrower.profile.national_id_next_front') }}</p>
                </div>
                <x-site.document-source-picker
                    :host-id="$frontHost"
                    :camera-only="true"
                    :camera-label="__('borrower.document_upload.take_photo')"
                    :title="__('borrower.document_upload.take_photo')"
                />
            </div>
            <x-site.single-image-document-upload
                :name="$frontName"
                :input-host-id="$frontHost"
                facing="environment"
                guide-frame="id-card"
                :guide="__('borrower.document_upload.nida_front_guide')"
                :source-driven="true"
                :camera-only="true"
                :required="$required"
            />
        </div>

        <div x-show="side === 'back'" x-cloak class="space-y-3">
            <div class="flex items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 shadow-sm">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">{{ __('borrower.profile.national_id_side_back') }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ __('borrower.profile.national_id_next_back') }}</p>
                </div>
                <x-site.document-source-picker
                    :host-id="$backHost"
                    :camera-only="true"
                    :camera-label="__('borrower.document_upload.take_photo')"
                    :title="__('borrower.document_upload.take_photo')"
                />
            </div>
            <x-site.single-image-document-upload
                :name="$backName"
                :input-host-id="$backHost"
                facing="environment"
                guide-frame="id-card"
                :guide="__('borrower.document_upload.nida_back_guide')"
                :source-driven="true"
                :camera-only="true"
                :required="$required"
            />
        </div>
    </div>

    <input type="hidden" name="nida_front_captured" :value="frontDone ? '1' : ''">
    <input type="hidden" name="nida_back_captured" :value="backDone ? '1' : ''">
</div>
