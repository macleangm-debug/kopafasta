@props([
    'customer',
    'angles',
    'wizard',
    'photos',
    'steps',
    'uploadUrls',
    'deleteUrls' => [],
    'submitUrl' => null,
])

<div
    class="w-full"
    x-data="faceVerificationWizard({
        steps: @js($steps),
        uploadUrls: @js($uploadUrls),
        deleteUrls: @js($deleteUrls),
        submitUrl: @js($submitUrl ?? route('site.borrower.face-verification.submit')),
        startIndex: @js($wizard['current_index']),
    })"
    x-init="init()"
>
    {{-- Intro --}}
    <div x-show="phase === 'intro'" class="space-y-5">
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

        <div class="rounded-3xl overflow-hidden ring-1 ring-brand/15 bg-gradient-to-b from-brand-muted/40 via-white to-white">
            <div class="px-5 sm:px-7 py-6 sm:py-7 text-center">
                <div class="mx-auto mb-5 size-20 rounded-full bg-gradient-to-br from-brand to-brand-light text-white grid place-items-center shadow-lg shadow-brand/20">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">{{ __('borrower.face_verification_page.intro_title') }}</h2>
                <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">{{ __('borrower.nida.face_steps_intro') }}</p>

                <div class="mt-5 rounded-2xl bg-sky-50/80 ring-1 ring-sky-200/80 px-4 py-3 text-left text-xs text-sky-950 max-w-md mx-auto">
                    <p class="font-semibold">{{ __('borrower.nida.face_permission_title') }}</p>
                    <p class="mt-1 text-sky-800">{{ __('borrower.nida.face_permission_body') }}</p>
                </div>
            </div>

            <template x-if="steps.some(s => s.done)">
                <div class="px-5 sm:px-7 pb-2">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">{{ __('borrower.nida.face_captured_photos') }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="(step, i) in steps" :key="'intro-' + step.key">
                            <div class="rounded-2xl overflow-hidden ring-1 ring-brand/10 bg-white">
                                <div class="relative aspect-[3/4] bg-gradient-to-b from-brand-muted/40 to-gray-100">
                                    <template x-if="step.done && step.previewUrl">
                                        <img :src="step.previewUrl" :alt="step.label"
                                             class="absolute inset-0 w-full h-full object-cover object-center">
                                    </template>
                                    <div x-show="!step.done" class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-xs text-gray-400">
                                        <span class="text-xl opacity-40" aria-hidden="true">◎</span>
                                        <span>{{ __('borrower.nida.face_not_captured') }}</span>
                                    </div>
                                    <div x-show="step.done" class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-black/55 to-transparent pointer-events-none"></div>
                                    <p x-show="step.done" class="absolute bottom-2 left-2 right-2 text-[11px] font-semibold text-white drop-shadow-sm truncate" x-text="step.label"></p>
                                </div>
                                <div x-show="step.done" class="p-2 flex gap-2 border-t border-gray-100">
                                    <button type="button" @click="retakeStep(i)" :disabled="isRemoving"
                                            class="flex-1 text-[11px] font-semibold px-2 py-2 rounded-xl bg-brand-muted/60 hover:bg-brand-muted text-brand disabled:opacity-50">
                                        {{ __('borrower.nida.face_retake') }}
                                    </button>
                                    <button type="button" @click="removePhoto(step.key)" :disabled="isRemoving"
                                            class="flex-1 text-[11px] font-semibold px-2 py-2 rounded-xl bg-red-50 hover:bg-red-100 text-red-700 disabled:opacity-50">
                                        <span x-show="!isRemoving">{{ __('borrower.nida.face_remove') }}</span>
                                        <span x-show="isRemoving" x-cloak>…</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div class="px-5 sm:px-7 py-6">
                <p x-show="!ready && !isDesktop" class="text-sm text-gray-400 mb-3 text-center">{{ __('borrower.face_verification_page.loading_scanner') }}</p>
                <button type="button" @click="startScan()" :disabled="!ready || loading"
                        class="w-full bg-brand-gold hover:bg-yellow-400 disabled:opacity-50 text-brand font-bold px-6 py-4 rounded-full text-sm shadow-sm transition">
                    <span x-show="!loading">{{ __('borrower.face_verification_page.start_cta') }}</span>
                    <span x-show="loading" x-cloak>{{ __('borrower.face_verification_page.opening_camera') }}</span>
                </button>
                <p x-show="notice" x-cloak class="mt-3 text-xs text-amber-700 text-center" x-text="notice"></p>
            </div>
        </div>
    </div>

    {{-- Scanner — live camera preview while capturing --}}
    <div
        x-show="phase === 'scanning' || phase === 'saving'"
        x-cloak
        class="relative rounded-3xl overflow-hidden bg-black w-full min-h-[70vh] max-h-[80vh] shadow-2xl ring-1 ring-gray-800"
    >
        <video x-ref="video" autoplay playsinline webkit-playsinline muted class="absolute inset-0 z-[1] w-full h-full object-cover mirror bg-gray-900"></video>
        <canvas x-ref="overlay" class="absolute inset-0 z-[2] w-full h-full pointer-events-none mirror"></canvas>

        {{-- Live preview label --}}
        <div class="absolute top-12 left-1/2 -translate-x-1/2 z-[3] pointer-events-none">
            <span class="inline-flex items-center gap-2 text-xs font-semibold text-brand bg-brand-gold px-3 py-1.5 rounded-full shadow-lg">
                <span class="w-2 h-2 rounded-full bg-brand animate-pulse"></span>
                {{ __('borrower.face_verification_page.live_preview') }}
            </span>
        </div>

        {{-- Step illustration --}}
        <div class="absolute top-20 left-0 right-0 flex justify-center z-[3] pointer-events-none">
            <div class="rounded-2xl bg-black/45 px-4 py-3 text-white text-center max-w-xs">
                <p class="text-[11px] uppercase tracking-widest text-white/70"
                   x-text="@js(__('borrower.face_verification_page.step_of', ['current' => '__C__', 'total' => '__T__'])).replace('__C__', String(stepIndex + 1)).replace('__T__', String(steps.length))"></p>
                <div class="mt-2 text-3xl" x-show="currentStep?.pose === 'left'">← 👤</div>
                <div class="mt-2 text-3xl" x-show="currentStep?.pose === 'right'">👤 →</div>
                <div class="mt-2 text-3xl" x-show="currentStep?.key === 'holding_nida'">🪪 👤</div>
                <div class="mt-2 text-3xl" x-show="!currentStep?.pose && currentStep?.key !== 'holding_nida'">👤</div>
                <p class="text-xs mt-2 text-white/80" x-text="currentStep?.instruction"></p>
            </div>
        </div>

        {{-- Progress ring + large oval — semi-transparent so you can see yourself --}}
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-[3]">
            <svg class="w-[92%] max-w-[380px] aspect-square" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="54" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="2.5"/>
                <circle cx="60" cy="60" r="54" fill="none" stroke="#34d399" stroke-width="3.5" stroke-linecap="round"
                        transform="rotate(-90 60 60)"
                        :stroke-dasharray="339.3"
                        :stroke-dashoffset="339.3 - (339.3 * holdProgress / 100)"/>
            </svg>
            <div class="absolute w-[78%] max-w-[300px] aspect-[4/5] rounded-[50%] border-[3px] transition-all duration-300"
                 :class="poseOk
                    ? 'border-emerald-400 shadow-[0_0_32px_rgba(52,211,153,0.55)] bg-emerald-400/10'
                    : (faceVisible ? 'border-amber-300/90 shadow-[0_0_20px_rgba(251,191,36,0.35)] bg-transparent' : 'border-white/60 bg-transparent')"></div>
        </div>

        {{-- Detection status --}}
        <div class="absolute top-16 left-0 right-0 flex flex-col items-center gap-2 px-4 z-[4]">
            <span class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-full backdrop-blur-sm"
                  :class="faceVisible
                    ? (poseOk ? 'bg-emerald-500/90 text-white' : 'bg-amber-500/90 text-gray-900')
                    : 'bg-black/50 text-white/90'">
                <span class="w-2.5 h-2.5 rounded-full"
                      :class="faceVisible ? (poseOk ? 'bg-white animate-pulse' : 'bg-gray-900 animate-pulse') : 'bg-red-400 animate-pulse'"></span>
                <span x-text="detectionLabel"></span>
            </span>
            <span x-show="phase === 'scanning' && holdProgress > 0" x-cloak
                  class="text-xs font-mono text-white/80 bg-black/40 px-3 py-1 rounded-full"
                  x-text="Math.round(holdProgress) + '%'"></span>
        </div>

        {{-- Step badge --}}
        <div class="absolute top-4 left-4 right-4 flex justify-between items-center z-[4]">
            <span class="text-xs font-semibold text-white/90 bg-black/40 px-3 py-1 rounded-full"
                  x-text="@js(__('borrower.face_verification_page.step_of', ['current' => '__C__', 'total' => '__T__'])).replace('__C__', String(stepIndex + 1)).replace('__T__', String(steps.length))"></span>
            <button type="button" @click="cancelScan()" class="text-xs font-semibold text-white/80 bg-black/40 px-3 py-1 rounded-full">{{ __('borrower.face_verification_page.cancel') }}</button>
        </div>

        {{-- Status --}}
        <div class="absolute bottom-0 inset-x-0 px-6 pb-24 pt-12 bg-gradient-to-t from-black/90 via-black/50 to-transparent text-center z-[4]">
            <p class="text-lg font-semibold text-white" x-text="statusTitle"></p>
            <p class="text-sm text-white/70 mt-1" x-text="statusSubtitle"></p>
            <p class="text-xs text-white/60 mt-2">{{ __('borrower.face_verification_page.capture_hint') }}</p>
            <button type="button" @click="manualCapture()" :disabled="isUploading || phase === 'saving'"
                    class="mt-4 inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-full text-sm border-0 shadow-sm">
                {{ __('borrower.face_verification_page.capture') }}
            </button>
        </div>

        {{-- Saving flash --}}
        <div x-show="phase === 'saving'" class="absolute inset-0 bg-black/60 flex items-center justify-center">
            <div class="text-center text-white">
                <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-emerald-500 flex items-center justify-center">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="font-semibold">{{ __('borrower.face_verification_page.photo_saved') }}</p>
            </div>
        </div>
    </div>

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
            <div class="grid grid-cols-2 gap-3 p-5">
                <template x-for="(step, i) in steps" :key="step.key">
                    <div class="rounded-2xl overflow-hidden ring-1 ring-brand/10 bg-white">
                        <div class="relative aspect-[3/4] bg-gradient-to-b from-brand-muted/40 to-gray-100">
                            <img x-show="step.previewUrl" :src="step.previewUrl" :alt="step.label"
                                 class="absolute inset-0 w-full h-full object-cover object-center">
                            <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-black/55 to-transparent pointer-events-none"></div>
                            <p class="absolute bottom-2 left-2 right-2 text-[11px] font-semibold text-white drop-shadow-sm truncate" x-text="step.label"></p>
                        </div>
                        <div class="p-2 flex gap-2 border-t border-gray-100">
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
        <p class="text-sm text-gray-600 mt-2 max-w-sm mx-auto">{{ __('borrower.face_verification_page.done_body') }}</p>
        <a href="{{ route('site.borrower.dashboard') }}" class="inline-flex mt-6 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-full text-sm shadow-sm">
            {{ __('borrower.face_verification_page.back_dashboard') }}
        </a>
    </div>

    <p class="text-xs text-gray-400 text-center mt-4" x-show="phase !== 'done'">
        {{ __('borrower.face_verification_page.privacy_note') }}
    </p>
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
                    scanStartedAt: null,
                    stepStartedAt: null,
                    simpleMode: false,
                    isDesktop: !/Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent),
                    uiTick: 0,
                    uiTimer: null,

                    get detectionLabel() {
                        void this.uiTick;
                        if (this.phase === 'saving') return 'Saving photo…';
                        if (this.isDesktop || this.simpleMode) return 'Tap Capture when you are ready';
                        if (!this.detectorActive && !this.landmarkerActive) return 'Browser mode — hold your face in the oval';
                        if (!this.faceVisible) return 'No face detected — sit closer to the camera';
                        if (this.poseOk) return '✓ Perfect — hold still';
                        if (this.holdProgress > 0) return 'Almost there — keep holding';
                        return 'Face detected — adjust your head';
                    },

                    get currentStep() {
                        return this.steps[this.stepIndex] || null;
                    },

                    get statusTitle() {
                        if (this.phase === 'saving') return 'Saving…';
                        const step = this.currentStep;
                        if (!step) return '';
                        if (this.holdProgress > 0 && this.poseOk) return 'Hold still…';
                        return step.instruction;
                    },

                    get statusSubtitle() {
                        if (this.phase === 'saving') return 'Uploading photo securely';
                        const step = this.currentStep;
                        if (!step) return '';
                        if (this.isDesktop || this.simpleMode) return 'Follow the step instruction, then tap Capture when ready.';
                        if (!this.faceVisible) return 'Fill the oval with your face — sit closer to the webcam';
                        if (step.key === 'holding_nida') {
                            return this.poseOk ? 'Keep NIDA and face visible' : 'Hold your NIDA card beside your face';
                        }
                        if (this.poseOk) return 'Perfect — keep this pose';
                        if (step.pose === 'left') return 'Slowly turn your head to the left';
                        if (step.pose === 'right') return 'Slowly turn your head to the right';
                        return 'Look straight at the camera';
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
                    },

                    async cameraPermissionGranted() {
                        if (! navigator.permissions?.query) {
                            return false;
                        }

                        try {
                            const status = await navigator.permissions.query({ name: 'camera' });

                            return status.state === 'granted';
                        } catch {
                            return false;
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
                            window.location.reload();
                        } catch (e) {
                            this.notice = e.message || 'Could not submit verification. Please try again.';
                            this.isSubmitting = false;
                        }
                    },

                    async retakeStep(index) {
                        if (this.isRemoving || this.isUploading || this.isSubmitting) return;
                        this.stepIndex = index;
                        this.holdProgress = 0;
                        this.notice = null;
                        await this.startScan();
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
                        const attempts = [
                            { video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                            { video: { facingMode: 'user' }, audio: false },
                            { video: true, audio: false },
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
                                this.holdProgress = Math.min(100, this.holdProgress + (dt / 12));
                            } else if (this.faceVisible && step.key === 'holding_nida') {
                                this.holdProgress = Math.min(100, this.holdProgress + (dt / 16));
                            } else if (this.faceVisible && !this.detectorActive && !this.landmarkerActive) {
                                this.holdProgress = Math.min(100, this.holdProgress + (dt / 18));
                            } else if (this.faceVisible && this.scanStartedAt && (now - this.scanStartedAt) > 5000) {
                                this.holdProgress = Math.min(100, this.holdProgress + (dt / 20));
                            } else {
                                this.holdProgress = Math.max(0, this.holdProgress - (dt / 6));
                            }

                            if (bbox && overlay) {
                                this.drawBox(overlay, video, bbox, this.poseOk);
                            }
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
                        ctx.strokeStyle = ok ? '#34d399' : '#fbbf24';
                        ctx.lineWidth = Math.max(3, video.videoWidth / 180);
                        ctx.strokeRect(box.originX, box.originY, box.width, box.height);
                        ctx.fillStyle = ok ? 'rgba(52,211,153,0.15)' : 'rgba(251,191,36,0.12)';
                        ctx.fillRect(box.originX, box.originY, box.width, box.height);
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
                        if (this.isUploading) return;
                        const blob = await this.captureBlob();
                        if (!blob) return;
                        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                        this.previewBlob = blob;
                        this.previewUrl = URL.createObjectURL(blob);
                        this.stopLoop();
                        this.phase = 'preview';
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
                            } catch (e) { /* user can tap Start again */ }
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
                            // Prefer the persisted server URL so the grid stays visible after we
                            // release the temporary blob preview (revoking the blob blanked images).
                            if (data.previewUrl) {
                                step.previewUrl = data.previewUrl;
                            } else if (this.previewUrl && !String(this.previewUrl).startsWith('blob:')) {
                                step.previewUrl = this.previewUrl;
                            } else if (this.previewUrl) {
                                // Keep blob only until navigation; do not revoke while step still uses it.
                                step.previewUrl = this.previewUrl;
                            }
                            this.holdProgress = 0;
                            this.poseOk = false;
                            this.stepStartedAt = performance.now();

                            await new Promise(r => setTimeout(r, 700));

                            const blobToRelease = this.previewUrl;
                            this.previewUrl = null;
                            this.previewBlob = null;
                            if (blobToRelease && String(blobToRelease).startsWith('blob:') && step.previewUrl !== blobToRelease) {
                                URL.revokeObjectURL(blobToRelease);
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
                            this.phase = 'intro';
                            this.stopCamera();
                        } finally {
                            this.isUploading = false;
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
