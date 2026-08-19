@props([
    'customer',
    'angles',
    'wizard',
    'photos',
    'steps',
    'uploadUrls',
    'deleteUrls' => [],
    'submitUrl' => null,
    'returnUrl' => null,
])

<div
    class="w-full"
    x-data="faceVerificationWizard({
        steps: @js($steps),
        uploadUrls: @js($uploadUrls),
        deleteUrls: @js($deleteUrls),
        submitUrl: @js($submitUrl ?? route('site.borrower.face-verification.submit')),
        returnUrl: @js($returnUrl),
        startIndex: @js($wizard['current_index']),
    })"
    x-init="init()"
>
    {{-- Intro --}}
    <div x-show="phase === 'intro'" class="space-y-4">
        <nav aria-label="{{ __('borrower.face_verification_page.steps_nav') }}">
            <ol class="flex items-center gap-0">
                <template x-for="(step, i) in steps" :key="'rail-' + step.key">
                    <li class="flex items-center min-w-0" :class="i < steps.length - 1 ? 'flex-1' : ''">
                        <button type="button"
                                @click="step.done ? retakeStep(i) : null"
                                class="group flex flex-col items-center gap-1.5 shrink-0 focus:outline-none">
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
                        </button>
                        <div x-show="i < steps.length - 1"
                             class="mx-1.5 sm:mx-2 h-px flex-1 min-w-[0.75rem] transition"
                             :class="step.done ? 'bg-emerald-400' : 'bg-gray-200'"
                             aria-hidden="true"></div>
                    </li>
                </template>
            </ol>
        </nav>

        <div class="rounded-2xl ring-1 ring-gray-200 bg-white px-5 py-6 text-center">
            <p class="text-sm text-gray-600">{{ __('borrower.face_verification_page.intro_short') }}</p>
            <p class="mt-3 text-xs text-gray-500">{{ __('borrower.face_verification_page.privacy_note') }}</p>
            <button type="button" @click="startScan()" :disabled="!ready || loading"
                    class="mt-5 w-full bg-brand-gold hover:bg-yellow-400 disabled:opacity-50 text-brand font-bold px-6 py-4 rounded-full text-sm shadow-sm transition">
                <span x-show="!loading">{{ __('borrower.face_verification_page.start_cta') }}</span>
                <span x-show="loading" x-cloak>{{ __('borrower.face_verification_page.opening_camera') }}</span>
            </button>
            <p x-show="notice" x-cloak class="mt-3 text-xs text-amber-700" x-text="notice"></p>
        </div>

        <template x-if="steps.some(s => s.done)">
            <div class="px-1">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">{{ __('borrower.nida.face_captured_photos') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <template x-for="(step, i) in steps" :key="'intro-' + step.key + '-' + (step.previewUrl || '')">
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3" x-show="step.done && step.previewUrl">
                            <p class="text-xs text-gray-500" x-text="step.label"></p>
                            <div class="mt-2 flex flex-col sm:flex-row sm:items-start gap-3">
                                <button type="button"
                                        class="h-28 w-24 shrink-0 rounded-lg ring-1 ring-brand/15 overflow-hidden bg-white cursor-zoom-in block shadow-sm relative"
                                        @click="openPreview(step.previewUrl)">
                                    <img :src="step.previewUrl" :alt="step.label" class="absolute inset-0 w-full h-full object-cover object-top">
                                </button>
                                <div class="min-w-0 flex-1 flex flex-col gap-2 pt-0.5">
                                    <button type="button" @click="retakeStep(i)" :disabled="isRemoving"
                                            class="text-[11px] font-semibold px-2 py-2 rounded-xl bg-brand-muted/60 hover:bg-brand-muted text-brand disabled:opacity-50">
                                        {{ __('borrower.nida.face_retake') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- Scanner — fullscreen proper camera --}}
    <template x-teleport="body">
    <div
        x-show="phase === 'scanning' || phase === 'saving'"
        x-cloak
        class="fixed inset-0 z-[95] bg-brand flex flex-col"
    >
        <video x-ref="video" autoplay playsinline webkit-playsinline muted class="absolute inset-0 z-[1] w-full h-full object-cover mirror bg-gray-900"></video>
        <canvas x-ref="overlay" class="absolute inset-0 z-[2] w-full h-full pointer-events-none"></canvas>

        {{-- Top: brand + step --}}
        <div class="relative z-[4] pt-[max(1rem,env(safe-area-inset-top))] px-4 flex items-start justify-between gap-3 bg-gradient-to-b from-brand to-transparent pb-6">
            <div class="min-w-0 max-w-md">
                <x-site.brand-mark size="sm" variant="light" />
                <div class="mt-3 rounded-2xl bg-black/40 backdrop-blur-sm px-4 py-3 text-white">
                    <p class="text-[11px] uppercase tracking-widest text-brand-gold"
                       x-text="@js(__('borrower.face_verification_page.step_of', ['current' => '__C__', 'total' => '__T__'])).replace('__C__', String(stepIndex + 1)).replace('__T__', String(steps.length))"></p>
                    <p class="text-sm font-semibold mt-1" x-text="guideTitle"></p>
                    <p class="text-xs text-white/80 mt-1">{{ __('borrower.face_verification_page.oval_hint') }}</p>
                    <p class="text-[11px] text-white/70 mt-2">{{ __('borrower.profile.selfie_front_only') }}</p>
                </div>
            </div>
            <button type="button" @click="cancelScan()" class="shrink-0 text-xs font-semibold text-white/90 bg-white/15 ring-1 ring-white/25 px-3 py-2 rounded-full">{{ __('borrower.face_verification_page.cancel') }}</button>
        </div>

        {{-- Oval only --}}
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-[3]">
            <div class="w-[78%] max-w-[340px] aspect-[4/5] rounded-[50%] border-[3px] transition-all duration-300"
                 :class="poseOk
                    ? 'border-emerald-400 shadow-[0_0_32px_rgba(52,211,153,0.45)]'
                    : 'border-amber-300/90 shadow-[0_0_20px_rgba(251,191,36,0.3)]'"></div>
        </div>

        {{-- Bottom: capture CTA + small page dots (not blocking camera) --}}
        <div class="relative z-[4] mt-auto px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-8 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
            <div x-show="steps.some(s => s.done)" class="flex gap-2 justify-center mb-4">
                <template x-for="(step, i) in steps" :key="'scan-dot-' + step.key">
                    <button type="button" x-show="step.done && step.previewUrl" @click="retakeStep(i)"
                            class="size-10 rounded-full overflow-hidden ring-2 ring-white/70 shadow bg-black/40">
                        <img :src="step.previewUrl" alt="" class="w-full h-full object-cover object-top">
                    </button>
                </template>
            </div>
            <button type="button" @click="manualCapture()" :disabled="isUploading || phase === 'saving'"
                    class="w-full max-w-md mx-auto block bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-4 rounded-full text-sm border-0 shadow-lg">
                {{ __('borrower.face_verification_page.capture') }}
            </button>
        </div>

        <div x-show="phase === 'saving'" class="absolute inset-0 z-[6] bg-black/60 flex items-center justify-center">
            <p class="font-semibold text-white">{{ __('borrower.face_verification_page.photo_saved') }}</p>
        </div>
    </div>
    </template>

        {{-- Preview captured photo --}}
        <div x-show="phase === 'preview'" x-cloak class="rounded-3xl overflow-hidden ring-1 ring-brand/15 bg-white">
            <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.face_verification_page.review_photo_title') }}</p>
                <p class="text-xs text-gray-500 mt-1" x-text="currentStep?.instruction"></p>
            </div>
            <img :src="previewUrl" alt="{{ __('borrower.face_verification_page.review_photo_title') }}" class="w-full max-h-[420px] object-cover bg-black">
            <div class="p-4 flex gap-3">
                <button type="button" @click="retakePreview()" class="flex-1 bg-brand-muted/60 hover:bg-brand-muted text-brand font-semibold px-4 py-3 rounded-full text-sm">{{ __('borrower.nida.face_retake') }}</button>
                <button type="button" @click="confirmPreview()" :disabled="isUploading" class="flex-1 bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-4 py-3 rounded-full text-sm">
                    <span x-text="isUploading ? @js(__('borrower.face_verification_page.saving')) : @js(__('borrower.face_verification_page.use_photo'))"></span>
                </button>
            </div>
        </div>

        {{-- Final review --}}
        <div x-show="phase === 'review'" x-cloak class="rounded-3xl overflow-hidden ring-1 ring-brand/15 bg-white">
            <div class="px-5 sm:px-6 py-5 border-b border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.face_verification_page.review_all_title') }}</p>
                <h2 class="mt-1 text-lg font-bold text-gray-900 tracking-tight">{{ __('borrower.face_verification_page.review_all_heading') }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ __('borrower.face_verification_page.review_all_hint') }}</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-3 p-5">
                <template x-for="(step, i) in steps" :key="'review-' + step.key + '-' + (step.previewUrl || '')">
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                        <p class="text-xs text-gray-500" x-text="step.label"></p>
                        <div class="mt-2 flex flex-col sm:flex-row sm:items-start gap-3">
                            <button type="button"
                                    class="h-28 w-24 shrink-0 rounded-lg ring-1 ring-brand/15 overflow-hidden bg-white cursor-zoom-in block shadow-sm hover:ring-brand/40 transition relative"
                                    @click="step.previewUrl ? openPreview(step.previewUrl) : null"
                                    :disabled="!step.previewUrl">
                                <img x-cloak
                                     x-show="!!step.previewUrl"
                                     :src="step.previewUrl"
                                     :alt="step.label"
                                     class="absolute inset-0 w-full h-full object-cover object-top"
                                     loading="eager"
                                     decoding="async">
                            </button>
                            <div class="min-w-0 flex-1 flex flex-col gap-2 pt-0.5">
                                <p class="text-[11px] text-gray-500">{{ __('borrower.profile.tap_to_enlarge') }}</p>
                                <button type="button" @click="openPreview(step.previewUrl)" :disabled="!step.previewUrl"
                                        class="inline-flex items-center justify-center self-start rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-semibold text-brand hover:bg-brand-muted/40 disabled:opacity-50">
                                    {{ __('borrower.nida.face_view_angle') }}
                                </button>
                                <div class="flex gap-2 pt-1">
                                    <button type="button" @click="retakeStep(i)" :disabled="isRemoving"
                                            class="flex-1 text-[11px] font-semibold px-2 py-2 rounded-xl bg-brand-muted/60 hover:bg-brand-muted text-brand disabled:opacity-50">
                                        {{ __('borrower.nida.face_retake') }}
                                    </button>
                                    <button type="button" @click="removePhoto(step.key)" :disabled="isRemoving"
                                            class="flex-1 text-[11px] font-semibold px-2 py-2 rounded-xl bg-red-50 hover:bg-red-100 text-red-700 disabled:opacity-50">
                                        {{ __('borrower.nida.face_remove') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="p-5 border-t border-gray-100 flex flex-wrap gap-3">
                <button type="button" @click="phase = 'intro'" class="flex-1 min-w-[120px] bg-brand-muted/60 hover:bg-brand-muted text-brand font-semibold px-4 py-3 rounded-full text-sm">{{ __('borrower.nida.face_add_more') }}</button>
                <button type="button" @click="submitVerification()" :disabled="isRemoving || isSubmitting" class="flex-1 min-w-[120px] inline-flex items-center justify-center gap-2 bg-brand-gold hover:bg-yellow-400 disabled:opacity-60 text-brand font-bold px-4 py-3 rounded-full text-sm shadow-sm">
                    <svg x-show="isSubmitting" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span x-text="isSubmitting ? @js(__('borrower.nida.face_submitting')) : @js(__('borrower.nida.face_submit_step'))"></span>
                </button>
            </div>
        </div>

        {{-- All done (inline, before reload) --}}
    <div x-show="phase === 'done'" x-cloak class="text-center rounded-3xl bg-gradient-to-b from-emerald-50/80 to-white ring-1 ring-emerald-200/80 px-6 py-10">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('borrower.face_verification_page.done_title') }}</h2>
    </div>

    {{-- Collateral / NIDA enlarge — teleport escapes overflow + backdrop-filter traps --}}
    <template x-teleport="body">
        <div x-show="expandedPreviewUrl" x-cloak x-transition
             class="fixed inset-0 z-[90] bg-black/80 flex items-center justify-center p-4"
             @keydown.escape.window="closePreview()"
             @click.self="closePreview()">
            <button type="button" class="absolute top-4 right-4 text-white/90 text-2xl font-semibold" @click="closePreview()"
                    aria-label="{{ __('borrower.face_verification_page.cancel') }}">×</button>
            <img :src="expandedPreviewUrl" alt="{{ __('borrower.nida.face_preview') }}"
                 class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
        </div>
    </template>
