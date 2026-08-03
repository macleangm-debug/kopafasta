@props([
    'name' => 'document_pages',
    'inputHostId' => null,
    'labels' => [],
    'maxPages' => 12,
])

@php
    $hostId = $inputHostId ?? ('doc-pages-'.md5($name));
    $labelDefaults = [
        'hint' => __('borrower.profile.multi_page_hint_short'),
        'uploadFile' => __('borrower.profile.multi_page_upload'),
        'capturePage' => __('borrower.profile.multi_page_capture'),
        'close' => __('borrower.profile.multi_page_close'),
        'pageLabel' => __('borrower.profile.multi_page_page'),
        'remove' => __('borrower.profile.multi_page_remove'),
        'addAnother' => __('borrower.profile.multi_page_add_another'),
        'pagesReady' => __('borrower.profile.multi_page_pages_ready'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'cameraUnsupported' => __('borrower.profile.camera_unsupported'),
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
        'maxPages' => __('borrower.profile.multi_page_max', ['max' => $maxPages]),
    ];
    $mergedLabels = array_merge($labelDefaults, $labels);
@endphp

<div class="space-y-4" x-data="multiPageDocumentUpload(@js($mergedLabels), @js($name), @js($hostId), {{ (int) $maxPages }})">
    <div class="flex flex-wrap gap-2">
        <label class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm cursor-pointer">
            <span x-text="labels.uploadFile"></span>
            <input type="file" accept="image/*,application/pdf" multiple class="sr-only" @change="addFiles($event)">
        </label>
        <button type="button" @click="openCamera()" class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm" x-text="labels.capturePage">
        </button>
    </div>

    <p x-show="cameraNotice" x-cloak class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2" x-text="cameraNotice"></p>

    {{-- Fullscreen camera --}}
    <template x-teleport="body">
        <div x-show="cameraOpen" x-cloak class="fixed inset-0 z-[95] bg-black flex flex-col">
            <video x-ref="camVideo" autoplay playsinline webkit-playsinline muted class="absolute inset-0 w-full h-full object-cover"></video>
            <div class="relative z-[2] mt-auto px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-6 bg-gradient-to-t from-black/85 to-transparent">
                <div x-show="pages.length" class="flex gap-2 overflow-x-auto justify-center mb-4 pb-1">
                    <template x-for="(page, index) in pages" :key="'live-'+page.id">
                        <div class="relative shrink-0">
                            <template x-if="page.previewUrl">
                                <img :src="page.previewUrl" alt="" class="size-12 rounded-lg object-cover ring-2 ring-white/80">
                            </template>
                            <template x-if="!page.previewUrl">
                                <div class="size-12 rounded-lg bg-white/20 ring-2 ring-white/80 grid place-items-center text-[10px] font-bold text-white">PDF</div>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="flex gap-2 max-w-lg mx-auto">
                    <button type="button" @click="capturePage()" class="flex-1 bg-brand-gold text-brand font-bold px-4 py-3.5 rounded-full text-sm" x-text="labels.capturePage"></button>
                    <button type="button" @click="closeCamera()" class="px-5 py-3.5 rounded-full text-sm font-semibold bg-white/15 text-white ring-1 ring-white/30" x-text="labels.close"></button>
                </div>
            </div>
        </div>
    </template>

    {{-- Page gallery --}}
    <div x-show="pages.length > 0" x-cloak class="rounded-2xl ring-1 ring-brand/15 bg-white p-4">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="text-sm font-semibold text-gray-900">
                <span x-text="labels.pagesReady.replace(':count', String(pages.length))"></span>
            </p>
            <button type="button" @click="openCamera()" :disabled="pages.length >= maxPages"
                    class="text-xs font-semibold text-amber-700 hover:underline disabled:opacity-40" x-text="labels.addAnother"></button>
        </div>
        <ul class="grid grid-cols-3 sm:grid-cols-4 gap-3">
            <template x-for="(page, index) in pages" :key="page.id">
                <li class="relative rounded-xl overflow-hidden ring-1 ring-gray-200 bg-gray-50 aspect-[3/4]">
                    <template x-if="page.previewUrl">
                        <img :src="page.previewUrl" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!page.previewUrl">
                        <div class="absolute inset-0 grid place-items-center text-xs font-bold text-brand">PDF</div>
                    </template>
                    <div class="absolute inset-x-0 bottom-0 bg-black/55 px-2 py-1.5 flex items-center justify-between gap-1">
                        <span class="text-[10px] font-semibold text-white truncate" x-text="labels.pageLabel + ' ' + (index + 1)"></span>
                        <button type="button" @click="removePage(index)" class="text-[10px] font-bold text-red-200 hover:text-white" x-text="labels.remove"></button>
                    </div>
                </li>
            </template>
        </ul>
        <p class="text-[11px] text-gray-500 mt-3" x-text="labels.hint"></p>
    </div>

    <div id="{{ $hostId }}"></div>
</div>

@once
    @push('scripts')
    <script>
        function multiPageDocumentUpload(labels, fieldName, hostId, maxPages = 12) {
            return {
                labels: labels || {},
                fieldName,
                hostId,
                maxPages: maxPages || 12,
                pages: [],
                cameraOpen: false,
                cameraNotice: null,
                stream: null,
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
                        this.stream = await this.requestCameraStream();
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
                async waitForVideoReady(video) {
                    if (video.readyState >= 2 && video.videoWidth > 0) return;
                    await new Promise((resolve, reject) => {
                        const timeout = setTimeout(() => reject(new Error(this.labels.cameraDenied)), 15000);
                        const done = () => { clearTimeout(timeout); video.removeEventListener('loadedmetadata', done); resolve(); };
                        video.addEventListener('loadedmetadata', done);
                    });
                },
                async requestCameraStream() {
                    const attempts = [
                        { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false },
                        { video: { facingMode: 'environment' }, audio: false },
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
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    canvas.toBlob(blob => {
                        if (!blob) return;
                        this.addBlob(blob, 'page-' + (this.pages.length + 1) + '.jpg');
                        // Keep camera open so user can add more pages quickly.
                    }, 'image/jpeg', 0.92);
                },
                addFiles(event) {
                    const files = event.target.files;
                    if (!files?.length) return;
                    for (const file of files) {
                        if (this.pages.length >= this.maxPages) {
                            this.cameraNotice = this.labels.maxPages;
                            break;
                        }
                        this.addBlob(file, file.name);
                    }
                    event.target.value = '';
                },
                addBlob(blob, name) {
                    const isPdf = (blob.type || '').includes('pdf') || /\.pdf$/i.test(name || '');
                    const previewUrl = isPdf ? null : URL.createObjectURL(blob);
                    this.pages.push({ id: this.nextId++, blob, name, previewUrl, isPdf });
                    this.syncInputs();
                },
                removePage(index) {
                    const page = this.pages[index];
                    if (page?.previewUrl) URL.revokeObjectURL(page.previewUrl);
                    this.pages.splice(index, 1);
                    this.syncInputs();
                },
                syncInputs() {
                    const host = document.getElementById(this.hostId);
                    if (!host) return;
                    host.innerHTML = '';
                    this.pages.forEach((page, index) => {
                        const input = document.createElement('input');
                        input.type = 'file';
                        input.name = this.fieldName + '[]';
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
