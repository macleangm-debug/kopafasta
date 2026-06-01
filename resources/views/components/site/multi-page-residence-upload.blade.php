@props(['name' => 'residence_letter_pages'])

<div class="space-y-4" x-data="multiPageResidenceUpload(@js([
    'hint' => __('borrower.profile.residence_upload_hint'),
    'uploadFile' => __('borrower.profile.residence_upload_file'),
    'capturePage' => __('borrower.profile.residence_capture_page'),
    'close' => __('borrower.profile.residence_close'),
    'pageLabel' => __('borrower.profile.residence_page_label'),
    'remove' => __('borrower.profile.residence_remove'),
]))">
    <p class="text-xs text-gray-500" x-text="labels.hint"></p>

    <div class="flex flex-wrap gap-2">
        <label class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm cursor-pointer">
            <span x-text="labels.uploadFile"></span>
            <input type="file" accept="image/*,application/pdf" class="sr-only" @change="addFile($event)">
        </label>
        <button type="button" @click="openCamera()" class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm" x-text="labels.capturePage">
        </button>
    </div>

    <div x-show="cameraOpen" x-cloak class="rounded-2xl overflow-hidden ring-1 ring-gray-200 bg-black">
        <video x-ref="camVideo" autoplay playsinline muted class="w-full max-h-72 object-cover mirror"></video>
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

    <div id="residence-page-inputs"></div>
</div>

@once
    @push('styles')
        <style>.mirror { transform: scaleX(-1); }</style>
    @endpush
    @push('scripts')
    <script>
        function multiPageResidenceUpload(labels) {
            return {
                labels: labels || {},
                pages: [],
                cameraOpen: false,
                stream: null,
                nextId: 1,
                async openCamera() {
                    if (!navigator.mediaDevices?.getUserMedia) return;
                    this.cameraOpen = true;
                    await this.$nextTick();
                    const constraints = { video: { facingMode: 'environment' }, audio: false };
                    if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                        constraints.video.facingMode = 'environment';
                    }
                    this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                    this.$refs.camVideo.srcObject = this.stream;
                    await this.$refs.camVideo.play();
                },
                closeCamera() {
                    if (this.stream) {
                        this.stream.getTracks().forEach(t => t.stop());
                        this.stream = null;
                    }
                    this.cameraOpen = false;
                },
                capturePage() {
                    const video = this.$refs.camVideo;
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    canvas.toBlob(blob => {
                        if (!blob) return;
                        this.addBlob(blob, 'camera-page.jpg');
                        this.closeCamera();
                    }, 'image/jpeg', 0.92);
                },
                addFile(event) {
                    const file = event.target.files?.[0];
                    if (file) this.addBlob(file, file.name);
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
                    const host = document.getElementById('residence-page-inputs');
                    host.innerHTML = '';
                    this.pages.forEach((page, index) => {
                        const input = document.createElement('input');
                        input.type = 'file';
                        input.name = '{{ $name }}[]';
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
