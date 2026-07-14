@props([
    'name' => 'document',
    'inputHostId' => null,
    'labels' => [],
])

@php
    $hostId = $inputHostId ?? ('single-image-'.md5($name));
    $labelDefaults = [
        'uploadImage' => __('borrower.profile.upload_image'),
        'captureImage' => __('borrower.profile.capture_image'),
        'close' => __('borrower.profile.multi_page_close'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'cameraUnsupported' => __('borrower.profile.camera_unsupported'),
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
    ];
    $mergedLabels = array_merge($labelDefaults, $labels);
@endphp

<div x-data="singleImageDocumentUpload(@js($mergedLabels), @js($name), @js($hostId))">
    <div class="flex flex-wrap gap-2">
        <label class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm cursor-pointer">
            <span x-text="labels.uploadImage"></span>
            <input type="file" name="{{ $name }}" accept="image/*,application/pdf" class="sr-only" @change="setFile($event)">
        </label>
        <button type="button" @click="openCamera()" class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm" x-text="labels.captureImage">
        </button>
    </div>

    <div x-show="previewUrl || previewName" x-cloak class="mt-3">
        <template x-if="previewUrl">
            <button type="button" @click="expanded = true" class="relative h-28 w-28 rounded-xl overflow-hidden ring-1 ring-brand/20 bg-white cursor-zoom-in block">
                <img :src="previewUrl" alt="" class="h-full w-full object-cover object-center">
            </button>
        </template>
        <template x-if="!previewUrl && previewName">
            <div class="h-28 w-28 rounded-xl ring-1 ring-brand/20 bg-white grid place-items-center">
                <span class="text-xs font-bold text-brand">PDF</span>
            </div>
        </template>
        <p class="text-xs text-gray-500 mt-1.5" x-text="previewName"></p>
    </div>

    <p x-show="cameraNotice" x-cloak class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2 mt-3" x-text="cameraNotice"></p>

    <div x-show="cameraOpen" x-cloak class="rounded-2xl overflow-hidden ring-1 ring-gray-200 bg-black mt-3">
        <video x-ref="camVideo" autoplay playsinline webkit-playsinline muted class="w-full max-h-72 object-cover mirror"></video>
        <div class="p-3 flex gap-2 bg-white">
            <button type="button" @click="captureImage()" class="flex-1 bg-gray-900 text-white font-semibold px-4 py-2 rounded-xl text-sm" x-text="labels.captureImage"></button>
            <button type="button" @click="closeCamera()" class="px-4 py-2 rounded-xl text-sm ring-1 ring-gray-200" x-text="labels.close"></button>
        </div>
    </div>

    <div x-show="expanded && previewUrl" x-cloak x-transition
         class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
         @keydown.escape.window="expanded = false"
         @click.self="expanded = false">
        <button type="button" class="absolute top-4 right-4 text-white/90 text-sm font-semibold" @click="expanded = false" x-text="labels.close"></button>
        <img :src="previewUrl" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
    </div>

    <div id="{{ $hostId }}"></div>
</div>

@once
    @push('styles')
        <style>.mirror { transform: scaleX(-1); }</style>
    @endpush
    @push('scripts')
    <script>
        function singleImageDocumentUpload(labels, fieldName, hostId) {
            return {
                labels: labels || {},
                fieldName,
                hostId,
                cameraOpen: false,
                cameraNotice: null,
                stream: null,
                previewUrl: null,
                previewName: null,
                expanded: false,
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
                        this.stream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                            audio: false,
                        });
                        const video = this.$refs.camVideo;
                        if (!video) throw new Error(this.labels.cameraUnsupported);
                        video.srcObject = this.stream;
                        video.muted = true;
                        await video.play();
                    } catch (e) {
                        this.cameraOpen = false;
                        this.stopStream();
                        this.cameraNotice = e?.name === 'NotAllowedError'
                            ? this.labels.cameraDenied
                            : (e?.message || this.labels.cameraDenied);
                    }
                },
                closeCamera() {
                    this.stopStream();
                    this.cameraOpen = false;
                },
                stopStream() {
                    if (this.stream) {
                        this.stream.getTracks().forEach(t => t.stop());
                        this.stream = null;
                    }
                },
                captureImage() {
                    const video = this.$refs.camVideo;
                    if (!video?.videoWidth) return;
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.translate(canvas.width, 0);
                    ctx.scale(-1, 1);
                    ctx.drawImage(video, 0, 0);
                    canvas.toBlob(blob => {
                        if (!blob) return;
                        this.syncFile(new File([blob], 'camera-capture.jpg', { type: 'image/jpeg' }));
                        this.closeCamera();
                    }, 'image/jpeg', 0.92);
                },
                setFile(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    this.syncFile(file);
                    event.target.value = '';
                },
                syncFile(file) {
                    const host = document.getElementById(this.hostId);
                    if (!host) return;
                    host.innerHTML = '';
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.name = this.fieldName;
                    input.className = 'sr-only';
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                    host.appendChild(input);

                    if (this.previewUrl && String(this.previewUrl).startsWith('blob:')) {
                        URL.revokeObjectURL(this.previewUrl);
                    }
                    this.previewName = file.name || 'capture.jpg';
                    if (file.type && file.type.startsWith('image/')) {
                        this.previewUrl = URL.createObjectURL(file);
                    } else {
                        this.previewUrl = null;
                    }
                },
            };
        }
    </script>
    @endpush
@endonce
