@props([
    'name' => 'document',
    'inputHostId' => null,
    'labels' => [],
    'facing' => 'environment', // environment = docs (front+back); user = selfie (front only)
    'required' => false,
])

@php
    $hostId = $inputHostId ?? ('single-image-'.md5($name));
    $facingMode = in_array($facing, ['user', 'environment'], true) ? $facing : 'environment';
    $lockFront = $facingMode === 'user'; // facial/selfie captures stay front-camera only
    $labelDefaults = [
        'uploadImage' => __('borrower.profile.upload_image'),
        'captureImage' => __('borrower.profile.capture_image'),
        'close' => __('borrower.profile.multi_page_close'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'cameraUnsupported' => __('borrower.profile.camera_unsupported'),
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
        'useFrontCamera' => __('borrower.profile.use_front_camera'),
        'useBackCamera' => __('borrower.profile.use_back_camera'),
        'addPicture' => __('borrower.profile.add_picture'),
        'brand' => brand_name(),
    ];
    $mergedLabels = array_merge($labelDefaults, $labels);
@endphp

<div x-data="singleImageDocumentUpload(@js($mergedLabels), @js($name), @js($hostId), @js($facingMode), @js($lockFront))">
    {{-- Gate helper: filled when a preview exists --}}
    <input type="hidden" value="" x-bind:value="previewUrl || previewName ? '1' : ''" @if($required) required @endif aria-hidden="true" tabindex="-1" class="sr-only">

    <p class="mb-2 text-[11px] text-gray-500 leading-relaxed">{{ __('borrower.nida.device_scope_body') }}</p>

    <div class="flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-3 rounded-xl text-sm cursor-pointer shadow-sm">
            <span>{{ __('borrower.profile.upload') }}</span>
            <input type="file" name="{{ $name }}" accept="image/*,application/pdf" class="sr-only" @change="setFile($event)">
        </label>
        <button type="button" @click="openCamera()"
                class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-brand shadow-sm ring-1 ring-brand/20 hover:bg-brand-muted/40">
            {{ __('borrower.document_upload.camera') }}
        </button>
    </div>
    <p class="text-xs text-gray-500 mt-2">{{ __('borrower.profile.upload_unified_hint') }}</p>

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
            <div class="relative z-[2] mt-auto px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-8 bg-gradient-to-t from-brand via-brand/90 to-transparent">
                <div class="flex items-center gap-2 max-w-lg mx-auto">
                    <button type="button" x-show="!lockFront" x-cloak @click="toggleFacing()"
                            class="shrink-0 rounded-full bg-white/15 text-white text-xs font-semibold px-3.5 py-3.5 ring-1 ring-white/30 min-w-[7.5rem]"
                            x-text="facingMode === 'user' ? labels.useBackCamera : labels.useFrontCamera"></button>
                    <button type="button" @click="captureImage()"
                            class="flex-1 bg-brand-gold text-brand font-bold px-4 py-3.5 rounded-full text-sm shadow-sm"
                            x-text="labels.captureImage"></button>
                </div>
            </div>
        </div>
    </template>

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
        function singleImageDocumentUpload(labels, fieldName, hostId, facingMode = 'environment', lockFront = false) {
            return {
                labels: labels || {},
                fieldName,
                hostId,
                facingMode: lockFront ? 'user' : (facingMode || 'environment'),
                lockFront: !!lockFront,
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
                        this.stream = await this.requestCameraStream(this.facingMode);
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
                async toggleFacing() {
                    if (this.lockFront) return;
                    this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
                    this.stopStream();
                    try {
                        this.stream = await this.requestCameraStream(this.facingMode);
                        const video = this.$refs.camVideo;
                        if (!video) throw new Error(this.labels.cameraUnsupported);
                        video.srcObject = this.stream;
                        video.muted = true;
                        await video.play();
                    } catch (e) {
                        this.cameraNotice = e?.name === 'NotAllowedError'
                            ? this.labels.cameraDenied
                            : (e?.message || this.labels.cameraDenied);
                    }
                },
                async requestCameraStream(facing) {
                    const attempts = this.lockFront
                        ? [
                            { video: { facingMode: { exact: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                            { video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                            { video: { facingMode: 'user' }, audio: false },
                        ]
                        : [
                            { video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                            { video: { facingMode: facing }, audio: false },
                            { video: true, audio: false },
                        ];
                    let lastError;
                    for (const constraints of attempts) {
                        try { return await navigator.mediaDevices.getUserMedia(constraints); }
                        catch (e) { lastError = e; }
                    }
                    throw lastError;
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
                    if (this.facingMode === 'user') {
                        ctx.translate(canvas.width, 0);
                        ctx.scale(-1, 1);
                    }
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
                    this.syncFile(this.normalizeFile(file));
                    event.target.value = '';
                },
                normalizeFile(file) {
                    if (!file) return file;
                    if (file.type && (file.type.startsWith('image/') || file.type === 'application/pdf')) {
                        return file;
                    }
                    const match = (file.name || '').match(/\.([a-z0-9]+)$/i);
                    const ext = match ? match[1].toLowerCase() : '';
                    const mimeByExt = {
                        jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png',
                        webp: 'image/webp', gif: 'image/gif', pdf: 'application/pdf',
                    };
                    const mime = mimeByExt[ext];
                    if (!mime) return file;
                    try {
                        return new File([file], file.name || ('upload.' + ext), {
                            type: mime,
                            lastModified: file.lastModified || Date.now(),
                        });
                    } catch (e) {
                        return file;
                    }
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
                    } else if (!file.type && /\.(jpe?g|png|webp|gif)$/i.test(file.name || '')) {
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
