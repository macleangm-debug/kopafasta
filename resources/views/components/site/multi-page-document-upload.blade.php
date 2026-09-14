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
    ];
    $mergedLabels = array_merge($labelDefaults, $labels);
@endphp

<div class="space-y-4" x-data="multiPageDocumentUpload(@js($mergedLabels), @js($name), @js($hostId), {{ (int) $maxPages }}, @js($autoFinishUpload))"
     @document-open-camera.window="if ($event.detail?.hostId === hostId) { fromCamera = true; openCamera(); }"
     @document-open-upload.window="if ($event.detail?.hostId === hostId) $refs.fileInput?.click()">
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
                <button type="button" @click="dismissCamera()"
                        class="shrink-0 rounded-full bg-white/15 text-white text-xs font-semibold px-3 py-2 ring-1 ring-white/25"
                        x-text="labels.close"></button>
            </div>
            <video x-ref="camVideo" autoplay playsinline webkit-playsinline muted
                   class="absolute inset-0 w-full h-full object-cover"
                   :class="facingMode === 'user' ? 'mirror' : ''"></video>
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
                        </div>
                    </template>
                </div>
                <div class="flex items-center gap-2 max-w-lg mx-auto">
                    <button type="button" @click="toggleFacing()"
                            class="shrink-0 rounded-full bg-white/15 text-white text-xs font-semibold px-3.5 py-3.5 ring-1 ring-white/30 min-w-[7.5rem]"
                            x-text="facingMode === 'user' ? labels.useBackCamera : labels.useFrontCamera"></button>
                    <button type="button" @click="capturePage()"
                            class="flex-1 font-bold px-4 py-3.5 rounded-full text-sm"
                            :class="pages.length ? 'bg-white/15 text-white ring-1 ring-white/30' : 'bg-brand-gold text-brand'"
                            x-text="pages.length ? labels.captureMore : labels.capturePage"></button>
                    <button type="button" x-show="pages.length" x-cloak @click="finishFromCamera()"
                            class="flex-1 bg-brand-gold text-brand font-bold px-4 py-3.5 rounded-full text-sm"
                            x-text="labels.finish"></button>
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
                            class="absolute -bottom-1.5 -left-1.5 size-5 rounded-full bg-white text-brand text-[10px] font-bold ring-1 ring-gray-200 grid place-items-center"
                            :title="labels.rotate || 'Rotate'" :aria-label="labels.rotate || 'Rotate'">⟳</button>
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
                nextId: 1,
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
