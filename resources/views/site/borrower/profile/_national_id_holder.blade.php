{{-- One member-facing National ID holder (front + back sides). --}}
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
    $nextSide = ! $hasFront ? 'front' : (! $hasBack ? 'back' : null);
    $updateAction = route('site.borrower.profile.update', ['section' => 'personal'])
        .(! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '');
@endphp

<div class="space-y-4" data-kf-national-id-holder>
    <p class="text-xs text-gray-500">{{ __('borrower.profile.national_id_holder_hint') }}</p>

    @if ($uploadsComplete)
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach ([
                'front' => ['doc' => $nidaFront, 'label' => __('borrower.profile.national_id_side_front'), 'field' => 'national_id_front', 'host' => 'nida-front-holder'],
                'back' => ['doc' => $nidaBack, 'label' => __('borrower.profile.national_id_side_back'), 'field' => 'national_id_back', 'host' => 'nida-back-holder'],
            ] as $side => $meta)
                <form method="POST" action="{{ $updateAction }}" enctype="multipart/form-data"
                      data-inline-document-progress data-saving-message="{{ __('borrower.profile.uploading_documents') }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="focus" value="id_images">
                    @if (! empty($returnUrl))
                        <input type="hidden" name="return" value="{{ $returnUrl }}">
                    @endif
                    @if ($nidaSaved)
                        <input type="hidden" name="national_id" value="{{ $nationalId }}">
                    @endif
                    <x-site.profile-document-field
                        :document="$meta['doc']"
                        :field-name="$meta['field']"
                        mode="single"
                        :label="$meta['label']"
                        :input-host-id="$meta['host']"
                        :document-code="$meta['field']"
                        :read-only="false"
                        :allow-remove="false"
                        :allow-replace="true"
                        :show-replace-button="false"
                        :show-view-button="false"
                        :holder-side="$side"
                        :guide="__('borrower.document_upload.nida_'.$side.'_guide')"
                        guide-frame="id-card"
                    />
                </form>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ([
                'front' => $nidaFront,
                'back' => $nidaBack,
            ] as $side => $doc)
                @if ($doc?->file_path)
                    <button type="button"
                            onclick="window.kfSiteOpenDocumentPreview(@js(asset('storage/'.$doc->file_path)), @js($side === 'front' ? __('borrower.profile.nida_front') : __('borrower.profile.nida_back')), 'image')"
                            class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                        {{ __('borrower.profile.view_document') }} · {{ $side === 'front' ? __('borrower.profile.national_id_side_front') : __('borrower.profile.national_id_side_back') }}
                    </button>
                @endif
            @endforeach
            <div class="relative inline-flex"
                 x-data="{
                    open: false,
                    menuStyle: {},
                    placeMenu() {
                        const btn = this.$refs.trigger;
                        if (! btn) return;
                        const r = btn.getBoundingClientRect();
                        const width = 224;
                        const left = Math.min(window.innerWidth - width - 12, Math.max(12, r.right - width));
                        this.menuStyle = { position: 'fixed', top: (r.bottom + 8) + 'px', left: left + 'px', width: width + 'px', zIndex: 10060 };
                    },
                 }">
                <button type="button" x-ref="trigger"
                        @click="if (window.matchMedia('(min-width: 1024px)').matches) { open = !open; if (open) placeMenu(); } else { $dispatch('nida-side-sheet', true) }"
                        class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                    {{ __('borrower.profile.replace_document') }}
                </button>
                <template x-teleport="body">
                    <div x-cloak x-show="open && window.matchMedia('(min-width: 1024px)').matches" x-transition
                         @click.outside="open = false"
                         :style="menuStyle"
                         class="rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 overflow-hidden">
                        <p class="px-4 pt-3 pb-1 text-[10px] uppercase tracking-widest font-bold text-gray-500">{{ __('borrower.profile.national_id_replace_side') }}</p>
                        <button type="button" @click="open = false; window.dispatchEvent(new CustomEvent('nida-holder-replace', { detail: { side: 'front' } }))"
                                class="w-full px-4 py-3.5 text-left text-sm font-semibold text-gray-800 hover:bg-brand/5">{{ __('borrower.profile.national_id_side_front') }}</button>
                        <button type="button" @click="open = false; window.dispatchEvent(new CustomEvent('nida-holder-replace', { detail: { side: 'back' } }))"
                                class="w-full px-4 py-3.5 text-left text-sm font-semibold text-gray-800 hover:bg-brand/5 border-t border-gray-100">{{ __('borrower.profile.national_id_side_back') }}</button>
                    </div>
                </template>
            </div>
        </div>
        <div class="lg:hidden" x-data="{ open: false }" @nida-side-sheet.window="open = !!$event.detail">
            <x-site.bottom-sheet :title="__('borrower.profile.national_id_replace_side')" open="open">
                <div class="space-y-1">
                    <button type="button" @click="open = false; window.dispatchEvent(new CustomEvent('nida-holder-replace', { detail: { side: 'front' } }))"
                            class="w-full rounded-xl px-4 py-3.5 text-left text-sm font-semibold text-gray-900 hover:bg-gray-50">{{ __('borrower.profile.national_id_side_front') }}</button>
                    <button type="button" @click="open = false; window.dispatchEvent(new CustomEvent('nida-holder-replace', { detail: { side: 'back' } }))"
                            class="w-full rounded-xl px-4 py-3.5 text-left text-sm font-semibold text-gray-900 hover:bg-gray-50">{{ __('borrower.profile.national_id_side_back') }}</button>
                </div>
            </x-site.bottom-sheet>
        </div>
    @else
        <p class="text-sm font-semibold text-brand">
            {{ $nextSide === 'front' ? __('borrower.profile.national_id_next_front') : __('borrower.profile.national_id_next_back') }}
        </p>
        @if ($hasFront)
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-800">
                ✓ {{ __('borrower.profile.national_id_side_front') }}
            </div>
        @endif
        @php
            $active = $nextSide === 'front'
                ? ['doc' => $nidaFront, 'field' => 'national_id_front', 'host' => 'nida-front-holder', 'label' => __('borrower.profile.national_id_side_front'), 'guide' => __('borrower.document_upload.nida_front_guide'), 'side' => 'front']
                : ['doc' => $nidaBack, 'field' => 'national_id_back', 'host' => 'nida-back-holder', 'label' => __('borrower.profile.national_id_side_back'), 'guide' => __('borrower.document_upload.nida_back_guide'), 'side' => 'back'];
        @endphp
        <form method="POST" action="{{ $updateAction }}" enctype="multipart/form-data"
              data-inline-document-progress data-saving-message="{{ __('borrower.profile.uploading_documents') }}">
            @csrf @method('PUT')
            <input type="hidden" name="focus" value="id_images">
            @if (! empty($returnUrl))
                <input type="hidden" name="return" value="{{ $returnUrl }}">
            @endif
            @if ($nidaSaved)
                <input type="hidden" name="national_id" value="{{ $nationalId }}">
            @endif
            <x-site.profile-document-field
                :document="$active['doc']"
                :field-name="$active['field']"
                mode="single"
                :label="$active['label']"
                :input-host-id="$active['host']"
                :document-code="$active['field']"
                :allow-remove="false"
                :holder-side="$active['side']"
                :guide="$active['guide']"
                guide-frame="id-card"
                :start-open="true"
            />
        </form>
    @endif
</div>
