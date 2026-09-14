@props([
    'name' => 'document_pages',
    'inputHostId' => null,
    'labels' => [],
    'maxPages' => 12,
    'required' => false,
    'cameraFirst' => false,
    'autoFinishUpload' => false,
    'sourceDriven' => false,
])

@php
    $hostId = $inputHostId ?? ('doc-pages-'.md5($name));
    $autoFinishUpload = (bool) $autoFinishUpload;
    $sourceDriven = (bool) $sourceDriven || $autoFinishUpload;
    $labelDefaults = [
        'hint' => '',
        'uploadFile' => __('borrower.profile.multi_page_upload'),
        'capturePage' => __('borrower.profile.multi_page_capture'),
        'close' => __('borrower.profile.multi_page_close'),
        'pageLabel' => __('borrower.profile.multi_page_page'),
        'remove' => __('borrower.profile.multi_page_remove'),
        'addAnother' => __('borrower.profile.multi_page_add_another'),
        'pagesReady' => __('borrower.profile.multi_page_pages_ready'),
        'finish' => __('borrower.profile.multi_page_finish'),
        'captureMore' => __('borrower.profile.multi_page_capture_more'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'cameraUnsupported' => __('borrower.profile.camera_unsupported'),
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
        'maxPages' => __('borrower.profile.multi_page_max', ['max' => $maxPages]),
        'useFrontCamera' => __('borrower.profile.use_front_camera'),
        'useBackCamera' => __('borrower.profile.use_back_camera'),
        'addPicture' => __('borrower.profile.add_picture'),
        'brand' => brand_name(),
        'rotate' => __('borrower.document_upload.rotate'),
        'fitFrame' => __('borrower.document_upload.fit_document_frame'),
        'orientationPortrait' => __('borrower.document_upload.orientation_portrait'),
        'orientationLandscape' => __('borrower.document_upload.orientation_landscape'),
    ];
    $mergedLabels = array_merge($labelDefaults, $labels);
@endphp

<div class="space-y-4" x-data="multiPageDocumentUpload(@js($mergedLabels), @js($name), @js($hostId), {{ (int) $maxPages }}, @js($autoFinishUpload))"
     @document-open-camera.window="if ($event.detail?.hostId === hostId) { fromCamera = true; if ($event.detail?.fresh) resetCapture(); openCamera(); }"
     @document-open-upload.window="if ($event.detail?.hostId === hostId) { if ($event.detail?.fresh) resetCapture(); $refs.fileInput?.click(); }"
     @clear-capture.window="if ($event.detail?.hostId === hostId) resetCapture()">
    <input type="hidden" value="" x-bind:value="pages.length ? String(pages.length) : ''" @if($required) required @endif aria-hidden="true" tabindex="-1" class="sr-only">
    <div class="flex flex-wrap items-center gap-3" x-show="pages.length === 0 && !@js($sourceDriven)" x-cloak>
        @if ($cameraFirst)
            <button type="button" @click="fromCamera = true; openCamera()"
                    class="inline-flex items-center justify-center rounded-xl bg-brand-gold px-5 py-3 text-sm font-bold text-brand shadow-sm hover:bg-yellow-400">
                {{ __('borrower.document_upload.open_camera') }}
            </button>
            <label class="inline-flex items-center justify-center bg-white hover:bg-gray-50 text-brand font-bold px-5 py-3 rounded-xl text-sm cursor-pointer shadow-sm ring-1 ring-brand/20">
                <span>{{ __('borrower.profile.upload') }}</span>
                <input type="file" accept="image/*,application/pdf" multiple class="sr-only" x-ref="fileInput" @change="fromCamera = false; addFiles($event)">
            </label>
        @else
            <label class="inline-flex items-center justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-3 rounded-xl text-sm cursor-pointer shadow-sm">
                <span>{{ __('borrower.profile.upload') }}</span>
                <input type="file" accept="image/*,application/pdf" multiple class="sr-only" x-ref="fileInput" @change="fromCamera = false; addFiles($event)">
            </label>
            <button type="button" @click="fromCamera = true; openCamera()"
                    class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-brand shadow-sm ring-1 ring-brand/20 hover:bg-brand-muted/40">
                {{ __('borrower.document_upload.camera') }}
            </button>
        @endif
    </div>
    @if ($sourceDriven)
        <input type="file" accept="image/*,application/pdf" multiple class="sr-only" x-ref="fileInput" @change="fromCamera = false; addFiles($event)">
    @endif

    <p x-show="cameraNotice" x-cloak class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2" x-text="cameraNotice"></p>

    {{-- Fullscreen branded camera --}}
    <template x-teleport="body">
        <div x-show="cameraOpen" x-cloak class="fixed inset-0 z-[95] bg-brand flex flex-col">
            <div class="relative z-[3] flex items-center justify-between gap-3 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-3 bg-gradient-to-b from-brand to-transparent">
                <div class="min-w-0">
                    <x-site.brand-mark size="sm" variant="light" />
                    <p class="mt-1 text-[10px] uppercase tracking-widest text-brand-gold font-semibold truncate" x-text="labels.brand"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="frameOrientation = frameOrientation === 'portrait' ? 'landscape' : 'portrait'"
                            class="rounded-full bg-white/15 text-white text-xs font-semibold px-3 py-2 ring-1 ring-white/25 inline-flex items-center gap-1.5"
                            :title="frameOrientation === 'portrait' ? labels.orientationLandscape : labels.orientationPortrait"
                            :aria-label="frameOrientation === 'portrait' ? labels.orientationLandscape : labels.orientationPortrait">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
                             :class="frameOrientation === 'landscape' ? 'rotate-90' : ''">
                            <rect x="7" y="3" width="10" height="18" rx="1.5"/>
                        </svg>
                    </button>
                    <button type="button" @click="dismissCamera()"
                            class="rounded-full bg-white/15 text-white text-xs font-semibold px-3 py-2 ring-1 ring-white/25"
                            x-text="labels.close"></button>
                </div>
            </div>
            <video x-ref="camVideo" autoplay playsinline webkit-playsinline muted
                   class="absolute inset-0 w-full h-full object-cover"
                   :class="facingMode === 'user' ? 'mirror' : ''"></video>
            {{-- Framing guide only — does not crop the capture. --}}
            <div class="pointer-events-none absolute inset-0 z-[1] flex items-center justify-center px-6"
                 aria-hidden="true">
                <div class="relative transition-all duration-200 ease-out border-2 border-dashed border-white/70 rounded-xl shadow-[0_0_0_9999px_rgba(0,0,0,0.28)]"
                     :class="frameOrientation === 'portrait'
                        ? 'w-[min(78vw,22rem)] aspect-[3/4] max-h-[62vh]'
                        : 'w-[min(92vw,34rem)] aspect-[4/3] max-h-[48vh]'">
                    <p class="absolute -bottom-8 left-0 right-0 text-center text-[11px] font-semibold tracking-wide text-white/90"
                       x-text="labels.fitFrame"></p>
                </div>
            </div>
            <div class="relative z-[2] mt-auto px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-8 bg-gradient-to-t from-brand via-brand/90 to-transparent">
                <div x-show="pages.length" class="flex gap-2 overflow-x-auto justify-center mb-4 pb-1">
                    <template x-for="(page, index) in pages" :key="'live-'+page.id">
                        <div class="relative shrink-0">
                            <template x-if="page.previewUrl">
                                <img :src="page.previewUrl" alt="" class="size-12 rounded-lg object-cover ring-2 ring-brand-gold/80">
                            </template>
                            <template x-if="!page.previewUrl">
                                <div class="size-12 rounded-lg bg-white/20 ring-2 ring-brand-gold/80 grid place-items-center text-[10px] font-bold text-white">PDF</div>
                            </template>
                            <span class="absolute -top-1.5 -left-1.5 size-5 rounded-full bg-brand-gold text-brand text-[10px] font-bold grid place-items-center ring-2 ring-brand"
                                  x-text="index + 1"></span>
                            <button type="button" @click="removePage(index)"
                                    class="pointer-events-auto absolute -top-1.5 -right-1.5 size-5 rounded-full bg-black/70 text-white text-xs font-bold grid place-items-center ring-1 ring-white/40"
                                    :title="labels.remove" :aria-label="labels.remove">×</button>
                        </div>
                    </template>
                </div>
                <div class="flex items-center justify-center gap-5 max-w-lg mx-auto">
                    <button type="button" @click="toggleFacing()"
                            class="shrink-0 size-12 rounded-full bg-white/15 text-white ring-1 ring-white/30 grid place-items-center"
                            :title="facingMode === 'user' ? labels.useBackCamera : labels.useFrontCamera"
                            :aria-label="facingMode === 'user' ? labels.useBackCamera : labels.useFrontCamera">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 4h2a2 2 0 0 1 2 2v2M8 4H6a2 2 0 0 0-2 2v2m0 8v2a2 2 0 0 0 2 2h2m8 0h2a2 2 0 0 0 2-2v-2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 12a4.5 4.5 0 0 0 7.2 3.6L16 17m.5-5a4.5 4.5 0 0 0-7.2-3.6L8 7"/>
                        </svg>
                    </button>
                    <button type="button" @click="capturePage()"
                            class="shrink-0 size-[4.25rem] rounded-full bg-brand-gold text-brand shadow-lg ring-4 ring-white/25 grid place-items-center"
                            :title="pages.length ? labels.captureMore : labels.capturePage"
                            :aria-label="pages.length ? labels.captureMore : labels.capturePage">
                        <span class="size-14 rounded-full bg-brand-gold ring-2 ring-brand/20 grid place-items-center">
                            <svg class="size-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M9 3.75A1.5 1.5 0 0 1 10.35 3h3.3A1.5 1.5 0 0 1 15 3.75V5.25h2.25A2.25 2.25 0 0 1 19.5 7.5v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 18V7.5A2.25 2.25 0 0 1 6.75 5.25H9V3.75zm3 14a4.125 4.125 0 1 0 0-8.25 4.125 4.125 0 0 0 0 8.25z"/>
                            </svg>
                        </span>
                    </button>
                    <button type="button" x-show="pages.length" x-cloak @click="finishFromCamera()"
                            class="shrink-0 min-w-[5.5rem] rounded-full bg-brand-gold text-brand font-bold px-4 py-3 text-sm shadow-sm inline-flex items-center justify-center gap-1.5"
                            :title="labels.finish" :aria-label="labels.finish">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="labels.finish"></span>
                    </button>
                    <div x-show="!pages.length" class="shrink-0 min-w-[5.5rem]" aria-hidden="true"></div>
                </div>
                <div x-show="pages.length && pages.length < maxPages" class="mt-3 flex justify-center">
                    <button type="button" @click="capturePage()"
                            class="inline-flex items-center gap-2 rounded-full bg-white/15 text-white text-xs font-semibold px-3.5 py-2 ring-1 ring-white/30"
                            :title="labels.addAnother" :aria-label="labels.addAnother">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                            <rect x="3" y="6" width="18" height="14" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span x-text="labels.addAnother"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Page gallery — same holder as submitted document thumbs --}}
    <div x-show="pages.length > 0" x-cloak>
                <div class="flex items-center justify-between gap-3 mb-2">
            <p class="text-xs font-semibold text-gray-500">
                <span x-text="labels.pagesReady.replace(':count', String(pages.length))"></span>
            </p>
            <div class="flex items-center gap-2">
                <button type="button" x-show="fromCamera" x-cloak @click="openCamera()" :disabled="pages.length >= maxPages"
                        class="inline-flex items-center justify-center size-9 rounded-full bg-white ring-1 ring-brand/20 text-brand hover:bg-brand/5 disabled:opacity-40"
                        :title="labels.addAnother" :aria-label="labels.addAnother">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                    </svg>
                </button>
                <button type="button" x-show="autoFinishUpload && !cameraOpen" x-cloak @click="finishUpload()"
                        class="inline-flex items-center justify-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm"
                        :title="labels.finish" :aria-label="labels.finish" x-text="labels.finish"></button>
            </div>
        </div>
        <ul class="flex flex-wrap gap-2">
            <template x-for="(page, index) in pages" :key="page.id">
                <li class="relative" x-data="{ expanded: false }">
                    <template x-if="page.previewUrl">
                        <button type="button" @click="expanded = true"
                                class="h-16 w-16 rounded-lg overflow-hidden ring-1 ring-gray-200 bg-white cursor-zoom-in block">
                            <img :src="page.previewUrl" alt="" class="h-full w-full object-cover"
                                 :style="page.rotation ? ('transform: rotate(' + page.rotation + 'deg)') : ''">
                        </button>
                    </template>
                    <template x-if="!page.previewUrl">
                        <div class="h-16 w-16 rounded-lg ring-1 ring-gray-200 bg-white grid place-items-center">
                            <span class="text-[10px] font-bold text-brand">PDF</span>
                        </div>
                    </template>
                    <button type="button" x-show="page.previewUrl" x-cloak @click="rotatePage(index)"
                            class="absolute -bottom-1.5 -left-1.5 size-5 rounded-full bg-white text-brand ring-1 ring-gray-200 grid place-items-center"
                            :title="labels.rotate || 'Rotate'" :aria-label="labels.rotate || 'Rotate'">
                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5a7.5 7.5 0 1 1-9.2 11.7"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5V9h-4.5"/>
                        </svg>
                    </button>
                    <button type="button" @click="removePage(index)"
                            class="absolute -top-1.5 -right-1.5 size-5 rounded-full bg-white text-red-600 text-xs font-bold ring-1 ring-gray-200 grid place-items-center"
                            :aria-label="labels.remove">×</button>
                    <div x-show="expanded && page.previewUrl" x-cloak x-transition
                         class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
                         @keydown.escape.window="expanded = false"
                         @click.self="expanded = false">
                        <button type="button" class="absolute top-4 right-4 text-white/90 text-sm font-semibold" @click="expanded = false" x-text="labels.close"></button>
                        <img :src="page.previewUrl" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
                    </div>
                </li>
            </template>
        </ul>
    </div>

    <div id="{{ $hostId }}"></div>
