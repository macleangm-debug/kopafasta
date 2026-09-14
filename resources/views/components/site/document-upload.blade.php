@props([
    'action',
    'multiple' => true,
    'showClarification' => false,
    'disabled' => false,
    'maxKb' => 5120,
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
        'fileTooLarge' => __('borrower.document_upload.file_too_large'),
        'fileInvalidType' => __('borrower.document_upload.file_invalid_type'),
        'uploadFailed' => __('borrower.document_upload.upload_failed'),
        'successTitle' => __('borrower.feedback.saved_title'),
        'successBody' => __('borrower.document_upload.submitted_body'),
        'continueLabel' => __('borrower.celebration.cta_continue'),
    ];
@endphp

<div class="space-y-3" x-data="documentUpload(@js($disabled), @js($multiple), @js($cameraLabels), @js((int) $maxKb), @js(! (bool) $showClarification && ! (bool) $multiple))">
    @unless($disabled)
        <div class="flex flex-wrap items-center gap-3" x-show="queued.length === 0" x-cloak>
            <label class="inline-flex items-center justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-3 rounded-xl text-sm cursor-pointer shadow-sm">
                <span>{{ __('borrower.profile.upload') }}</span>
                <input type="file" accept="image/*,application/pdf" :multiple="allowMultiple" class="sr-only" @change="addFiles($event.target.files); $event.target.value = ''; mode='gallery'">
            </label>
            <button type="button" @click="openCamera()"
                    class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-brand shadow-sm ring-1 ring-brand/20 hover:bg-brand-muted/40">
                {{ __('borrower.document_upload.camera') }}
            </button>
        </div>

        <p x-show="validationError" x-cloak class="text-sm text-red-800 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2" x-text="validationError" role="alert"></p>
        <p x-show="cameraNotice" x-cloak class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 px-3 py-2 rounded-lg" x-text="cameraNotice"></p>

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

        <div x-show="inlineUploading" x-cloak class="rounded-xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3 space-y-2" data-document-holder>
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-brand"
                   x-text="inlineProgress === null ? @js(__('borrower.document_upload.processing')) : @js(__('borrower.document_upload.uploading'))"></p>
                <p class="text-sm font-bold tabular-nums text-brand"
                   x-show="inlineProgress !== null"
                   x-text="(inlineProgress ?? 0) + '%'"></p>
            </div>
            <div class="h-2 rounded-full bg-white overflow-hidden ring-1 ring-brand/10" x-show="inlineProgress !== null">
                <div class="h-full bg-brand transition-[width] duration-150" :style="'width:' + (inlineProgress ?? 0) + '%'"></div>
            </div>
            <div class="h-2 rounded-full bg-white overflow-hidden ring-1 ring-brand/10" x-show="inlineProgress === null">
                <div class="h-full w-1/3 bg-brand animate-pulse rounded-full"></div>
            </div>
        </div>

        <form x-ref="form" method="POST" action="{{ $action }}" enctype="multipart/form-data" @submit.prevent="submitForm" data-inline-document-progress>
            @csrf
            {{ $slot }}
            @if ($showClarification)
                <div class="mb-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.document_upload.your_response') }}</label>
                    <textarea name="response" rows="3" class="w-full rounded-xl border-gray-200 text-sm" placeholder="{{ __('borrower.document_upload.response_placeholder') }}"></textarea>
                </div>
            @endif
            <button type="submit" x-show="!autoStart || allowMultiple" x-cloak :disabled="!canSubmit || submitting"
                    class="w-full bg-brand hover:bg-brand-light disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold px-4 py-2.5 rounded-xl text-sm inline-flex items-center justify-center gap-2">
                {{ __('borrower.document_upload.submit') }}
            </button>
            <p x-show="autoStart && queued.length && submitting" x-cloak class="text-sm text-gray-600 text-center">{{ __('borrower.profile.uploading_documents') }}</p>
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
                Alpine.data('documentUpload', (disabled = false, allowMultiple = true, labels = {}, maxKb = 5120, autoStart = false) => ({
                    mode: 'gallery',
                    queued: [],
                    stream: null,
                    allowMultiple,
                    autoStart: !!autoStart,
                    maxBytes: Math.max(1, Number(maxKb) || 5120) * 1024,
                    expandedUrl: null,
                    cameraOpen: false,
                    cameraNotice: null,
                    validationError: null,
                    submitting: false,
                    inlineUploading: false,
                    inlineProgress: null,
                    facingMode: 'environment',
                    labels: labels || {},

                    get canSubmit() {
                        return !this.submitting && (this.queued.length > 0 || (this.$refs.form?.querySelector('[name=response]')?.value?.trim()?.length > 0));
                    },

                    isAllowedType(file) {
                        const type = (file.type || '').toLowerCase();
                        const name = (file.name || '').toLowerCase();
                        return type.startsWith('image/') || type === 'application/pdf' || /\.pdf$/i.test(name) || /\.(jpe?g|png|webp|gif)$/i.test(name);
                    },

                    addFiles(fileList) {
                        if (!fileList?.length) return;
                        this.validationError = null;
                        let added = 0;
                        for (const file of fileList) {
                            if (!this.isAllowedType(file)) {
                                this.validationError = this.labels.fileInvalidType || '';
                                continue;
                            }
                            if ((file.size || 0) <= 0) {
                                this.validationError = this.labels.fileInvalidType || '';
                                continue;
                            }
                            if ((file.size || 0) > this.maxBytes) {
                                this.validationError = this.labels.fileTooLarge || '';
                                continue;
                            }
                            if (!this.allowMultiple && this.queued.length >= 1) {
                                this.revokeQueued();
                                this.queued = [];
                            }
                            const isImage = (file.type || '').startsWith('image/') || /\.(jpe?g|png|webp|gif)$/i.test(file.name || '');
                            const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
                            this.queued.push({
                                file,
                                name: file.name,
                                preview: isImage ? URL.createObjectURL(file) : null,
                                isPdf,
                            });
                            added++;
                        }
                        if (added > 0 && this.autoStart) {
                            this.$nextTick(() => this.submitForm());
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
                        this.validationError = null;
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
                        if (this.submitting) return;
                        this.validationError = null;
                        // Document uploads persist inline — no celebratory confirm modal.
                        this.performSubmit();
                    },

                    humanizeError(raw) {
                        const text = String(raw || '').trim();
                        if (!text) return this.labels.uploadFailed || '';
                        if (/files\.|file\.|document_type|max\.|mimes|uploaded/i.test(text) && /[_\[\]]/.test(text)) {
                            return this.labels.uploadFailed || text;
                        }
                        return text;
                    },

                    performSubmit() {
                        if (this.submitting) return;
                        const form = this.$refs.form;
                        const btn = form?.querySelector('button[type=submit]');
                        this.submitting = true;
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
                        this.inlineUploading = true;
                        this.inlineProgress = 0;
                        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', form.action);
                        xhr.withCredentials = true;
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('Accept', 'application/json, text/html;q=0.9');
                        if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                        if (xhr.upload) {
                            xhr.upload.onprogress = (evt) => {
                                if (! evt.lengthComputable) {
                                    this.inlineProgress = null;
                                    return;
                                }
                                this.inlineProgress = Math.max(0, Math.min(99, Math.round((evt.loaded / evt.total) * 100)));
                            };
                        }
                        xhr.onload = async () => {
                            const redirected = !!xhr.getResponseHeader('Location') || (xhr.responseURL && xhr.responseURL !== form.action);
                            const ok = xhr.status >= 200 && xhr.status < 300;
                            if (ok || redirected) {
                                this.inlineProgress = 100;
                                this.revokeQueued();
                                this.queued = [];
                                try { if (typeof open !== 'undefined') open = false; } catch (e) {}
                                this.$dispatch('profile-section-close-edit');
                                // Inline ✓ Saved only — no success modal for ordinary document uploads.
                                window.location.href = xhr.getResponseHeader('Location') || xhr.responseURL || window.location.href;
                                return;
                            }
                            this.inlineUploading = false;
                            this.inlineProgress = null;
                            this.submitting = false;
                            if (btn && typeof window.kfClearBusy === 'function') window.kfClearBusy(btn);
                            let message = this.labels.uploadFailed || '';
                            try {
                                const data = JSON.parse(xhr.responseText || '{}');
                                const first = data?.message || Object.values(data?.errors || {}).flat()?.[0];
                                message = this.humanizeError(first);
                            } catch (e) {
                                message = this.labels.uploadFailed || '';
                            }
                            this.validationError = message;
                            this.revokeQueued();
                            this.queued = [];
                        };
                        xhr.onerror = () => {
                            this.inlineUploading = false;
                            this.inlineProgress = null;
                            this.submitting = false;
                            if (btn && typeof window.kfClearBusy === 'function') window.kfClearBusy(btn);
                            this.validationError = this.labels.uploadFailed || '';
                        };
                        xhr.send(fd);
                    },
                }));
            });
        </script>
    @endpush
@endonce
