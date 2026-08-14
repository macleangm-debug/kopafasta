@props([
    'action',
    'multiple' => true,
    'showClarification' => false,
    'disabled' => false,
])

@php
    $cameraLabels = [
        'addPicture' => __('borrower.profile.add_picture'),
        'captureImage' => __('borrower.profile.capture_image'),
        'close' => __('borrower.profile.multi_page_close'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'cameraUnsupported' => __('borrower.profile.camera_unsupported'),
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
        'useFrontCamera' => __('borrower.profile.use_front_camera'),
        'useBackCamera' => __('borrower.profile.use_back_camera'),
        'brand' => brand_name(),
        'gallery' => __('borrower.document_upload.gallery'),
        'pdf' => __('borrower.document_upload.pdf'),
        'camera' => __('borrower.document_upload.camera'),
        'submitConfirmTitle' => __('borrower.document_upload.submit_confirm_title'),
        'submitConfirmBody' => __('borrower.document_upload.submit_confirm_body'),
        'submitConfirmLabel' => __('borrower.document_upload.submit'),
        'savingMessage' => __('borrower.profile.uploading_documents'),
    ];
@endphp

<div class="space-y-3" x-data="documentUpload(@js($disabled), @js($multiple), @js($cameraLabels))">
    @unless($disabled)
        <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-3 rounded-xl text-sm cursor-pointer shadow-sm">
                <span>{{ __('borrower.profile.upload') }}</span>
                <input type="file" accept="image/*,application/pdf" :multiple="allowMultiple" class="sr-only" @change="addFiles($event.target.files); mode='gallery'">
            </label>
            <button type="button" @click="openCamera()"
                    class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-brand shadow-sm ring-1 ring-brand/20 hover:bg-brand-muted/40">
                {{ __('borrower.document_upload.camera') }}
            </button>
        </div>

        <p x-show="cameraNotice" x-cloak class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2" x-text="cameraNotice"></p>

        <template x-teleport="body">
            <div x-show="cameraOpen" x-cloak class="fixed inset-0 z-[95] bg-brand flex flex-col">
                <div class="relative z-[3] flex items-center justify-between gap-3 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-3 bg-gradient-to-b from-brand to-transparent">
                    <div class="min-w-0">
                        <x-site.brand-mark size="sm" variant="light" />
                        <p class="mt-1 text-[10px] uppercase tracking-widest text-brand-gold font-semibold truncate" x-text="labels.brand"></p>
                    </div>
                    <button type="button" @click="closeCamera()"
                            class="shrink-0 rounded-full bg-white/15 text-white text-xs font-semibold px-3 py-2 ring-1 ring-white/25"
                            x-text="labels.close"></button>
                </div>
                <video x-ref="camVideo" autoplay playsinline webkit-playsinline muted
                       class="absolute inset-0 w-full h-full object-cover"
                       :class="facingMode === 'user' ? 'mirror' : ''"></video>
                <canvas x-ref="canvas" class="hidden"></canvas>
                <div class="relative z-[2] mt-auto px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-8 bg-gradient-to-t from-brand via-brand/90 to-transparent">
                    <div class="flex items-center gap-2 max-w-lg mx-auto">
                        <button type="button" @click="toggleFacing()"
                                class="shrink-0 rounded-full bg-white/15 text-white text-xs font-semibold px-3.5 py-3.5 ring-1 ring-white/30 min-w-[7.5rem]"
                                x-text="facingMode === 'user' ? labels.useBackCamera : labels.useFrontCamera"></button>
                        <button type="button" @click="capture()"
                                class="flex-1 bg-brand-gold text-brand font-bold px-4 py-3.5 rounded-full text-sm shadow-sm"
                                x-text="labels.captureImage"></button>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="queued.length" class="space-y-2">
            <p class="text-xs font-semibold text-gray-500">{{ __('borrower.document_upload.ready_to_upload') }} (<span x-text="queued.length"></span>)</p>
            <ul class="flex flex-wrap gap-2">
                <template x-for="(item, index) in queued" :key="index">
                    <li class="relative">
                        <template x-if="item.preview">
                            <button type="button" @click="expandedUrl = item.preview"
                                    class="h-16 w-16 rounded-lg overflow-hidden ring-1 ring-gray-200 bg-white cursor-zoom-in block">
                                <img :src="item.preview" alt="" class="h-full w-full object-cover">
                            </button>
                        </template>
                        <template x-if="!item.preview && item.isPdf">
                            <div class="h-16 w-16 rounded-lg ring-1 ring-gray-200 bg-white grid place-items-center">
                                <span class="text-[10px] font-bold text-brand">PDF</span>
                            </div>
                        </template>
                        <template x-if="!item.preview && !item.isPdf">
                            <div class="h-16 w-16 rounded-lg ring-1 ring-gray-200 bg-white grid place-items-center">
                                <span class="text-[10px] font-semibold text-brand">FILE</span>
                            </div>
                        </template>
                        <button type="button" @click="removeQueued(index)"
                                class="absolute -top-1.5 -right-1.5 size-5 rounded-full bg-white text-red-600 text-xs font-bold ring-1 ring-gray-200 grid place-items-center"
                                aria-label="{{ __('borrower.document_upload.remove') }}">×</button>
                    </li>
                </template>
            </ul>
        </div>

        <form x-ref="form" method="POST" action="{{ $action }}" enctype="multipart/form-data" @submit.prevent="submitForm">
            @csrf
            {{ $slot }}
            @if ($showClarification)
                <div class="mb-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.document_upload.your_response') }}</label>
                    <textarea name="response" rows="3" class="w-full rounded-xl border-gray-200 text-sm" placeholder="{{ __('borrower.document_upload.response_placeholder') }}"></textarea>
                </div>
            @endif
            <button type="submit" :disabled="!canSubmit"
                    class="w-full bg-brand hover:bg-brand-light disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold px-4 py-2.5 rounded-xl text-sm inline-flex items-center justify-center gap-2">
                {{ __('borrower.document_upload.submit') }}
            </button>
        </form>
    @else
        <p class="text-sm text-gray-500">{{ __('borrower.document_upload.no_action') }}</p>
    @endunless

    <div x-show="expandedUrl" x-cloak x-transition
         class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
         @keydown.escape.window="expandedUrl = null"
         @click.self="expandedUrl = null">
        <button type="button" class="absolute top-4 right-4 text-white/90 text-sm font-semibold" @click="expandedUrl = null">{{ __('borrower.profile.cancel') }}</button>
        <img :src="expandedUrl" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
    </div>
</div>

@once
    @push('styles')
        <style>.mirror { transform: scaleX(-1); }</style>
    @endpush
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('documentUpload', (disabled = false, allowMultiple = true, labels = {}) => ({
                    mode: 'gallery',
                    queued: [],
                    stream: null,
                    allowMultiple,
                    expandedUrl: null,
                    cameraOpen: false,
                    cameraNotice: null,
                    facingMode: 'environment',
                    labels: labels || {},

                    get canSubmit() {
                        return this.queued.length > 0 || (this.$refs.form?.querySelector('[name=response]')?.value?.trim()?.length > 0);
                    },

                    addFiles(fileList) {
                        if (!fileList?.length) return;
                        for (const file of fileList) {
                            if (!this.allowMultiple && this.queued.length >= 1) {
                                this.revokeQueued();
                                this.queued = [];
                            }
                            const isImage = (file.type || '').startsWith('image/');
                            const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
                            this.queued.push({
                                file,
                                name: file.name,
                                preview: isImage ? URL.createObjectURL(file) : null,
                                isPdf,
                            });
                        }
                    },

                    revokeQueued() {
                        this.queued.forEach((item) => {
                            if (item.preview) URL.revokeObjectURL(item.preview);
                        });
                    },

                    removeQueued(index) {
                        const [removed] = this.queued.splice(index, 1);
                        if (removed?.preview) URL.revokeObjectURL(removed.preview);
                    },

                    async openCamera() {
                        this.cameraNotice = null;
                        if (!window.isSecureContext) {
                            this.cameraNotice = this.labels.cameraInsecure;
                            return;
                        }
                        if (!navigator.mediaDevices?.getUserMedia) {
                            this.cameraNotice = this.labels.cameraUnsupported;
                            return;
                        }
                        try {
                            this.cameraOpen = true;
                            await this.$nextTick();
                            await this.$nextTick();
                            this.stream = await this.requestCameraStream(this.facingMode);
                            const video = this.$refs.camVideo;
                            if (!video) throw new Error(this.labels.cameraUnsupported);
                            video.srcObject = this.stream;
                            video.setAttribute('playsinline', 'true');
                            video.setAttribute('webkit-playsinline', 'true');
                            video.muted = true;
                            await video.play();
                        } catch (e) {
                            this.cameraOpen = false;
                            this.stopCamera();
                            this.cameraNotice = e?.name === 'NotAllowedError'
                                ? this.labels.cameraDenied
                                : (e?.message || this.labels.cameraDenied);
                        }
                    },

                    async toggleFacing() {
                        this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
                        if (!this.cameraOpen) return;
                        try {
                            this.stopCamera();
                            this.stream = await this.requestCameraStream(this.facingMode);
                            const video = this.$refs.camVideo;
                            if (video) {
                                video.srcObject = this.stream;
                                await video.play();
                            }
                        } catch (e) {
                            this.cameraNotice = this.labels.cameraDenied;
                        }
                    },

                    async requestCameraStream(facing) {
                        const attempts = [
                            { video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                            { video: { facingMode: facing }, audio: false },
                            { video: true, audio: false },
                        ];
                        let lastError = null;
                        for (const constraints of attempts) {
                            try { return await navigator.mediaDevices.getUserMedia(constraints); }
                            catch (e) { lastError = e; }
                        }
                        throw lastError || new Error(this.labels.cameraDenied);
                    },

                    capture() {
                        const video = this.$refs.camVideo;
                        const canvas = this.$refs.canvas;
                        if (!video?.videoWidth) return;
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        const ctx = canvas.getContext('2d');
                        if (this.facingMode === 'user') {
                            ctx.translate(canvas.width, 0);
                            ctx.scale(-1, 1);
                        }
                        ctx.drawImage(video, 0, 0);
                        canvas.toBlob((blob) => {
                            if (!blob) return;
                            const file = new File([blob], `scan-${Date.now()}.jpg`, { type: 'image/jpeg' });
                            this.addFiles([file]);
                            this.closeCamera();
                        }, 'image/jpeg', 0.92);
                    },

                    closeCamera() {
                        this.cameraOpen = false;
                        this.stopCamera();
                    },

                    stopCamera() {
                        this.stream?.getTracks().forEach(t => t.stop());
                        this.stream = null;
                        if (this.$refs.camVideo) this.$refs.camVideo.srcObject = null;
                    },

                    submitForm() {
                        if (typeof window.confirmForm === 'function') {
                            window.confirmForm(null, {
                                title: this.labels.submitConfirmTitle || '',
                                message: this.labels.submitConfirmBody || '',
                                confirmLabel: this.labels.submitConfirmLabel || '',
                                onConfirm: () => this.performSubmit(),
                            });
                            return;
                        }
                        this.performSubmit();
                    },

                    performSubmit() {
                        const form = this.$refs.form;
                        const btn = form?.querySelector('button[type=submit]');
                        if (btn && typeof window.kfMarkBusy === 'function') {
                            window.kfMarkBusy(btn);
                        }
                        const fd = new FormData(form);
                        fd.delete('files[]');
                        fd.delete('file');
                        this.queued.forEach((item) => {
                            fd.append(this.allowMultiple ? 'files[]' : 'file', item.file || item);
                        });
                        this.closeCamera();
                        this.revokeQueued();
                        if (typeof window.kfShowSaving === 'function') {
                            window.kfShowSaving(this.labels.savingMessage || '');
                        }
                        fetch(form.action, {
                            method: 'POST',
                            body: fd,
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                            credentials: 'same-origin',
                        }).then((res) => {
                            if (res.redirected) window.location.href = res.url;
                            else window.location.reload();
                        }).catch(() => {
                            if (btn && typeof window.kfClearBusy === 'function') {
                                window.kfClearBusy(btn);
                            }
                        });
                    },
                }));
            });
        </script>
    @endpush
@endonce
