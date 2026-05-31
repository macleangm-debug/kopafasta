@props([
    'customer',
    'angles',
    'wizard',
    'photos',
])

@php
    $order = $wizard['order'];
    $stepIndex = $wizard['current_index'];
    $stepNumber = $stepIndex + 1;
    $totalSteps = $wizard['total'];
    $angleKey = $wizard['current_angle'];
    $meta = $angles[$angleKey] ?? [];
    $uploadUrl = route('site.borrower.face-verification.store', ['angle' => $angleKey]);
    $requireFace = $meta['require_face'] ?? true;
    $allowGallery = $meta['allow_gallery'] ?? false;
@endphp

<div
    class="max-w-lg mx-auto"
    x-data="faceVerificationWizard({
        uploadUrl: @js($uploadUrl),
        requireFace: @js($requireFace),
        allowGallery: @js($allowGallery),
        stepNumber: @js($stepNumber),
    })"
    x-init="init()"
>
    {{-- Step header --}}
    <div class="text-center mb-6">
        <p class="text-xs uppercase tracking-widest text-amber-600 font-semibold">Face verification</p>
        <p class="text-sm text-gray-500 mt-1">Step {{ $stepNumber }} of {{ $totalSteps }}</p>
        <h2 class="text-xl font-bold text-gray-900 mt-2">{{ $meta['label'] ?? 'Capture photo' }}</h2>
        <p class="text-sm text-gray-600 mt-2">{{ $meta['instruction'] ?? '' }}</p>
        @if (! empty($meta['hint']))
            <p class="text-xs text-gray-400 mt-1">{{ $meta['hint'] }}</p>
        @endif
    </div>

    {{-- Step dots --}}
    <div class="flex justify-center gap-2 mb-6">
        @foreach ($order as $i => $key)
            @php $done = isset($photos[$key]); @endphp
            <div @class([
                'h-2 rounded-full transition-all',
                'w-8 bg-amber-500' => $i === $stepIndex && ! $done,
                'w-2 bg-emerald-500' => $done,
                'w-2 bg-gray-200' => ! $done && $i !== $stepIndex,
            ])></div>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl ring-1 ring-gray-200 overflow-hidden">
        {{-- Camera not started --}}
        <div x-show="phase === 'intro'" class="p-8 text-center">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                <svg class="w-10 h-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                </svg>
            </div>
            <button type="button" @click="openCamera()" :disabled="loading"
                    class="w-full bg-gray-900 hover:bg-gray-800 disabled:opacity-60 text-white font-semibold px-6 py-3.5 rounded-full text-sm">
                <span x-show="!loading">Open camera</span>
                <span x-show="loading" x-cloak>Starting camera…</span>
            </button>
            @if ($allowGallery)
                <button type="button" @click="$refs.galleryInput.click()"
                        class="mt-3 w-full bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-6 py-3 rounded-full text-sm">
                    Upload from gallery
                </button>
                <input type="file" x-ref="galleryInput" accept="image/*" class="hidden" @change="onGallerySelected($event)">
            @endif
            <p x-show="detectorError" x-cloak class="mt-3 text-xs text-amber-700" x-text="detectorError"></p>
        </div>

        {{-- Live camera --}}
        <div x-show="phase === 'camera'" x-cloak class="relative bg-black">
            <video x-ref="video" autoplay playsinline muted class="w-full max-h-80 object-cover mirror"></video>
            <canvas x-ref="overlay" class="absolute inset-0 w-full h-full pointer-events-none mirror"></canvas>
            <div class="absolute top-3 left-3 right-3 flex justify-center">
                <span x-show="faceDetected" x-cloak
                      class="inline-flex items-center gap-1.5 bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                    Face detected
                </span>
                <span x-show="requireFace && !faceDetected && detectorReady" x-cloak
                      class="inline-flex items-center gap-1.5 bg-gray-900/70 text-white text-xs font-semibold px-3 py-1.5 rounded-full">
                    Position your face in the frame
                </span>
            </div>
            <div class="p-4 bg-gray-900 flex flex-wrap gap-2 justify-center">
                <button type="button" @click="capture()"
                        :disabled="requireFace && !faceDetected"
                        class="bg-amber-500 hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                    Capture
                </button>
                <button type="button" @click="stopCamera(); phase='intro'"
                        class="bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                    Cancel
                </button>
            </div>
        </div>

        {{-- Preview --}}
        <div x-show="phase === 'preview'" x-cloak class="p-5 space-y-4">
            <div class="rounded-xl overflow-hidden ring-1 ring-gray-200">
                <img :src="previewUrl" alt="Preview" class="w-full max-h-72 object-cover">
            </div>
            <div class="flex gap-3">
                <button type="button" @click="retake()"
                        class="flex-1 bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-full text-sm">
                    Retake
                </button>
                <button type="button" @click="submitPhoto()" :disabled="submitting"
                        class="flex-1 bg-amber-500 hover:bg-amber-400 disabled:opacity-60 text-gray-900 font-semibold py-2.5 rounded-full text-sm">
                    <span x-show="!submitting">{{ $stepNumber < $totalSteps ? 'Next' : 'Finish' }}</span>
                    <span x-show="submitting" x-cloak>Saving…</span>
                </button>
            </div>
        </div>
    </div>

    <p class="text-xs text-gray-400 text-center mt-4">
        Your photos are stored securely and reviewed by our underwriting team.
    </p>