</div>

@once
    @push('styles')
        <style>.mirror { transform: scaleX(-1); }</style>
    @endpush
    @push('scripts')
    <script>
        function multiPageDocumentUpload(labels, fieldName, hostId, maxPages = 12, autoFinishUpload = false) {
            return {
                labels: labels || {},
                fieldName,
                hostId,
                maxPages: maxPages || 12,
                autoFinishUpload: !!autoFinishUpload,
                pages: [],
                fromCamera: false,
                cameraOpen: false,
                cameraNotice: null,
                stream: null,
                facingMode: 'environment',
                frameOrientation: 'portrait',
                nextId: 1,
                resetCapture() {
                    this.pages.forEach((page) => {
                        if (page?.previewUrl) URL.revokeObjectURL(page.previewUrl);
                    });
                    this.pages = [];
                    this.nextId = 1;
                    this.cameraNotice = null;
                    this.fromCamera = false;
                    this.frameOrientation = 'portrait';
                    this.syncInputs();
                    this.$dispatch('document-pages-changed', { name: this.fieldName, count: 0 });
                },
                async openCamera() {
                    this.cameraNotice = null;
                    if (this.pages.length >= this.maxPages) {
                        this.cameraNotice = this.labels.maxPages;
                        return;
                    }
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
                        await this.waitForVideoReady(video);
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
                    this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
                    this.stopStream();
                    try {
                        this.stream = await this.requestCameraStream(this.facingMode);
                        const video = this.$refs.camVideo;
                        if (!video) throw new Error(this.labels.cameraUnsupported);
                        video.srcObject = this.stream;
                        video.muted = true;
                        await this.waitForVideoReady(video);
                        await video.play();
                    } catch (e) {
                        this.cameraNotice = e?.name === 'NotAllowedError'
                            ? this.labels.cameraDenied
                            : (e?.message || this.labels.cameraDenied);
                    }
                },
                async waitForVideoReady(video) {
                    if (video.readyState >= 2 && video.videoWidth > 0) return;
                    await new Promise((resolve, reject) => {
                        const timeout = setTimeout(() => reject(new Error(this.labels.cameraDenied)), 15000);
                        const done = () => { clearTimeout(timeout); video.removeEventListener('loadedmetadata', done); resolve(); };
                        video.addEventListener('loadedmetadata', done);
                    });
                },
                async requestCameraStream(facing = 'environment') {
                    const attempts = [
                        { video: { facingMode: { ideal: facing }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false },
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
                dismissCamera() {
                    this.closeCamera();
                },
                finishFromCamera() {
                    this.closeCamera();
                    if (this.autoFinishUpload && this.pages.length > 0) {
                        this.finishUpload();
                    }
                },
                finishUpload() {
                    this.syncInputs();
                    this.$dispatch('kf-document-pages-ready', {
                        hostId: this.hostId,
                        name: this.fieldName,
                        count: this.pages.length,
                    });
                },
                stopStream() {
                    if (this.stream) {
                        this.stream.getTracks().forEach(t => t.stop());
                        this.stream = null;
                    }
                },
                capturePage() {
                    if (this.pages.length >= this.maxPages) {
                        this.cameraNotice = this.labels.maxPages;
                        return;
                    }
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
                        this.fromCamera = true;
                        this.addBlob(blob, 'page-' + (this.pages.length + 1) + '.jpg');
                        if (this.maxPages === 1) {
                            this.finishFromCamera();
                        }
                    }, 'image/jpeg', 0.92);
                },
                addFiles(event) {
                    const files = event.target.files;
                    if (!files?.length) return;
                    this.cameraNotice = null;
                    const maxBytes = 5120 * 1024;
                    for (const file of files) {
                        if (this.pages.length >= this.maxPages) {
                            this.cameraNotice = this.labels.maxPages;
                            break;
                        }
                        const type = (file.type || '').toLowerCase();
                        const name = (file.name || '').toLowerCase();
                        const allowed = type.startsWith('image/') || type === 'application/pdf' || /\.pdf$/i.test(name) || /\.(jpe?g|png|webp|gif)$/i.test(name);
                        if (!allowed) {
                            this.cameraNotice = @js(__('borrower.document_upload.file_invalid_type'));
                            continue;
                        }
                        if ((file.size || 0) > maxBytes) {
                            this.cameraNotice = @js(__('borrower.document_upload.file_too_large'));
                            continue;
                        }
                        if ((file.size || 0) <= 0) {
                            this.cameraNotice = @js(__('borrower.document_upload.file_invalid_type'));
                            continue;
                        }
                        this.fromCamera = false;
                        this.addBlob(file, file.name);
                    }
                    event.target.value = '';
                    if (this.autoFinishUpload && this.pages.length > 0) {
                        this.$nextTick(() => this.finishUpload());
                    }
                },
                addBlob(blob, name) {
                    const isPdf = (blob.type || '').includes('pdf') || /\.pdf$/i.test(name || '');
                    const previewUrl = isPdf ? null : URL.createObjectURL(blob);
                    this.pages.push({ id: this.nextId++, blob, name, previewUrl, isPdf, rotation: 0 });
                    this.syncInputs();
                    this.$dispatch('document-pages-changed', { name: this.fieldName, count: this.pages.length });
                },
                rotatePage(index) {
                    const page = this.pages[index];
                    if (! page || ! page.previewUrl || page.isPdf) return;
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.naturalHeight;
                        canvas.height = img.naturalWidth;
                        const ctx = canvas.getContext('2d');
                        ctx.translate(canvas.width / 2, canvas.height / 2);
                        ctx.rotate(Math.PI / 2);
                        ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);
                        canvas.toBlob((blob) => {
                            if (! blob) return;
                            if (page.previewUrl) URL.revokeObjectURL(page.previewUrl);
                            const next = {
                                ...page,
                                blob,
                                previewUrl: URL.createObjectURL(blob),
                                rotation: 0,
                                name: (page.name || 'page.jpg').replace(/\.[^.]+$/, '') + '.jpg',
                            };
                            this.pages.splice(index, 1, next);
                            this.syncInputs();
                        }, 'image/jpeg', 0.92);
                    };
                    img.src = page.previewUrl;
                },
                removePage(index) {
                    const page = this.pages[index];
                    if (page?.previewUrl) URL.revokeObjectURL(page.previewUrl);
                    this.pages.splice(index, 1);
                    this.syncInputs();
                    this.$dispatch('document-pages-changed', { name: this.fieldName, count: this.pages.length });
                },
                syncInputs() {
                    const host = document.getElementById(this.hostId);
                    if (!host) return;
                    host.innerHTML = '';
                    this.pages.forEach((page, index) => {
                        const input = document.createElement('input');
                        input.type = 'file';
                        input.name = this.maxPages === 1 ? this.fieldName : this.fieldName + '[]';
                        input.className = 'sr-only';
                        const dt = new DataTransfer();
                        const file = page.blob instanceof File
                            ? page.blob
                            : new File([page.blob], page.name || `page-${index + 1}.jpg`, { type: page.blob.type || 'image/jpeg' });
                        dt.items.add(file);
                        input.files = dt.files;
                        host.appendChild(input);
                    });
                },
            };
        }
    </script>
    @endpush
@endonce
