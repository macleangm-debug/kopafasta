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
                <div class="flex items-center justify-between gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                    <span class="truncate" x-text="item.name"></span>
                    <button type="button" @click="removeQueued(index)" class="text-red-600 text-xs font-semibold">{{ __('borrower.document_upload.remove') }}</button>
                </div>
            </template>
        </div>

        <form x-ref="form" method="POST" action="{{ $action }}" enctype="multipart/form-data" @submit.prevent="submitForm">
            @csrf
            @if ($showClarification)
                <div class="mb-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.document_upload.your_response') }}</label>
                    <textarea name="response" rows="3" class="w-full rounded-xl border-gray-200 text-sm" placeholder="{{ __('borrower.document_upload.response_placeholder') }}"></textarea>
                </div>
            @endif
            <button type="submit" :disabled="!canSubmit"
                    class="w-full bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-gray-900 font-semibold px-4 py-2.5 rounded-full text-sm">
                {{ __('borrower.document_upload.submit') }}
            </button>
        </form>
    @else
        <p class="text-sm text-gray-500">{{ __('borrower.document_upload.no_action') }}</p>
    @endunless
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
                    activeTab: 'text-sm font-semibold px-4 py-2 rounded-full bg-gray-900 text-white',
                    idleTab: 'text-sm font-semibold px-4 py-2 rounded-full bg-white ring-1 ring-gray-200 text-gray-700',

                    get canSubmit() {
                        return this.queued.length > 0 || (this.$refs.form?.querySelector('[name=response]')?.value?.trim()?.length > 0);
                    },

                    addFiles(fileList) {
                        if (!fileList?.length) return;
                        for (const file of fileList) {
                            if (!this.allowMultiple && this.queued.length >= 1) this.queued = [];
                            this.queued.push(file);
                        }
                    },

                    removeQueued(index) {
                        this.queued.splice(index, 1);
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
                        const fd = new FormData(form);
                        fd.delete('files[]');
                        fd.delete('file');
                        this.queued.forEach((file) => {
                            fd.append(this.allowMultiple ? 'files[]' : 'file', file);
                        });
                        this.stopCamera();
                        fetch(form.action, {
                            method: 'POST',
                            body: fd,
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                            credentials: 'same-origin',
                        }).then((res) => {
                            if (res.redirected) window.location.href = res.url;
                            else window.location.reload();
                        });
                    },
                }));
            });
        </script>
    @endpush
@endonce