</div>

@once
    @push('styles')
        <style>
            .mirror { transform: scaleX(-1); }
        </style>
    @endpush
    @push('scripts')
        <script type="module">
            import { FaceDetector, FilesetResolver } from 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/+esm';

            const WASM = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm';
            const MODEL = 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite';

            document.addEventListener('alpine:init', () => {
                Alpine.data('faceVerificationWizard', (config) => ({
                    phase: 'intro',
                    loading: false,
                    submitting: false,
                    stream: null,
                    previewUrl: null,
                    capturedFile: null,
                    faceDetected: false,
                    detectorReady: false,
                    detectorError: null,
                    detector: null,
                    detectLoopId: null,
                    uploadUrl: config.uploadUrl,
                    requireFace: config.requireFace,
                    allowGallery: config.allowGallery,

                    async init() {
                        try {
                            const vision = await FilesetResolver.forVisionTasks(WASM);
                            this.detector = await FaceDetector.createFromOptions(vision, {
                                baseOptions: { modelAssetPath: MODEL, delegate: 'GPU' },
                                runningMode: 'VIDEO',
                                minDetectionConfidence: 0.6,
                            });
                            this.detectorReady = true;
                        } catch (e) {
                            this.detectorError = 'Live face detection unavailable — you can still capture manually.';
                            this.detectorReady = true;
                        }
                    },

                    async openCamera() {
                        if (!navigator.mediaDevices?.getUserMedia) {
                            this.detectorError = 'Camera not supported on this device.';
                            return;
                        }
                        this.loading = true;
                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({
                                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                                audio: false,
                            });
                            this.$refs.video.srcObject = this.stream;
                            await this.$refs.video.play();
                            this.phase = 'camera';
                            this.startDetectionLoop();
                        } catch (e) {
                            this.detectorError = 'Camera permission denied. Please allow camera access and try again.';
                        } finally {
                            this.loading = false;
                        }
                    },

                    startDetectionLoop() {
                        const video = this.$refs.video;
                        const overlay = this.$refs.overlay;
                        const ctx = overlay.getContext('2d');

                        const tick = () => {
                            if (this.phase !== 'camera' || !video.videoWidth) {
                                return;
                            }

                            overlay.width = video.videoWidth;
                            overlay.height = video.videoHeight;
                            ctx.clearRect(0, 0, overlay.width, overlay.height);

                            if (this.detector) {
                                try {
                                    const result = this.detector.detectForVideo(video, performance.now());
                                    this.faceDetected = result.detections.length > 0;

                                    result.detections.forEach((det) => {
                                        const box = det.boundingBox;
                                        if (!box) return;
                                        ctx.strokeStyle = this.faceDetected ? '#10b981' : '#f59e0b';
                                        ctx.lineWidth = 3;
                                        ctx.strokeRect(box.originX, box.originY, box.width, box.height);
                                    });
                                } catch (e) {
                                    // ignore frame errors
                                }
                            } else {
                                this.faceDetected = true;
                            }

                            this.detectLoopId = requestAnimationFrame(tick);
                        };

                        this.detectLoopId = requestAnimationFrame(tick);
                    },

                    stopDetectionLoop() {
                        if (this.detectLoopId) {
                            cancelAnimationFrame(this.detectLoopId);
                            this.detectLoopId = null;
                        }
                    },

                    stopCamera() {
                        this.stopDetectionLoop();
                        this.stream?.getTracks().forEach(t => t.stop());
                        this.stream = null;
                        this.faceDetected = false;
                    },

                    capture() {
                        const video = this.$refs.video;
                        const canvas = document.createElement('canvas');
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        canvas.getContext('2d').drawImage(video, 0, 0);
                        canvas.toBlob((blob) => {
                            if (!blob) return;
                            this.capturedFile = new File([blob], `face-step-${config.stepNumber}.jpg`, { type: 'image/jpeg' });
                            this.previewUrl = URL.createObjectURL(blob);
                            this.stopCamera();
                            this.phase = 'preview';
                        }, 'image/jpeg', 0.92);
                    },

                    onGallerySelected(event) {
                        const file = event.target.files?.[0];
                        if (!file) return;
                        this.capturedFile = file;
                        this.previewUrl = URL.createObjectURL(file);
                        this.phase = 'preview';
                    },

                    retake() {
                        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                        this.previewUrl = null;
                        this.capturedFile = null;
                        this.phase = 'intro';
                    },

                    async submitPhoto() {
                        if (!this.capturedFile || this.submitting) return;
                        this.submitting = true;

                        const fd = new FormData();
                        fd.append('photo', this.capturedFile);
                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);

                        try {
                            const res = await fetch(this.uploadUrl, {
                                method: 'POST',
                                body: fd,
                                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                                credentials: 'same-origin',
                            });
                            window.location.href = res.redirected ? res.url : window.location.href;
                        } catch (e) {
                            this.submitting = false;
                            alert('Upload failed. Please try again.');
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
