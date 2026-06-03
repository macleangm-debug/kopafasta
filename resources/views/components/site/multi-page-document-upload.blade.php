@props([
    'name' => 'document_pages',
    'inputHostId' => null,
    'labels' => [],
])

@php
    $hostId = $inputHostId ?? ('doc-pages-'.md5($name));
    $labelDefaults = [
        'hint' => __('borrower.profile.multi_page_hint'),
        'uploadFile' => __('borrower.profile.multi_page_upload'),
        'capturePage' => __('borrower.profile.multi_page_capture'),
        'close' => __('borrower.profile.multi_page_close'),
        'pageLabel' => __('borrower.profile.multi_page_page'),
        'remove' => __('borrower.profile.multi_page_remove'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'cameraUnsupported' => __('borrower.profile.camera_unsupported'),
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
    ];
    $mergedLabels = array_merge($labelDefaults, $labels);
@endphp

<div class="space-y-4" x-data="multiPageDocumentUpload(@js($mergedLabels), @js($name), @js($hostId))">
    <p class="text-xs text-gray-500" x-text="labels.hint"></p>

    <div class="flex flex-wrap gap-2">
        <label class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm cursor-pointer">
            <span x-text="labels.uploadFile"></span>
            <input type="file" accept="image/*,application/pdf" multiple class="sr-only" @change="addFiles($event)">
        </label>
        <button type="button" @click="openCamera()" class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm" x-text="labels.capturePage">
        </button>
    </div>

    <p x-show="cameraNotice" x-cloak class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2" x-text="cameraNotice"></p>

    <div x-show="cameraOpen" x-cloak class="rounded-2xl overflow-hidden ring-1 ring-gray-200 bg-black">
        <video x-ref="camVideo" autoplay playsinline webkit-playsinline muted class="w-full max-h-72 object-cover mirror"></video>
        <div class="p-3 flex gap-2 bg-white">
            <button type="button" @click="capturePage()" class="flex-1 bg-gray-900 text-white font-semibold px-4 py-2 rounded-xl text-sm" x-text="labels.capturePage"></button>
            <button type="button" @click="closeCamera()" class="px-4 py-2 rounded-xl text-sm ring-1 ring-gray-200" x-text="labels.close"></button>
        </div>
    </div>

    <ul class="space-y-2" x-show="pages.length > 0">
        <template x-for="(page, index) in pages" :key="page.id">
            <li class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2 text-sm">
                <span x-text="labels.pageLabel + ' ' + (index + 1)"></span>
                <button type="button" @click="removePage(index)" class="text-xs font-semibold text-red-600 hover:underline" x-text="labels.remove"></button>
            </li>
        </template>
    </ul>

    <div id="{{ $hostId }}"></div>
</div>

@once
    @push('styles')
        <style>.mirror { transform: scaleX(-1); }</style>
    @endpush
    @push('scripts')
    <script>
        function multiPageDocumentUpload(labels, fieldName, hostId) {
            return {
                labels: labels || {},
                fieldName,
                hostId,
                pages: [],
                cameraOpen: false,
                cameraNotice: null,
                stream: null,
                nextId: 1,
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
                        this.stream = await this.requestCameraStream();
                        const video = this.$refs.camVideo;
                        if (!video) {
                            throw new Error(this.labels.cameraUnsupported);
                        }
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
                    if (video.readyState >= 2 && video.videoWidth > 0) {
                        return;
                    }
                    await new Promise((resolve, reject) => {
                        const timeout = setTimeout(() => reject(new Error(this.labels.cameraDenied)), 15000);
                        const done = () => {
                            clearTimeout(timeout);
                            video.removeEventListener('loadedmetadata', done);
                            resolve();
                        };
                        video.addEventListener('loadedmetadata', done);
                    });
                },
                async requestCameraStream() {
                    const attempts = [
                        { video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                        { video: { facingMode: 'user' }, audio: false },
                        { video: true, audio: false },
                    ];
                    let lastError;
                    for (const constraints of attempts) {
                        try {
                            return await navigator.mediaDevices.getUserMedia(constraints);
                        } catch (e) {
                            lastError = e;
                        }
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
                        this.addBlob(blob, 'camera-page.jpg');
                        this.closeCamera();
                    }, 'image/jpeg', 0.92);
                },
                addFiles(event) {
                    const files = event.target.files;
                    if (!files?.length) return;
                    for (const file of files) this.addBlob(file, file.name);
                    event.target.value = '';
                },
                addBlob(blob, name) {
                    this.pages.push({ id: this.nextId++, blob, name });
                    this.syncInputs();
                },
                removePage(index) {
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
