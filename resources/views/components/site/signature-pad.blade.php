@props([
    'name' => 'signature_data',
    'signerName' => 'signer_name',
    'defaultName' => '',
    'readonlyName' => false,
    'verified' => false,
])

<div x-data="signaturePad(@js($defaultName), @js((bool) $readonlyName))" class="space-y-4" data-signature-pad>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.signature_legal_name') }}</label>
        @if ($readonlyName)
            <div class="rounded-lg ring-1 ring-gray-200 bg-gray-50 px-3 py-3">
                <p class="text-sm font-semibold text-gray-900">{{ $defaultName }}</p>
                @if ($verified)
                    <p class="text-xs font-semibold text-emerald-700 mt-1">{{ __('borrower.apply.signature_verified') }}</p>
                @endif
            </div>
            <input type="hidden" name="{{ $signerName }}" value="{{ $defaultName }}">
        @else
            <input type="text" name="{{ $signerName }}" x-model="signerName" required
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
    <input type="hidden" name="{{ $name }}" :value="dataUrl">
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('signaturePad', (defaultName = '', readonlyName = false) => ({
                    signerName: defaultName,
                    readonlyName,
                    dataUrl: '',
                    drawing: false,
                    ctx: null,
                    init() {
                        this.$nextTick(() => {
                            const canvas = this.$refs.canvas;
                            this.ctx = canvas.getContext('2d');
                            this.ctx.strokeStyle = '#111827';
                            this.ctx.lineWidth = 2;
                            this.ctx.lineCap = 'round';
                        });
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
                    draw(e) {
                        if (!this.drawing) return;
                        const p = this.pos(e);
                        this.ctx.lineTo(p.x, p.y);
                        this.ctx.stroke();
                        this.dataUrl = this.$refs.canvas.toDataURL('image/png');
                    },
                    endDraw() {
                        this.drawing = false;
                        this.dataUrl = this.$refs.canvas.toDataURL('image/png');
                    },
                    clear() {
                        const canvas = this.$refs.canvas;
                        this.ctx.clearRect(0, 0, canvas.width, canvas.height);
                        this.dataUrl = '';
                    },
                }));
            });
        </script>
    @endpush
@endonce
