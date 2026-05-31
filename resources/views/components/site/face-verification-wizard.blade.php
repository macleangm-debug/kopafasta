@props([
    'customer',
    'angles',
    'wizard',
    'photos',
    'steps',
    'uploadUrls',
])

<div
    class="max-w-md mx-auto"
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
            <p class="text-sm text-gray-500 mb-6">We will guide you through four poses — front, left, right, and NIDA — and save each photo automatically.</p>
            <ul class="text-left text-sm text-gray-600 space-y-2 mb-6">
                <template x-for="(step, i) in steps" :key="step.key">
                    <li class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
                              :class="step.done ? 'bg-emerald-100 text-emerald-700' : (i === stepIndex ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500')"
                              x-text="i + 1"></span>
                        <span x-text="step.instruction" :class="step.done ? 'line-through text-gray-400' : ''"></span>
                    </li>
                </template>
            </ul>
            <p x-show="!ready" class="text-sm text-gray-400 mb-4">Loading face scanner…</p>
            <button type="button" @click="startScan()" :disabled="!ready || loading"
                    class="w-full bg-gray-900 hover:bg-gray-800 disabled:opacity-50 text-white font-semibold px-6 py-4 rounded-2xl text-sm">
                <span x-show="!loading">Start verification</span>
                <span x-show="loading" x-cloak>Opening camera…</span>
            </button>
            <p x-show="notice" x-cloak class="mt-3 text-xs text-amber-700" x-text="notice"></p>
        </div>
    </div>

    {{-- iOS-style scanner --}}
    <div x-show="phase === 'scanning' || phase === 'saving'" x-cloak class="relative rounded-3xl overflow-hidden bg-black aspect-[3/4] max-h-[72vh] shadow-2xl ring-1 ring-gray-800">
        <video x-ref="video" autoplay playsinline muted class="absolute inset-0 w-full h-full object-cover mirror"></video>

        {{-- Progress ring (iOS Face ID style) --}}
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none" style="margin-top: -8%;">
            <svg class="w-56 h-56 sm:w-64 sm:h-64" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="3"/>
                <circle cx="60" cy="60" r="52" fill="none" stroke="#34d399" stroke-width="3" stroke-linecap="round"
                        transform="rotate(-90 60 60)"
                        :stroke-dasharray="326.7"
                        :stroke-dashoffset="326.7 - (326.7 * holdProgress / 100)"/>
            </svg>
            <div class="absolute w-44 h-56 sm:w-48 sm:h-60 rounded-[50%] border-2 transition-colors duration-300"
                 :class="poseOk ? 'border-emerald-400 shadow-[0_0_24px_rgba(52,211,153,0.45)]' : 'border-white/40'"></div>
        </div>

        {{-- Step badge --}}
        <div class="absolute top-4 left-4 right-4 flex justify-between items-center">
            <span class="text-xs font-semibold text-white/90 bg-black/40 px-3 py-1 rounded-full"
                  x-text="'Step ' + (stepIndex + 1) + ' of ' + steps.length"></span>
            <button type="button" @click="cancelScan()" class="text-xs font-semibold text-white/80 bg-black/40 px-3 py-1 rounded-full">Cancel</button>
        </div>

        {{-- Status --}}
        <div class="absolute bottom-0 inset-x-0 px-6 pb-8 pt-16 bg-gradient-to-t from-black via-black/80 to-transparent text-center">
            <p class="text-lg font-semibold text-white" x-text="statusTitle"></p>
            <p class="text-sm text-white/70 mt-1" x-text="statusSubtitle"></p>
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
                    landmarker: null,
                    landmarkerActive: false,
                    detectLoopId: null,
                    holdProgress: 0,
                    poseOk: false,
                    faceVisible: false,
                    headOffset: 0,
                    lastTick: null,
                    isUploading: false,

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
                        if (!this.faceVisible) return 'Move your face into the oval';
                        if (step.key === 'holding_nida') {
                            return this.poseOk ? 'Keep NIDA and face visible' : 'Hold your NIDA card beside your face';
                        }
                        if (this.poseOk) return 'Perfect — keep this pose';
                        if (step.pose === 'left') return 'Slowly turn your head to the left';
                        if (step.pose === 'right') return 'Slowly turn your head to the right';
                        return 'Look straight at the camera';
                    },

                    async init() {
                        try {
                            const { FaceLandmarker, FilesetResolver } = await import('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/+esm');
                            const WASM = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm';
                            const MODEL = 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task';
                            const vision = await FilesetResolver.forVisionTasks(WASM);
                            try {
                                this.landmarker = await FaceLandmarker.createFromOptions(vision, {
                                    baseOptions: { modelAssetPath: MODEL, delegate: 'GPU' },
                                    runningMode: 'VIDEO',
                                    numFaces: 1,
                                });
                            } catch (e) {
                                this.landmarker = await FaceLandmarker.createFromOptions(vision, {
                                    baseOptions: { modelAssetPath: MODEL, delegate: 'CPU' },
                                    runningMode: 'VIDEO',
                                    numFaces: 1,
                                });
                            }
                            this.landmarkerActive = true;
                        } catch (e) {
                            this.landmarkerActive = false;
                            this.notice = 'Advanced pose detection unavailable — photos will save automatically when your face is visible.';
                        } finally {
                            this.ready = true;
                            while (this.stepIndex < this.steps.length && this.steps[this.stepIndex]?.done) {
                                this.stepIndex++;
                            }
                        }
                    },

                    async startScan() {
                        if (!navigator.mediaDevices?.getUserMedia) {
                            this.notice = 'Camera not supported on this device.';
                            return;
                        }
                        if (this.stepIndex >= this.steps.length) {
                            this.phase = 'done';
                            return;
                        }
                        this.loading = true;
                        this.notice = null;
                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({
                                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                                audio: false,
                            });
                            const video = this.$refs.video;
                            video.srcObject = this.stream;
                            await video.play();
                            this.holdProgress = 0;
                            this.poseOk = false;
                            this.phase = 'scanning';
                            this.lastTick = performance.now();
                            this.startLoop();
                        } catch (e) {
                            this.notice = 'Allow camera access in your browser settings, then try again.';
                        } finally {
                            this.loading = false;
                        }
                    },

                    cancelScan() {
                        this.stopLoop();
                        this.stopCamera();
                        this.phase = 'intro';
                        this.holdProgress = 0;
                    },

                    startLoop() {
                        this.stopLoop();
                        const video = this.$refs.video;

                        const tick = (now) => {
                            if (this.phase !== 'scanning' || this.isUploading) {
                                this.detectLoopId = requestAnimationFrame(tick);
                                return;
                            }
                            this.detectLoopId = requestAnimationFrame(tick);

                            if (!video.videoWidth) return;

                            const step = this.currentStep;
                            if (!step) return;

                            this.faceVisible = false;
                            this.poseOk = false;

                            if (this.landmarkerActive && this.landmarker) {
                                try {
                                    const result = this.landmarker.detectForVideo(video, now);
                                    if (result.faceLandmarks?.length) {
                                        this.faceVisible = true;
                                        this.headOffset = this.offsetFromLandmarks(result.faceLandmarks[0]);
                                        this.poseOk = this.matchesPose(step, this.headOffset);
                                    }
                                } catch (e) { /* continue */ }
                            } else {
                                this.faceVisible = true;
                                this.poseOk = true;
                            }

                            const dt = Math.min(now - (this.lastTick || now), 100);
                            this.lastTick = now;

                            if (this.poseOk) {
                                this.holdProgress = Math.min(100, this.holdProgress + (dt / 14));
                            } else {
                                this.holdProgress = Math.max(0, this.holdProgress - (dt / 8));
                            }

                            if (this.holdProgress >= 100 && !this.isUploading) {
                                this.autoCapture();
                            }
                        };

                        this.detectLoopId = requestAnimationFrame(tick);
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

                    matchesPose(step, offset) {
                        if (!this.faceVisible) return false;
                        if (step.key === 'holding_nida') return true;
                        if (step.pose === 'front') return Math.abs(offset) < 0.07;
                        if (step.pose === 'left') return offset > 0.11;
                        if (step.pose === 'right') return offset < -0.11;
                        return true;
                    },

                    stopLoop() {
                        if (this.detectLoopId) {
                            cancelAnimationFrame(this.detectLoopId);
                            this.detectLoopId = null;
                        }
                    },

                    stopCamera() {
                        this.stopLoop();
                        this.stream?.getTracks().forEach(t => t.stop());
                        this.stream = null;
                    },

                    captureBlob() {
                        return new Promise((resolve) => {
                            const video = this.$refs.video;
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
                        if (this.isUploading) return;
                        this.isUploading = true;
                        this.phase = 'saving';

                        const step = this.currentStep;
                        const blob = await this.captureBlob();
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
                            this.holdProgress = 0;
                            this.poseOk = false;

                            await new Promise(r => setTimeout(r, 700));

                            if (data.complete) {
                                this.stopCamera();
                                this.phase = 'done';
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
