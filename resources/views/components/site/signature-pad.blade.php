@props([
    'name' => 'signature_data',
    'signerName' => 'signer_name',
    'defaultName' => '',
    'readonlyName' => false,
    'verified' => false,
    'includeInForm' => true,
    'initialDataUrl' => '',
])

<div x-data="signaturePad(@js($defaultName), @js((bool) $readonlyName), @js($initialDataUrl))" class="space-y-4" data-signature-pad>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.signature_legal_name') }}</label>
        @if ($readonlyName)
            <div class="rounded-lg ring-1 ring-gray-200 bg-gray-50 px-3 py-3">
                <p class="text-sm font-semibold text-gray-900">{{ $defaultName }}</p>
                @if ($verified)
                    <p class="text-xs font-semibold text-emerald-700 mt-1">{{ __('borrower.apply.signature_verified') }}</p>
                @endif
            </div>
            @if ($includeInForm)
                <input type="hidden" name="{{ $signerName }}" value="{{ $defaultName }}">
            @endif
        @else
            <input type="text" @if ($includeInForm) name="{{ $signerName }}" @endif x-model="signerName" required
                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
        @endif
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.signature_draw_label') }}</label>
        <div class="rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
            <canvas x-ref="canvas" width="600" height="180" class="w-full touch-none cursor-crosshair bg-white"
                    @mousedown="startDraw($event)" @mousemove="draw($event)" @mouseup="endDraw()" @mouseleave="endDraw()"
                    @touchstart.prevent="startDraw($event)" @touchmove.prevent="draw($event)" @touchend.prevent="endDraw()"></canvas>
        </div>
        <div class="flex justify-between items-center mt-2">
            <button type="button" @click="clear()" class="text-xs font-semibold text-gray-600 hover:text-gray-900">{{ __('borrower.apply.signature_clear') }}</button>
            <p class="text-[11px] text-gray-500">{{ __('borrower.apply.signature_draw_hint') }}</p>
        </div>
    </div>
    <input type="hidden" @if ($includeInForm) name="{{ $name }}" @endif x-ref="hidden" :value="dataUrl">
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('signaturePad', (defaultName = '', readonlyName = false, initialDataUrl = '') => ({
                    signerName: defaultName,
                    readonlyName,
                    dataUrl: initialDataUrl || '',
                    drawing: false,
                    ctx: null,
                    init() {
                        this.$nextTick(() => {
                            const canvas = this.$refs.canvas;
                            this.ctx = canvas.getContext('2d');
                            this.ctx.strokeStyle = '#111827';
                            this.ctx.lineWidth = 2;
                            this.ctx.lineCap = 'round';
                            if (this.dataUrl) {
                                this.loadFromDataUrl(this.dataUrl);
                            }
                        });
                    },
                    loadFromDataUrl(url) {
                        if (! url || ! String(url).startsWith('data:image')) {
                            return;
                        }
                        this.dataUrl = url;
                        this.syncInput();
                        const canvas = this.$refs.canvas;
                        if (! canvas) return;
                        const img = new Image();
                        img.onload = () => {
                            if (! this.ctx) {
                                this.ctx = canvas.getContext('2d');
                                this.ctx.strokeStyle = '#111827';
                                this.ctx.lineWidth = 2;
                                this.ctx.lineCap = 'round';
                            }
                            this.ctx.clearRect(0, 0, canvas.width, canvas.height);
                            this.ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        };
                        img.src = url;
                    },
                    pos(e) {
                        const canvas = this.$refs.canvas;
                        const rect = canvas.getBoundingClientRect();
                        const touch = e.touches ? e.touches[0] : e;
                        return {
                            x: (touch.clientX - rect.left) * (canvas.width / rect.width),
                            y: (touch.clientY - rect.top) * (canvas.height / rect.height),
                        };
                    },
                    startDraw(e) {
                        this.drawing = true;
                        const p = this.pos(e);
                        this.ctx.beginPath();
                        this.ctx.moveTo(p.x, p.y);
                    },
                    syncInput() {
                        if (this.$refs.hidden) {
                            this.$refs.hidden.value = this.dataUrl || '';
                        }
                    },
                    draw(e) {
                        if (!this.drawing) return;
                        const p = this.pos(e);
                        this.ctx.lineTo(p.x, p.y);
                        this.ctx.stroke();
                        this.dataUrl = this.$refs.canvas.toDataURL('image/png');
                        this.syncInput();
                    },
                    endDraw() {
                        this.drawing = false;
                        this.dataUrl = this.$refs.canvas.toDataURL('image/png');
                        this.syncInput();
                    },
                    clear() {
                        const canvas = this.$refs.canvas;
                        this.ctx.clearRect(0, 0, canvas.width, canvas.height);
                        this.dataUrl = '';
                        this.syncInput();
                    },
                }));
            });
        </script>
    @endpush
@endonce