</div>

@once
    @push('styles')
        <style>
            .mirror { transform: scaleX(-1); }
        </style>
    @endpush
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('faceVerificationWizard', (config) => ({
                    phase: 'intro',
                    ready: false,
                    loading: false,
                    notice: null,
                    steps: config.steps,
                    uploadUrls: config.uploadUrls,
                    deleteUrls: config.deleteUrls || {},
                    submitUrl: config.submitUrl || '',
                    returnUrl: config.returnUrl || '',
                    stepIndex: config.startIndex,
                    stream: null,
                    faceDetector: null,
                    detectorActive: false,
                    landmarker: null,
                    landmarkerActive: false,
                    detectLoopId: null,
                    holdProgress: 0,
                    poseOk: false,
                    faceVisible: false,
                    headOffset: 0,
                    lastTick: null,
                    isUploading: false,
                    isRemoving: false,
                    isSubmitting: false,
                    previewUrl: null,
                    previewBlob: null,
                    expandedPreviewUrl: null,
                    scanStartedAt: null,
                    stepStartedAt: null,
                    simpleMode: false,
                    isDesktop: !/Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent),
                    uiTick: 0,
                    uiTimer: null,

                    get detectionLabel() {
                        void this.uiTick;
                        return this.guideTitle;
                    },

                    get guideTitle() {
                        void this.uiTick;
                        if (this.phase === 'saving') return @js(__('borrower.face_verification_page.saving'));
                        const step = this.currentStep;
                        return step?.instruction || @js(__('borrower.face_verification_page.oval_hint'));
                    },

                    get currentStep() {
                        return this.steps[this.stepIndex] || null;
                    },

                    get statusTitle() {
                        return this.guideTitle;
                    },

                    get statusSubtitle() {
                        return @js(__('borrower.face_verification_page.oval_hint'));
                    },

                    startUiTimer() {
                        this.stopUiTimer();
                        this.uiTimer = setInterval(() => { this.uiTick++; }, 250);
                    },

                    stopUiTimer() {
                        if (this.uiTimer) {
                            clearInterval(this.uiTimer);
                            this.uiTimer = null;
                        }
                    },

                    async init() {
                        while (this.stepIndex < this.steps.length && this.steps[this.stepIndex]?.done) {
                            this.stepIndex++;
                        }

                        if (this.stepIndex >= this.steps.length) {
                            this.phase = this.steps.every(s => s.done) ? 'review' : 'intro';
                            if (this.phase === 'intro') {
                                this.stepIndex = this.steps.findIndex(s => !s.done);
                                if (this.stepIndex < 0) this.stepIndex = 0;
                            }
                            this.ready = true;
                            return;
                        }

                        this.simpleMode = this.isDesktop;
                        this.ready = true;
                        this.notice = null;

                        // Never auto-start the camera — Chrome fails when the video is inside a
                        // hidden profile section (videoWidth stays 0). User must click Start.
                        this.observeVisibility();
                        this.bindLeaveGuard();

                        // Alpine cleanup when the component is torn down.
                        return () => this.destroy();
                    },

                    isDirty() {
                        if (this.phase === 'done' || this.isSubmitting) {
                            return false;
                        }
                        if (['scanning', 'saving', 'preview', 'review'].includes(this.phase)) {
                            return true;
                        }
                        return (this.steps || []).some((step) => step.done);
                    },

                    bindLeaveGuard() {
                        this._beforeClose = (event) => {
                            // Event is dispatched on the parent section card; listen in capture on document.
                            if (! event?.target?.contains?.(this.$el)) {
                                return;
                            }
                            if (! this.isDirty()) {
                                return;
                            }
                            event.preventDefault();
                            const detail = event.detail || {};
                            const title = @js(__('borrower.nida.face_leave_title'));
                            const message = @js(__('borrower.nida.face_leave_body'));
                            const confirmLabel = @js(__('borrower.nida.face_leave_confirm'));
                            const runDiscard = async () => {
                                await this.discardIncompletePhotos();
                                detail.proceed?.();
                            };
                            if (typeof window.confirmForm === 'function') {
                                window.confirmForm(null, {
                                    title,
                                    message,
                                    confirmLabel,
                                    confirmClass: 'bg-red-600 hover:bg-red-500 text-white',
                                    onConfirm: () => { runDiscard(); },
                                    onCancel: () => detail.stay?.(),
                                });
                                return;
                            }
                            if (window.confirm(message)) {
                                runDiscard();
                            } else {
                                detail.stay?.();
                            }
                        };
                        document.addEventListener('profile-section-before-close', this._beforeClose, true);
                        this._onBeforeUnload = (e) => {
                            if (! this.isDirty()) return;
                            e.preventDefault();
                            e.returnValue = '';
                        };
                        window.addEventListener('beforeunload', this._onBeforeUnload);
                    },

                    async discardIncompletePhotos() {
                        this.stopCamera();
                        this.closePreview?.();
                        const deletes = (this.steps || [])
                            .filter((step) => step.done && this.deleteUrls?.[step.key])
                            .map(async (step) => {
                                try {
                                    await fetch(this.deleteUrls[step.key], {
                                        method: 'DELETE',
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                        },
                                        credentials: 'same-origin',
                                    });
                                } catch (e) { /* ignore */ }
                                step.done = false;
                                step.previewUrl = null;
                            });
                        await Promise.all(deletes);
                        this.phase = 'intro';
                        this.stepIndex = 0;
                        this.holdProgress = 0;
                        this.notice = null;
                    },

                    observeVisibility() {
                        if (typeof IntersectionObserver === 'undefined') {
                            return;
                        }

                        const root = this.$el;
                        if (! root || this._visibilityObserver) {
                            return;
                        }

                        this._visibilityObserver = new IntersectionObserver((entries) => {
                            const visible = entries.some((entry) => entry.isIntersecting && entry.intersectionRatio > 0.05);
                            if (! visible && this.phase === 'scanning') {
                                this.stopCamera();
                                this.phase = 'intro';
                                this.notice = null;
                            }
                        }, { threshold: [0, 0.05, 0.25] });

                        this._visibilityObserver.observe(root);
                    },

                    destroy() {
                        this.stopCamera();
                        if (this._visibilityObserver) {
                            this._visibilityObserver.disconnect();
                            this._visibilityObserver = null;
                        }
                        if (this._beforeClose) {
                            document.removeEventListener('profile-section-before-close', this._beforeClose, true);
                            this._beforeClose = null;
                        }
                        if (this._onBeforeUnload) {
                            window.removeEventListener('beforeunload', this._onBeforeUnload);
                            this._onBeforeUnload = null;
                        }
                    },

                    async submitVerification() {
                        if (this.isSubmitting || this.isRemoving || this.isUploading) return;
                        if (!this.submitUrl) {
                            window.location.reload();
                            return;
                        }

                        this.isSubmitting = true;
                        this.notice = null;

                        try {
                            const res = await fetch(this.submitUrl, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({}),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok || !data.ok) {
                                throw new Error(data.message || 'Could not submit verification');
                            }
                            const next = data.redirect || this.returnUrl;
                            window.location.href = next || window.location.href;
                        } catch (e) {
                            this.notice = e.message || 'Could not submit verification. Please try again.';
                            this.isSubmitting = false;
                        }
                    },

                    async retakeStep(index) {
                        if (this.isRemoving || this.isUploading || this.isSubmitting) return;
                        this.closePreview();
                        this.stepIndex = index;
                        this.holdProgress = 0;
                        this.notice = null;
                        await this.startScan();
                    },

                    openPreview(url) {
                        if (!url) return;
                        this.expandedPreviewUrl = url;
                    },

                    closePreview() {
                        this.expandedPreviewUrl = null;
                    },

                    waitForImage(url) {
                        return new Promise((resolve) => {
                            if (!url) {
                                resolve(false);
                                return;
                            }
                            const img = new Image();
                            img.onload = () => resolve(true);
                            img.onerror = () => resolve(false);
                            img.src = url;
                        });
                    },

                    async removePhoto(angle) {
                        if (this.isRemoving || this.isUploading || this.isSubmitting) return;
                        const url = this.deleteUrls[angle];
                        if (!url) return;

                        this.isRemoving = true;
                        this.notice = null;

                        try {
                            const res = await fetch(url, {
                                method: 'DELETE',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                },
                                credentials: 'same-origin',
                            });
                            const data = await res.json();
                            if (!res.ok || !data.ok) {
                                throw new Error(data.message || 'Could not remove photo');
                            }

                            const step = this.steps.find(s => s.key === angle);
                            if (step) {
                                const oldPreview = step.previewUrl;
                                step.done = false;
                                step.previewUrl = null;
                                if (oldPreview && String(oldPreview).startsWith('blob:')) {
                                    URL.revokeObjectURL(oldPreview);
                                }
                            }

                            if (!this.steps.every(s => s.done)) {
                                this.phase = 'intro';
                                while (this.stepIndex < this.steps.length && this.steps[this.stepIndex]?.done) {
                                    this.stepIndex++;
                                }
                                if (this.stepIndex >= this.steps.length) {
                                    this.stepIndex = this.steps.findIndex(s => !s.done);
                                    if (this.stepIndex < 0) this.stepIndex = 0;
                                }
                            }
                        } catch (e) {
                            this.notice = e.message || 'Could not remove photo. Please try again.';
                        } finally {
                            this.isRemoving = false;
                        }
                    },

                    async waitForVideoReady(video) {
                        if (!video) {
                            throw new Error('Camera preview unavailable');
                        }
                        if (video.readyState >= 2 && video.videoWidth > 0) {
                            return;
                        }
                        await new Promise((resolve, reject) => {
                            const timeout = setTimeout(() => reject(new Error('Camera preview timed out. Check permissions and try again.')), 15000);
                            const done = () => {
                                clearTimeout(timeout);
                                video.removeEventListener('loadedmetadata', done);
                                resolve();
                            };
                            video.addEventListener('loadedmetadata', done);
                        });
                    },

                    async loadMediaPipeAsync() {
                        const WASM = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm';
                        const BLAZE = 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite';
                        const LANDMARK = 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task';

                        try {
                            const { FaceDetector, FaceLandmarker, FilesetResolver } = await import('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/+esm');
                            const vision = await FilesetResolver.forVisionTasks(WASM);

                            try {
                                this.faceDetector = await FaceDetector.createFromOptions(vision, {
                                    baseOptions: { modelAssetPath: BLAZE, delegate: 'CPU' },
                                    runningMode: 'VIDEO',
                                    minDetectionConfidence: 0.35,
                                });
                                this.detectorActive = true;
                            } catch (e) { /* optional */ }

                            try {
                                this.landmarker = await FaceLandmarker.createFromOptions(vision, {
                                    baseOptions: { modelAssetPath: LANDMARK, delegate: 'CPU' },
                                    runningMode: 'VIDEO',
                                    numFaces: 1,
                                });
                                this.landmarkerActive = true;
                            } catch (e) { /* optional */ }

                            if (this.detectorActive || this.landmarkerActive) {
                                this.simpleMode = false;
                            }
                        } catch (e) {
                            this.simpleMode = true;
                        }
                    },

                    async startScan() {
                        if (!navigator.mediaDevices?.getUserMedia) {
                            this.notice = 'Camera not supported on this device or browser.';
                            this.phase = 'intro';
                            return;
                        }
                        if (this.stepIndex >= this.steps.length) {
                            this.phase = 'done';
                            return;
                        }
                        this.loading = true;
                        this.notice = null;
                        this.phase = 'scanning';
                        try {
                            if (!window.isSecureContext) {
                                throw new Error('Camera requires HTTPS. Open this site over a secure connection and try again.');
                            }
                            await this.$nextTick();
                            await this.$nextTick();
                            this.stream = await this.requestCameraStream();
                            const video = this.$refs.video;
                            if (! video) {
                                throw new Error('Camera preview unavailable');
                            }
                            video.srcObject = this.stream;
                            video.setAttribute('playsinline', 'true');
                            video.setAttribute('webkit-playsinline', 'true');
                            video.muted = true;
                            await this.waitForVideoReady(video);
                            await video.play();
                            this.holdProgress = 0;
                            this.poseOk = false;
                            this.lastTick = performance.now();
                            this.scanStartedAt = performance.now();
                            this.stepStartedAt = performance.now();
                            this.startLoop();
                            this.startUiTimer();
                            if (! this.isDesktop) {
                                this.loadMediaPipeAsync();
                            }
                        } catch (e) {
                            this.stopCamera();
                            this.phase = 'intro';
                            this.notice = e?.name === 'NotAllowedError'
                                ? @js(__('borrower.profile.camera_denied'))
                                : (e?.message || @js(__('borrower.profile.camera_denied')));
                        } finally {
                            this.loading = false;
                        }
                    },

                    async requestCameraStream() {
                        // Facial verification: front camera only (no back-camera fallback).
                        const attempts = [
                            { video: { facingMode: { exact: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                            { video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                            { video: { facingMode: 'user' }, audio: false },
                        ];
                        let lastError;
                        for (const constraints of attempts) {
                            try {
                                return await navigator.mediaDevices.getUserMedia(constraints);
                            } catch (e) {
                                lastError = e;
                            }
                        }
                        throw lastError;
                    },

                    cancelScan() {
                        this.stopLoop();
                        this.stopUiTimer();
                        this.stopCamera();
                        this.phase = 'intro';
                        this.holdProgress = 0;
                        this.simpleMode = this.isDesktop;
                    },

                    startLoop() {
                        this.stopLoop();
                        const video = this.$refs.video;
                        const overlay = this.$refs.overlay;

                        const tick = (now) => {
                            if (this.phase !== 'scanning' || this.isUploading) {
                                this.detectLoopId = requestAnimationFrame(tick);
                                return;
                            }
                            this.detectLoopId = requestAnimationFrame(tick);

                            if (!video.videoWidth) return;

                            const step = this.currentStep;
                            if (!step) return;

                            if (!this.simpleMode && this.stepStartedAt && (now - this.stepStartedAt) > 3500 && !this.faceVisible) {
                                this.simpleMode = true;
                            }

                            if (this.simpleMode) {
                                this.faceVisible = true;
                                this.poseOk = false;
                                this.holdProgress = 0;
                                this.clearOverlay(overlay);
                                return;
                            }

                            this.faceVisible = false;
                            this.poseOk = false;
                            this.clearOverlay(overlay);

                            let bbox = null;

                            if (this.detectorActive && this.faceDetector) {
                                try {
                                    const det = this.faceDetector.detectForVideo(video, now);
                                    if (det.detections?.length) {
                                        this.faceVisible = true;
                                        bbox = det.detections[0].boundingBox;
                                    }
                                } catch (e) { /* continue */ }
                            }

                            if (this.landmarkerActive && this.landmarker) {
                                try {
                                    const result = this.landmarker.detectForVideo(video, now);
                                    if (result.faceLandmarks?.length) {
                                        this.faceVisible = true;
                                        this.headOffset = this.offsetFromLandmarks(result.faceLandmarks[0]);
                                    }
                                } catch (e) { /* continue */ }
                            }

                            if (!this.detectorActive && !this.landmarkerActive) {
                                this.faceVisible = true;
                            }

                            if (this.faceVisible) {
                                this.poseOk = this.matchesPose(step, this.headOffset, bbox, video);
                            }

                            const dt = Math.min(now - (this.lastTick || now), 100);
                            this.lastTick = now;

                            if (this.poseOk) {
                                // Pose feedback only — capture is always manual (user taps Capture).
                                this.holdProgress = Math.min(100, this.holdProgress + (dt / 12));
                            } else if (this.faceVisible) {
                                this.holdProgress = Math.max(0, this.holdProgress - (dt / 8));
                            } else {
                                this.holdProgress = Math.max(0, this.holdProgress - (dt / 4));
                            }

                            // Never auto-capture — borrower taps Capture when ready.
                            if (bbox && overlay) {
                                this.drawBox(overlay, video, bbox, this.poseOk);
                            }
                            this.drawFaceGuide(overlay, video);
                        };

                        this.detectLoopId = requestAnimationFrame(tick);
                    },

                    clearOverlay(overlay) {
                        if (!overlay) return;
                        const ctx = overlay.getContext('2d');
                        ctx.clearRect(0, 0, overlay.width, overlay.height);
                    },

                    drawBox(overlay, video, box, ok) {
                        if (!overlay || !box) return;
                        overlay.width = video.videoWidth;
                        overlay.height = video.videoHeight;
                        const ctx = overlay.getContext('2d');
                        // Video is CSS-mirrored; overlay is not — mirror X so the box tracks the face.
                        const x = video.videoWidth - box.originX - box.width;
                        const y = box.originY;
                        ctx.strokeStyle = ok ? '#34d399' : '#fbbf24';
                        ctx.lineWidth = Math.max(3, video.videoWidth / 180);
                        ctx.strokeRect(x, y, box.width, box.height);
                        ctx.fillStyle = ok ? 'rgba(52,211,153,0.15)' : 'rgba(251,191,36,0.12)';
                        ctx.fillRect(x, y, box.width, box.height);
                    },

                    drawFaceGuide(overlay, video) {
                        if (!overlay || !video?.videoWidth) return;
                        if (overlay.width !== video.videoWidth) {
                            overlay.width = video.videoWidth;
                            overlay.height = video.videoHeight;
                        }
                        const ctx = overlay.getContext('2d');
                        const cx = overlay.width / 2;
                        const cy = overlay.height * 0.42;
                        const rx = overlay.width * 0.28;
                        const ry = overlay.height * 0.34;
                        ctx.save();
                        ctx.strokeStyle = 'rgba(255,255,255,0.85)';
                        ctx.lineWidth = Math.max(3, overlay.width / 200);
                        ctx.setLineDash([10, 8]);
                        ctx.beginPath();
                        ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
                        ctx.stroke();
                        ctx.setLineDash([]);
                        ctx.restore();
                    },

                    poseFromBBox(box, video, step) {
                        if (!box || !video.videoWidth) return step.key === 'holding_nida';
                        const cx = (box.originX + box.width / 2) / video.videoWidth;
                        const size = box.width / video.videoWidth;
                        if (size < 0.06) return false;
                        if (step.key === 'holding_nida') return true;
                        if (step.pose === 'front') return cx > 0.32 && cx < 0.68;
                        if (step.pose === 'left') return cx > 0.52;
                        if (step.pose === 'right') return cx < 0.48;
                        return true;
                    },

                    offsetFromLandmarks(lm) {
                        const nose = lm[1];
                        const left = lm[234];
                        const right = lm[454];
                        if (!nose || !left || !right) return 0;
                        const mid = (left.x + right.x) / 2;
                        const span = Math.abs(right.x - left.x) || 0.001;
                        return (nose.x - mid) / span;
                    },

                    matchesPose(step, offset, bbox, video) {
                        if (!this.faceVisible) return false;
                        if (step.key === 'holding_nida') return true;

                        const landmarkOk = step.pose === 'front' ? Math.abs(offset) < 0.12
                            : step.pose === 'left' ? offset > 0.06
                            : step.pose === 'right' ? offset < -0.06
                            : true;

                        const bboxOk = bbox ? this.poseFromBBox(bbox, video, step) : false;

                        if (this.landmarkerActive && Math.abs(offset) > 0.001) return landmarkOk;
                        if (bbox) return bboxOk;
                        return step.pose === 'front';
                    },

                    stopLoop() {
                        if (this.detectLoopId) {
                            cancelAnimationFrame(this.detectLoopId);
                            this.detectLoopId = null;
                        }
                    },

                    stopCamera() {
                        this.stopLoop();
                        this.stopUiTimer();
                        this.stream?.getTracks().forEach(t => t.stop());
                        this.stream = null;
                    },

                    manualCapture() {
                        if (this.isUploading || this.phase !== 'scanning') return;
                        this.holdProgress = 100;
                        this.captureForPreview();
                    },

                    async captureForPreview() {
                        if (this.isUploading || this.phase !== 'scanning') return;
                        this.phase = 'preview';
                        const blob = await this.captureBlob();
                        if (!blob) {
                            this.phase = 'scanning';
                            this.holdProgress = 0;
                            this.startLoop();
                            return;
                        }
                        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                        this.previewBlob = blob;
                        this.previewUrl = URL.createObjectURL(blob);
                        this.stopLoop();
                    },

                    async retakePreview() {
                        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                        this.previewUrl = null;
                        this.previewBlob = null;
                        this.holdProgress = 0;
                        this.phase = 'scanning';
                        await this.$nextTick();
                        const video = this.$refs.video;
                        if (video && this.stream && video.srcObject !== this.stream) {
                            video.srcObject = this.stream;
                        }
                        if (video && this.stream) {
                            try {
                                await this.waitForVideoReady(video);
                                await video.play();
                            } catch (e) {
                                this.notice = 'Camera preview paused. Tap Capture or Start again.';
                                await this.startScan();
                                return;
                            }
                        } else if (!this.stream) {
                            await this.startScan();
                            return;
                        }
                        this.stepStartedAt = performance.now();
                        this.startLoop();
                    },

                    async confirmPreview() {
                        if (!this.previewBlob || this.isUploading) return;
                        await this.uploadBlob(this.previewBlob);
                    },

                    captureBlob() {
                        return new Promise((resolve) => {
                            const video = this.$refs.video;
                            if (!video?.videoWidth || !video?.videoHeight) {
                                resolve(null);
                                return;
                            }
                            const canvas = document.createElement('canvas');
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            const c = canvas.getContext('2d');
                            c.translate(canvas.width, 0);
                            c.scale(-1, 1);
                            c.drawImage(video, 0, 0);
                            canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.92);
                        });
                    },

                    async autoCapture() {
                        await this.captureForPreview();
                    },

                    async uploadBlob(blob) {
                        if (this.isUploading) return;
                        this.isUploading = true;
                        this.phase = 'saving';

                        const step = this.currentStep;
                        if (!blob || !step) {
                            this.isUploading = false;
                            this.phase = 'scanning';
                            this.holdProgress = 0;
                            return;
                        }

                        const fd = new FormData();
                        fd.append('photo', new File([blob], step.key + '.jpg', { type: 'image/jpeg' }));
                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);

                        try {
                            const res = await fetch(this.uploadUrls[step.key], {
                                method: 'POST',
                                body: fd,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                credentials: 'same-origin',
                            });
                            const data = await res.json();
                            if (!res.ok || !data.ok) {
                                throw new Error(data.message || 'Upload failed');
                            }

                            step.done = true;
                            // Keep the blob preview until the server URL paints, then swap.
                            // Revoking the blob too early blanks images in Chrome.
                            const blobPreview = this.previewUrl;
                            if (blobPreview) {
                                step.previewUrl = blobPreview;
                            }

                            if (data.previewUrl) {
                                const serverReady = await this.waitForImage(data.previewUrl);
                                if (serverReady) {
                                    step.previewUrl = data.previewUrl;
                                } else if (! step.previewUrl) {
                                    step.previewUrl = data.previewUrl;
                                }
                            } else if (this.previewUrl && ! String(this.previewUrl).startsWith('blob:')) {
                                step.previewUrl = this.previewUrl;
                            }

                            // Force Alpine to re-render the step grid with the final URL.
                            this.steps = this.steps.map((s) => ({ ...s }));

                            this.holdProgress = 0;
                            this.poseOk = false;
                            this.stepStartedAt = performance.now();

                            await new Promise(r => setTimeout(r, 700));

                            const finalPreview = this.steps.find(s => s.key === step.key)?.previewUrl;
                            this.previewUrl = null;
                            this.previewBlob = null;
                            if (blobPreview && String(blobPreview).startsWith('blob:') && finalPreview !== blobPreview) {
                                URL.revokeObjectURL(blobPreview);
                            }

                            if (data.complete) {
                                this.stopCamera();
                                this.phase = 'review';
                                return;
                            }

                            this.stepIndex++;
                            while (this.stepIndex < this.steps.length && this.steps[this.stepIndex]?.done) {
                                this.stepIndex++;
                            }

                            if (this.stepIndex >= this.steps.length) {
                                this.stopCamera();
                                this.phase = this.steps.every(s => s.done) ? 'review' : 'intro';
                            } else {
                                this.phase = 'scanning';
                                this.startLoop();
                            }
                        } catch (e) {
                            this.notice = e.message || 'Upload failed. Please try again.';
                            this.phase = 'scanning';
                            this.holdProgress = 0;
                            this.startLoop();
                        } finally {
                            this.isUploading = false;
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
