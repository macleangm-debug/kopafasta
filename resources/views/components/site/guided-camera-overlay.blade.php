<template x-teleport="body">
    <div x-show="open" x-cloak class="fixed inset-0 z-[95] bg-black flex flex-col">
        <div class="kf-cam-stage">
            <video x-ref="camVideo" autoplay playsinline webkit-playsinline muted
                   class="absolute inset-0 z-[1] w-full h-full object-cover bg-gray-900"></video>
        </div>
        <div class="relative z-[4] pointer-events-none pt-[max(0.65rem,env(safe-area-inset-top))]">
            <div class="px-4">
                <div class="pointer-events-auto flex items-center justify-between gap-3 rounded-2xl bg-black/40 ring-1 ring-white/20 backdrop-blur-md px-3 py-2">
                    <x-site.brand-mark size="sm" variant="light" />
                    <button type="button" @click="closeCamera()"
                            class="shrink-0 size-9 rounded-full bg-white/10 text-white grid place-items-center ring-1 ring-white/20"
                            aria-label="{{ __('site.partner_portal.valuation_camera_close') }}">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6 6 18"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="px-4 pt-3 pb-2">
                <div class="kf-cam-guide">
                    <p x-show="subjectLine || subjectName" x-cloak class="text-xs font-semibold tracking-tight text-white/70">
                        <span x-text="subjectLine || subjectName"></span>
                    </p>
                    <p class="text-sm font-extrabold uppercase tracking-[0.18em] text-brand-gold"
                       :class="(subjectLine || subjectName) ? 'mt-2' : ''"
                       x-show="current() && current().required"
                       x-text="captureOrdinal() + ' ' + @js(__('site.partner_portal.valuation_shot_of')) + ' ' + requiredTotal()"></p>
                    <p class="text-2xl sm:text-3xl font-extrabold mt-1.5 leading-tight"
                       x-text="current()?.headline || current()?.label || ''"></p>
                    <p class="text-sm font-semibold mt-2 leading-snug text-white/90" x-text="current()?.guidance || ''"></p>
                </div>
            </div>
        </div>
        <div x-show="guideFrame === 'id-card'" x-cloak
             class="absolute inset-0 z-[3] flex items-center justify-center pointer-events-none px-6">
            <div class="rounded-xl border-[3px] border-brand-gold/90 shadow-[0_0_24px_rgba(251,191,36,0.28)]"
                 :class="orientation === 'landscape' ? 'w-[88%] max-w-lg aspect-[1.586]' : 'h-[58%] max-h-[28rem] aspect-[0.63]'"></div>
        </div>
        <div x-show="guideFrame === 'oval'" x-cloak
             class="absolute inset-0 z-[3] flex items-center justify-center pointer-events-none px-6">
            <div class="w-[62%] max-w-xs aspect-[3/4] rounded-[50%] border-[3px] border-brand-gold/90 shadow-[0_0_24px_rgba(251,191,36,0.28)]"></div>
        </div>
        <div x-show="flash" x-cloak class="relative z-[5] mt-auto mb-auto px-6 text-center text-white">
            <p class="text-2xl sm:text-3xl font-extrabold" x-text="flash ? ('✓ ' + flash.label) : ''"></p>
            <p class="text-base font-semibold mt-2" x-show="flash?.next"
               x-text="flash ? @js(__('site.partner_portal.valuation_next_is', ['label' => '__L__'])).replace('__L__', flash.next) : ''"></p>
        </div>
        <p x-show="cameraNotice" x-cloak class="relative z-[4] mx-4 rounded-xl bg-amber-50 text-amber-950 text-sm font-semibold p-3" x-text="cameraNotice"></p>
        <button type="button" x-show="cameraNotice" x-cloak @click="openCam()"
                class="relative z-[4] mx-4 mt-3 w-[calc(100%-2rem)] rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">
            {{ __('site.partner_portal.valuation_camera_retry') }}
        </button>
        <div class="relative z-[4] mt-auto px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-8 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
            <div class="flex items-center justify-center gap-2 mb-4" x-show="guideFrame !== 'oval'">
                <button type="button" @click="orientation !== 'portrait' && toggleOrientation()"
                        :class="orientation === 'portrait' ? 'bg-brand-gold text-brand' : 'bg-white/15 text-white ring-1 ring-white/30'"
                        class="rounded-full text-xs font-bold px-3.5 py-2">
                    {{ __('borrower.document_upload.orientation_portrait') }}
                </button>
                <button type="button" @click="orientation !== 'landscape' && toggleOrientation()"
                        :class="orientation === 'landscape' ? 'bg-brand-gold text-brand' : 'bg-white/15 text-white ring-1 ring-white/30'"
                        class="rounded-full text-xs font-bold px-3.5 py-2">
                    {{ __('borrower.document_upload.orientation_landscape') }}
                </button>
            </div>
            <button type="button" @click="capture()"
                    class="mx-auto block size-16 rounded-full bg-brand-gold text-brand font-extrabold shadow-lg grid place-items-center">●</button>
            <p class="text-center text-white text-sm font-bold mt-3">{{ __('site.partner_portal.valuation_camera_capture') }}</p>
        </div>
    </div>
</template>
