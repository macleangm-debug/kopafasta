@props([
    'name' => 'signature_data',
    'signerName' => 'signer_name',
    'defaultName' => '',
    'readonlyName' => false,
    'verified' => false,
    'includeInForm' => true,
    'initialDataUrl' => '',
    'compact' => false,
    'hideClear' => false,
])

<div x-data="signaturePad(@js($defaultName), @js((bool) $readonlyName), @js($initialDataUrl))"
     class="{{ $compact ? 'space-y-3' : 'space-y-4' }}"
     data-signature-pad>
    @unless ($compact)
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
    @else
        @if ($includeInForm && $readonlyName)
            <input type="hidden" name="{{ $signerName }}" value="{{ $defaultName }}">
        @endif
    @endunless

    <div>
        @unless ($compact)
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.signature_draw_label') }}</label>
        @endunless
        <div class="rounded-2xl ring-1 ring-brand/15 bg-[linear-gradient(180deg,#f8faf9_0%,#ffffff_55%)] overflow-hidden">
            <canvas x-ref="canvas" width="900" height="{{ $compact ? 220 : 270 }}"
                    class="w-full touch-none cursor-crosshair bg-transparent"
                    @mousedown="startDraw($event)" @mousemove="draw($event)" @mouseup="endDraw()" @mouseleave="endDraw()"
                    @touchstart.prevent="startDraw($event)" @touchmove.prevent="draw($event)" @touchend.prevent="endDraw()"></canvas>
        </div>
        <div class="flex {{ $hideClear ? 'justify-end' : 'justify-between' }} items-center mt-2.5 gap-3">
            @unless ($hideClear)
                <button type="button" @click="clear()" class="text-xs font-semibold text-brand/80 hover:text-brand">{{ __('borrower.apply.signature_clear') }}</button>
            @endunless
            <p class="text-[11px] text-gray-500">{{ __('borrower.apply.signature_draw_hint') }}</p>
        </div>
    </div>
    <input type="hidden" @if ($includeInForm) name="{{ $name }}" @endif x-ref="hidden" :value="dataUrl">
    <input type="hidden" name="signature_touched" value="0" x-ref="touched">
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
                    lastPoint: null,
                    initCanvas() {
                        const canvas = this.$refs.canvas;
                        this.ctx = canvas.getContext('2d');
                        this.ctx.strokeStyle = '#004d40';
                        this.ctx.lineWidth = 2.5;
                        this.ctx.lineCap = 'round';
                        this.ctx.lineJoin = 'round';
                    },
                    init() {
                        this.$nextTick(() => {
                            this.initCanvas();
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
                                this.initCanvas();
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
                        if (this.$refs.touched) {
                            this.$refs.touched.value = '1';
                        }
                        this.lastPoint = this.pos(e);
                        this.ctx.beginPath();
                        this.ctx.moveTo(this.lastPoint.x, this.lastPoint.y);
                    },
                    syncInput() {
                        if (this.$refs.hidden) {
                            this.$refs.hidden.value = this.dataUrl || '';
                        }
                    },
                    draw(e) {
                        if (!this.drawing) return;
                        const p = this.pos(e);
                        const mid = {
                            x: (this.lastPoint.x + p.x) / 2,
                            y: (this.lastPoint.y + p.y) / 2,
                        };
                        this.ctx.quadraticCurveTo(this.lastPoint.x, this.lastPoint.y, mid.x, mid.y);
                        this.ctx.stroke();
                        this.lastPoint = p;
                        if (this.$refs.touched?.value === '1') {
                            this.dataUrl = this.$refs.canvas.toDataURL('image/png');
                            this.syncInput();
                        }
                    },
                    endDraw() {
                        if (this.drawing && this.lastPoint) {
                            this.ctx.lineTo(this.lastPoint.x, this.lastPoint.y);
                            this.ctx.stroke();
                        }
                        this.drawing = false;
                        this.lastPoint = null;
                        if (this.$refs.touched?.value === '1') {
                            this.dataUrl = this.$refs.canvas.toDataURL('image/png');
                            this.syncInput();
                        }
                    },
                    clear() {
                        const canvas = this.$refs.canvas;
                        this.ctx.clearRect(0, 0, canvas.width, canvas.height);
                        this.dataUrl = '';
                        if (this.$refs.touched) {
                            this.$refs.touched.value = '0';
                        }
                        this.syncInput();
                    },
                }));
            });
        </script>
    @endpush
@endonce
