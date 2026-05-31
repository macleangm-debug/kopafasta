@props([
    'angle',
    'label',
    'instruction',
    'uploadUrl',
    'existingUrl' => null,
    'disabled' => false,
])

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden" x-data="faceCapture(@js($disabled))">
    <div class="px-5 py-4 border-b border-gray-100">
        <p class="font-semibold text-gray-900">{{ $label }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ $instruction }}</p>
    </div>

    <div class="p-5 space-y-4">
        @if ($existingUrl)
            <div class="relative rounded-xl overflow-hidden ring-1 ring-gray-200 bg-gray-50">
                <img src="{{ $existingUrl }}" alt="{{ $label }}" class="w-full max-h-56 object-cover">
                @unless($disabled)
                    <p class="text-xs text-center text-gray-500 py-2">Upload again to replace this photo.</p>
                @endunless
            </div>
        @endif

        @unless($disabled)
            <div x-show="mode === 'camera'" x-cloak class="space-y-3">
                <video x-ref="video" autoplay playsinline muted class="w-full rounded-xl bg-black max-h-64 object-cover"></video>
                <canvas x-ref="canvas" class="hidden"></canvas>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="capture()" class="bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2 rounded-full">
                        Capture photo
                    </button>
                    <button type="button" @click="stopCamera(); mode='file'" class="bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-2 rounded-full">
                        Use gallery instead
                    </button>
                </div>
            </div>

            <form x-ref="form" method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" class="space-y-3" x-show="mode === 'file' || previewUrl" x-cloak>
                @csrf
                <input type="file" name="photo" x-ref="fileInput" accept="image/*" capture="user" class="w-full text-sm" @change="onFileSelected($event)">
                <div x-show="previewUrl" class="rounded-xl overflow-hidden ring-1 ring-gray-200">
                    <img :src="previewUrl" alt="Preview" class="w-full max-h-56 object-cover">
                </div>
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2.5 rounded-full text-sm">
                    Save photo
                </button>
            </form>

            <div x-show="mode === 'choose'" class="grid sm:grid-cols-2 gap-3">
                <button type="button" @click="startCamera()" class="rounded-xl ring-1 ring-gray-200 hover:bg-gray-50 px-4 py-6 text-sm font-semibold text-gray-800">
                    Use camera
                </button>
                <button type="button" @click="mode='file'" class="rounded-xl ring-1 ring-gray-200 hover:bg-gray-50 px-4 py-6 text-sm font-semibold text-gray-800">
                    Upload from gallery
                </button>
            </div>
        @else
            <p class="text-sm text-gray-500">Photo locked while verification is pending or approved.</p>
        @endunless
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('faceCapture', (disabled = false) => ({
                    mode: disabled ? 'locked' : 'choose',
                    previewUrl: null,
                    stream: null,

                    async startCamera() {
                        if (!navigator.mediaDevices?.getUserMedia) {
                            this.mode = 'file';
                            return;
                        }
                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({
                                video: { facingMode: 'user' },
                                audio: false,
                            });
                            this.$refs.video.srcObject = this.stream;
                            this.mode = 'camera';
                        } catch (e) {
                            this.mode = 'file';
                        }
                    },

                    stopCamera() {
                        if (this.stream) {
                            this.stream.getTracks().forEach(track => track.stop());
                            this.stream = null;
                        }
                    },

                    capture() {
                        const video = this.$refs.video;
                        const canvas = this.$refs.canvas;
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        canvas.getContext('2d').drawImage(video, 0, 0);
                        canvas.toBlob((blob) => {
                            if (!blob) return;
                            const file = new File([blob], 'capture.jpg', { type: 'image/jpeg' });
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            this.$refs.fileInput.files = dt.files;
                            this.previewUrl = URL.createObjectURL(blob);
                            this.stopCamera();
                            this.mode = 'file';
                        }, 'image/jpeg', 0.92);
                    },

                    onFileSelected(event) {
                        const file = event.target.files?.[0];
                        if (!file) return;
                        this.previewUrl = URL.createObjectURL(file);
                    },
                }));
            });
        </script>
    @endpush
@endonce
