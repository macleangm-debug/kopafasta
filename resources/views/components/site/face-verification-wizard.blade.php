@props([
    'customer',
    'angles',
    'wizard',
    'photos',
    'steps',
    'uploadUrls',
])

<div
    class="w-full"
    x-data="faceVerificationWizard({
        steps: @js($steps),
        uploadUrls: @js($uploadUrls),
        startIndex: @js($wizard['current_index']),
    })"
    x-init="init()"
>
    {{-- Intro --}}
    <div x-show="phase === 'intro'" class="text-center">
        <div class="bg-white rounded-3xl ring-1 ring-gray-200 p-8 mb-4">
            <div class="w-24 h-24 mx-auto mb-5 rounded-full bg-gray-900 flex items-center justify-center">
                <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">Face ID verification</h2>
            <p class="text-sm text-gray-500 mb-4">{{ __('borrower.nida.face_steps_intro') }}</p>
            <ul class="text-left text-sm text-gray-600 space-y-2 mb-4">
                <template x-for="(step, i) in steps" :key="step.key">
                    <li class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
                              :class="step.done ? 'bg-emerald-100 text-emerald-700' : (i === stepIndex ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500')"
                              x-text="i + 1"></span>
                        <span x-text="step.step_title || step.label" :class="step.done ? 'line-through text-gray-400' : ''"></span>
                    </li>
                </template>
                <li class="flex items-center gap-2 text-gray-500">
                    <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold bg-gray-100 text-gray-500" x-text="steps.length + 1"></span>
                    <span>{{ __('borrower.nida.face_submit_step') }}</span>
                </li>
            </ul>
            <div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-left text-xs text-sky-900">
                <p class="font-semibold">{{ __('borrower.nida.face_permission_title') }}</p>
                <p class="mt-1">{{ __('borrower.nida.face_permission_body') }}</p>
            </div>
            <p x-show="!ready && !isDesktop" class="text-sm text-gray-400 mb-4">Loading face scanner…</p>
            <button type="button" @click="startScan()" :disabled="!ready || loading"
                    class="w-full bg-gray-900 hover:bg-gray-800 disabled:opacity-50 text-white font-semibold px-6 py-4 rounded-2xl text-sm">
                <span x-show="!loading">Start verification</span>
                <span x-show="loading" x-cloak>Opening camera…</span>
            </button>
            <p x-show="notice" x-cloak class="mt-3 text-xs text-amber-700" x-text="notice"></p>
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
            <span class="inline-flex items-center gap-2 text-xs font-semibold text-white bg-red-600/90 px-3 py-1.5 rounded-full shadow-lg">
                <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                Live camera preview
            </span>
        </div>

        {{-- Step illustration --}}
        <div class="absolute top-20 left-0 right-0 flex justify-center z-[3] pointer-events-none">
            <div class="rounded-2xl bg-black/45 px-4 py-3 text-white text-center max-w-xs">
                <p class="text-[11px] uppercase tracking-widest text-white/70" x-text="'Step ' + (stepIndex + 1) + ' of ' + steps.length"></p>
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
                  x-text="'Step ' + (stepIndex + 1) + ' of ' + steps.length"></span>
            <button type="button" @click="cancelScan()" class="text-xs font-semibold text-white/80 bg-black/40 px-3 py-1 rounded-full">Cancel</button>
        </div>

        {{-- Status --}}
        <div class="absolute bottom-0 inset-x-0 px-6 pb-24 pt-12 bg-gradient-to-t from-black/90 via-black/50 to-transparent text-center z-[4]">
            <p class="text-lg font-semibold text-white" x-text="statusTitle"></p>
            <p class="text-sm text-white/70 mt-1" x-text="statusSubtitle"></p>
            <p class="text-xs text-white/60 mt-2">Position your face in the oval, then tap Capture to review the photo.</p>
            <button type="button" @click="manualCapture()" :disabled="isUploading || phase === 'saving'"
                    class="mt-4 inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-2xl text-sm border-0">
                Capture
            </button>
        </div>

        {{-- Saving flash --}}
        <div x-show="phase === 'saving'" class="absolute inset-0 bg-black/60 flex items-center justify-center">
            <div class="text-center text-white">
                <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-emerald-500 flex items-center justify-center">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="font-semibold">Photo saved</p>
            </div>
        </div>
    </div>

        {{-- Preview captured photo --}}
        <div x-show="phase === 'preview'" x-cloak class="bg-white rounded-3xl ring-1 ring-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <p class="text-sm font-semibold">Review your photo</p>
                <p class="text-xs text-gray-500 mt-0.5" x-text="currentStep?.instruction"></p>
            </div>
            <img :src="previewUrl" alt="Captured preview" class="w-full max-h-[420px] object-cover bg-black">
            <div class="p-4 flex gap-3">
                <button type="button" @click="retakePreview()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-3 rounded-2xl text-sm">Retake</button>
                <button type="button" @click="confirmPreview()" :disabled="isUploading" class="flex-1 bg-gray-900 hover:bg-gray-800 disabled:opacity-60 text-white font-semibold px-4 py-3 rounded-2xl text-sm">
                    <span x-text="isUploading ? 'Saving…' : 'Use this photo'"></span>
                </button>
            </div>
        </div>

        {{-- Final review --}}
        <div x-show="phase === 'review'" x-cloak class="bg-white rounded-3xl ring-1 ring-gray-200 overflow-hidden">
            <div class="p-5 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-900">Review your photos</h2>
                <p class="text-sm text-gray-500 mt-1">Step {{ count($steps) + 1 }} — confirm all photos, then submit for review.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 p-5">
                <template x-for="(step, i) in steps" :key="step.key">
                    <div class="rounded-xl overflow-hidden ring-1 ring-gray-200">
                        <img :src="step.previewUrl" :alt="step.label" class="w-full aspect-[4/5] object-cover bg-gray-100">
                        <div class="p-2 text-xs font-semibold text-gray-700" x-text="step.label"></div>
                    </div>
                </template>
            </div>
            <div class="p-5 border-t border-gray-100 flex flex-wrap gap-3">
                <button type="button" @click="phase = 'intro'" class="flex-1 min-w-[120px] bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-3 rounded-2xl text-sm">Retake</button>
                <button type="button" @click="submitVerification()" class="flex-1 min-w-[120px] bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-3 rounded-2xl text-sm">{{ __('borrower.nida.face_submit_step') }}</button>
            </div>
        </div>

        {{-- All done (inline, before reload) --}}
    <div x-show="phase === 'done'" x-cloak class="text-center bg-white rounded-3xl ring-1 ring-gray-200 p-10">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900">Verification complete</h2>
        <p class="text-sm text-gray-600 mt-2">All four photos captured and saved. Our team will review them shortly.</p>
        <a href="{{ route('site.borrower.dashboard') }}" class="inline-flex mt-6 bg-gray-900 text-white font-semibold px-6 py-3 rounded-2xl text-sm">
            Back to dashboard
        </a>
    </div>

    <p class="text-xs text-gray-400 text-center mt-4" x-show="phase !== 'done'">
        Photos are encrypted in transit and reviewed by underwriting.
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
                            this.phase = this.steps.every(s => s.done) ? 'review' : 'done';
                            this.ready = true;
                            return;
                        }

                        this.simpleMode = this.isDesktop;
                        this.ready = true;
                        this.notice = null;

                        await this.startScan();
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

                    submitVerification() {
                        window.location.reload();
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
                            if (this.previewUrl) {
                                step.previewUrl = this.previewUrl;
                            }
                            this.holdProgress = 0;
                            this.poseOk = false;
                            this.stepStartedAt = performance.now();

                            await new Promise(r => setTimeout(r, 700));

                            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                            this.previewUrl = null;
                            this.previewBlob = null;

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
                                this.phase = 'done';
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
