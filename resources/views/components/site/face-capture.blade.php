@props([
    'angle',
    'label',
    'instruction',
    'uploadUrl',
    'existingUrl' => null,
    'disabled' => false,
])

{{-- Legacy wrapper: premium front-camera capture for any leftover face-angle forms. --}}
<div class="bg-white rounded-2xl ring-1 ring-brand/15 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <p class="font-semibold text-gray-900">{{ $label }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ $instruction }}</p>
    </div>

    <div class="p-5 space-y-4" x-data="faceCapture(@js($disabled), @js($uploadUrl))">
        @if ($existingUrl)
            <div class="relative rounded-xl overflow-hidden ring-1 ring-brand/15 bg-gray-50">
                <img src="{{ $existingUrl }}" alt="{{ $label }}" class="w-full max-h-56 object-cover">
                @unless($disabled)
                    <p class="text-xs text-center text-gray-500 py-2">{{ __('borrower.nida.face_replace_hint') }}</p>
                @endunless
            </div>
        @endif

        @unless($disabled)
            <x-site.single-image-document-upload
                name="photo"
                facing="user"
                :required="! filled($existingUrl)"
                :input-host-id="'face-capture-'.md5($angle.$uploadUrl)"
            />
            <button type="button" @click="submitCapture()"
                    class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                {{ __('borrower.nida.face_capture_photo') }}
            </button>
            <p x-show="error" x-cloak class="text-xs text-red-600" x-text="error"></p>
        @endunless
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('faceCapture', (disabled = false, uploadUrl = '') => ({
                    disabled,
                    uploadUrl,
                    error: null,
                    async submitCapture() {
                        this.error = null;
                        const host = this.$el.querySelector('[id^="single-image-"], [id^="face-capture-"]')?.closest('[x-data]')
                            || this.$el.querySelector('input[type=file][name=photo]')?.closest('[x-data]');
                        const fileInput = this.$el.querySelector('input[type=file][name=photo]');
                        const file = fileInput?.files?.[0];
                        if (!file) {
                            this.error = 'Capture or add a photo first.';
                            return;
                        }
                        const fd = new FormData();
                        fd.append('photo', file);
                        fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');
                        try {
                            const res = await fetch(this.uploadUrl, {
                                method: 'POST',
                                body: fd,
                                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                                credentials: 'same-origin',
                            });
                            if (res.redirected) window.location.href = res.url;
                            else window.location.reload();
                        } catch (e) {
                            this.error = 'Upload failed. Try again.';
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
