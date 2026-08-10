@props([
    'action',
    'multiple' => true,
    'showClarification' => false,
    'disabled' => false,
])

<div class="space-y-3" x-data="documentUpload(@js($disabled), @js($multiple))">
    @unless($disabled)
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="mode='camera'" :class="mode === 'camera' ? activeTab : idleTab">{{ __('borrower.document_upload.camera') }}</button>
            <button type="button" @click="mode='gallery'" :class="mode === 'gallery' ? activeTab : idleTab">{{ __('borrower.document_upload.gallery') }}</button>
            <button type="button" @click="mode='pdf'" :class="mode === 'pdf' ? activeTab : idleTab">{{ __('borrower.document_upload.pdf') }}</button>
        </div>

        <div x-show="mode === 'camera'" x-cloak class="space-y-3">
            <video x-ref="video" autoplay playsinline muted class="w-full rounded-xl bg-black max-h-48 object-cover"></video>
            <canvas x-ref="canvas" class="hidden"></canvas>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="startCamera()" class="text-sm font-semibold px-4 py-2 rounded-full bg-gray-900 text-white">{{ __('borrower.document_upload.start_camera') }}</button>
                <button type="button" @click="capture()" class="text-sm font-semibold px-4 py-2 rounded-full bg-amber-500 text-gray-900">{{ __('borrower.document_upload.capture_page') }}</button>
            </div>
        </div>

        <div x-show="mode === 'gallery'" x-cloak>
            <input type="file" x-ref="galleryInput" accept="image/*" :multiple="allowMultiple" class="w-full text-sm"
                   @change="addFiles($event.target.files)">
        </div>

        <div x-show="mode === 'pdf'" x-cloak>
            <input type="file" x-ref="pdfInput" accept="application/pdf" :multiple="allowMultiple" class="w-full text-sm"
                   @change="addFiles($event.target.files)">
        </div>

        <div x-show="queued.length" class="space-y-2">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">{{ __('borrower.document_upload.ready_to_upload') }} (<span x-text="queued.length"></span>)</p>
            <template x-for="(item, index) in queued" :key="index">
                <div class="flex items-center justify-between gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                    <div class="flex items-center gap-3 min-w-0">
                        <template x-if="item.preview">
                            <button type="button" @click="expandedUrl = item.preview"
                                    class="h-12 w-12 rounded-lg overflow-hidden ring-1 ring-gray-200 bg-white shrink-0 cursor-zoom-in">
                                <img :src="item.preview" alt="" class="h-full w-full object-cover">
                            </button>
                        </template>
                        <template x-if="!item.preview && item.isPdf">
                            <div class="h-12 w-12 rounded-lg ring-1 ring-gray-200 bg-white flex flex-col items-center justify-center text-gray-600 shrink-0">
                                <span class="text-[10px] font-bold">PDF</span>
                            </div>
                        </template>
                        <template x-if="!item.preview && !item.isPdf">
                            <div class="h-12 w-12 rounded-lg ring-1 ring-gray-200 bg-white flex items-center justify-center text-gray-500 text-[10px] font-semibold shrink-0">FILE</div>
                        </template>
                        <span class="truncate" x-text="item.name"></span>
                    </div>
                    <button type="button" @click="removeQueued(index)" class="text-red-600 text-xs font-semibold shrink-0">{{ __('borrower.document_upload.remove') }}</button>
                </div>
            </template>
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
                    class="w-full bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-gray-900 font-semibold px-4 py-2.5 rounded-full text-sm inline-flex items-center justify-center gap-2">
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
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('documentUpload', (disabled = false, allowMultiple = true) => ({
                    mode: 'gallery',
                    queued: [],
                    stream: null,
                    allowMultiple,
                    expandedUrl: null,
                    activeTab: 'text-sm font-semibold px-4 py-2 rounded-full bg-gray-900 text-white',
                    idleTab: 'text-sm font-semibold px-4 py-2 rounded-full bg-white ring-1 ring-gray-200 text-gray-700',

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

                    async startCamera() {
                        if (!navigator.mediaDevices?.getUserMedia) {
                            this.mode = 'gallery';
                            return;
                        }
                        try {
                            if (this.stream) this.stopCamera();
                            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                            this.$refs.video.srcObject = this.stream;
                        } catch (e) {
                            this.mode = 'gallery';
                        }
                    },

                    capture() {
                        const video = this.$refs.video;
                        const canvas = this.$refs.canvas;
                        if (!video?.videoWidth) return;
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        canvas.getContext('2d').drawImage(video, 0, 0);
                        canvas.toBlob((blob) => {
                            if (!blob) return;
                            const file = new File([blob], `scan-${Date.now()}.jpg`, { type: 'image/jpeg' });
                            this.addFiles([file]);
                        }, 'image/jpeg', 0.92);
                    },

                    stopCamera() {
                        this.stream?.getTracks().forEach(t => t.stop());
                        this.stream = null;
                    },

                    submitForm() {
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
                        this.stopCamera();
                        this.revokeQueued();
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
