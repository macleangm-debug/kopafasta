@php
    /** @var array<string, array{label_key: string, prefix: string, kind: string, category?: string}> $types */
    $selectedType = $selectedType ?? 'member';
    $number = $number ?? '';
    $action = $action ?? route('site.card.verify.lookup');
    $prefixes = collect($types)->mapWithKeys(fn ($meta, $key) => [$key => $meta['prefix']])->all();
    $typeLabels = collect($types)->mapWithKeys(fn ($meta, $key) => [$key => __($meta['label_key'])])->all();
    $typeKinds = collect($types)->mapWithKeys(fn ($meta, $key) => [$key => $meta['kind'] ?? 'member'])->all();
@endphp

<div class="relative overflow-hidden rounded-[1.5rem] bg-white/95 backdrop-blur shadow-[0_24px_60px_rgba(8,47,39,0.12)] ring-1 ring-brand/10 p-5 sm:p-7"
     x-data="cardVerifyForm(@js($selectedType), @js($prefixes), @js($typeLabels), @js($typeKinds))">
    <div class="absolute -right-16 -top-20 h-48 w-48 rounded-full bg-brand-gold/15 pointer-events-none"></div>
    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand via-brand-gold to-brand pointer-events-none"></div>

    <div class="relative">
        <p class="text-[11px] uppercase tracking-[0.18em] text-brand font-semibold">{{ __('site.card_verify.eyebrow') }}</p>
        <h1 class="mt-1.5 text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">{{ __('site.card_verify.heading') }}</h1>
        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ __('site.card_verify.subtitle') }}</p>

        <form method="POST" action="{{ $action }}" class="mt-6 space-y-4" x-ref="form">
            @csrf
            <input type="hidden" name="type" :value="type">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">{{ __('site.card_verify.type_label') }}</label>

                {{-- Desktop / tablet native select --}}
                <select x-model="type"
                        class="hidden sm:block w-full rounded-xl border-0 bg-brand-muted/40 ring-1 ring-brand/15 px-3.5 py-3 text-sm font-semibold text-gray-900 focus:ring-2 focus:ring-brand/30">
                    @foreach ($types as $key => $meta)
                        <option value="{{ $key }}">{{ __($meta['label_key']) }}</option>
                    @endforeach
                </select>

                {{-- Mobile: open bottom sheet --}}
                <button type="button"
                        @click="sheetOpen = true"
                        class="sm:hidden w-full flex items-center justify-between gap-3 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-3.5 py-3 text-left">
                    <span>
                        <span class="block text-[10px] uppercase tracking-wider text-brand/80 font-semibold">{{ __('site.card_verify.type_label') }}</span>
                        <span class="mt-0.5 block text-sm font-semibold text-gray-900" x-text="typeLabel"></span>
                    </span>
                    <svg class="w-5 h-5 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4 4 4-4"/></svg>
                </button>
                @error('type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">{{ __('site.card_verify.number_label') }}</label>
                <div class="flex rounded-xl overflow-hidden ring-1 ring-brand/15 focus-within:ring-2 focus-within:ring-brand/30 bg-white">
                    <span class="inline-flex items-center px-3 bg-gradient-to-b from-brand to-brand-light text-white text-xs sm:text-sm font-mono font-bold whitespace-nowrap"
                          x-text="prefix"></span>
                    <input type="text"
                           name="number"
                           x-ref="suffix"
                           value="{{ $number }}"
                           required
                           maxlength="24"
                           autocomplete="off"
                           spellcheck="false"
                           inputmode="text"
                           placeholder="{{ __('site.card_verify.number_placeholder') }}"
                           class="flex-1 min-w-0 border-0 px-3 py-3 text-sm font-mono tracking-wider uppercase focus:ring-0 bg-transparent">
                    <button type="button"
                            @click="startScan()"
                            class="inline-flex items-center justify-center px-3 text-brand hover:bg-brand-muted/50 shrink-0"
                            title="{{ __('site.card_verify.scan_aria') }}"
                            aria-label="{{ __('site.card_verify.scan_aria') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 8.5V6.75A2.25 2.25 0 015.75 4.5h1.75M3.5 15.5v1.75A2.25 2.25 0 005.75 19.5h1.75M20.5 8.5V6.75A2.25 2.25 0 0018.25 4.5h-1.75M20.5 15.5v1.75a2.25 2.25 0 01-2.25 2.25h-1.75"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.5 8.5h.01M8.5 9.75A3.25 3.25 0 1012 15.5 3.25 3.25 0 008.5 9.75z"/>
                        </svg>
                    </button>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">{{ __('site.card_verify.number_hint') }}</p>
                @error('number') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 bg-brand hover:bg-brand-light text-white font-bold px-5 py-3.5 rounded-xl text-sm shadow-[0_12px_28px_rgba(11,61,50,0.28)] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5l-8-3zM9 12l2 2 4-4"/></svg>
                {{ __('site.card_verify.submit') }}
            </button>
        </form>
    </div>

    {{-- Mobile type picker bottom sheet --}}
    <template x-teleport="body">
        <div x-show="sheetOpen" x-cloak class="fixed inset-0 z-[10070] sm:hidden" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/45" @click="sheetOpen = false"></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[min(82vh,640px)] flex flex-col rounded-t-3xl bg-white shadow-[0_-12px_40px_rgba(0,0,0,0.2)]"
                 @click.stop
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full">
                <div class="flex justify-center pt-3 pb-1 shrink-0">
                    <span class="h-1.5 w-10 rounded-full bg-gray-300"></span>
                </div>
                <div class="px-5 pb-3 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.16em] text-brand font-semibold">{{ __('site.card_verify.eyebrow') }}</p>
                        <h2 class="text-lg font-bold text-gray-900">{{ __('site.card_verify.type_label') }}</h2>
                    </div>
                    <button type="button" @click="sheetOpen = false" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <div class="overflow-y-auto px-3 py-3 space-y-1 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    @foreach ($types as $key => $meta)
                        <button type="button"
                                @click="pick(@js($key))"
                                class="w-full flex items-center justify-between gap-3 rounded-2xl px-4 py-3.5 text-left transition"
                                :class="type === @js($key) ? 'bg-brand text-white shadow-sm' : 'hover:bg-brand-muted/50 text-gray-900'">
                            <span>
                                <span class="block text-sm font-semibold">{{ __($meta['label_key']) }}</span>
                                <span class="block text-[11px] font-mono mt-0.5"
                                      :class="type === @js($key) ? 'text-brand-gold/90' : 'text-gray-500'">{{ $meta['prefix'] }}</span>
                            </span>
                            <svg x-show="type === @js($key)" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </template>

    {{-- QR scanner overlay --}}
    <template x-teleport="body">
        <div x-show="scanOpen" x-cloak class="fixed inset-0 z-[10080]" role="dialog" aria-modal="true" aria-label="{{ __('site.card_verify.scan_aria') }}">
            <div class="absolute inset-0 bg-black/70" @click="stopScan()"></div>
            <div class="absolute inset-x-4 top-[max(1.5rem,env(safe-area-inset-top))] bottom-[max(1.5rem,env(safe-area-inset-bottom))] sm:inset-auto sm:left-1/2 sm:top-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-md flex flex-col rounded-3xl bg-white shadow-2xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.16em] text-brand font-semibold">{{ __('site.card_verify.scan') }}</p>
                        <p class="text-sm text-gray-600">{{ __('site.card_verify.scan_hint') }}</p>
                    </div>
                    <button type="button" @click="stopScan()" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="{{ __('site.card_verify.stop_scan') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <div class="relative bg-black aspect-[3/4] sm:aspect-square">
                    <video x-ref="video" class="absolute inset-0 h-full w-full object-cover" playsinline muted></video>
                    <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                        <div class="w-2/3 aspect-square rounded-2xl border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
                    </div>
                </div>
                <div class="px-5 py-4 space-y-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <p class="text-xs text-rose-600" x-show="scanError" x-text="scanError"></p>
                    <label class="block">
                        <span class="sr-only">{{ __('site.card_verify.scan_photo') }}</span>
                        <input type="file" accept="image/*" capture="environment" class="block w-full text-xs text-gray-600" @change="scanFromFile($event)">
                    </label>
                    <button type="button" @click="stopScan()" class="w-full rounded-xl bg-gray-100 text-gray-800 text-sm font-semibold py-2.5">
                        {{ __('site.card_verify.stop_scan') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('cardVerifyForm', (selectedType, prefixes, labels, kinds) => ({
            type: selectedType || 'member',
            prefixes: prefixes || {},
            labels: labels || {},
            kinds: kinds || {},
            sheetOpen: false,
            scanOpen: false,
            scanError: '',
            stream: null,
            detector: null,
            raf: null,
            get prefix() { return this.prefixes[this.type] || '' },
            get typeLabel() { return this.labels[this.type] || '' },
            pick(key) { this.type = key; this.sheetOpen = false },
            clean(value) {
                return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            },
            parseScan(text) {
                const raw = String(text || '').trim();
                if (! raw) return null;
                let token = raw;
                let preferPartner = false;
                let path = raw;
                try {
                    if (/^https?:\/\//i.test(raw) || raw.startsWith('/')) {
                        path = /^https?:\/\//i.test(raw) ? (new URL(raw)).pathname : raw;
                    }
                } catch (e) {
                    path = raw;
                }
                path = decodeURIComponent(path.replace(/\/+$/, ''));
                const partner = path.match(/\/v\/p\/([^/]+)$/i) || path.match(/\/borrower\/verify\/p\/([^/]+)$/i);
                const member = path.match(/\/borrower\/verify\/member\/([^/]+)$/i) || path.match(/\/v\/([^/]+)$/i);
                if (partner) {
                    token = partner[1];
                    preferPartner = true;
                } else if (member && member[1].toLowerCase() !== 'p') {
                    token = member[1];
                }
                const clean = this.clean(token);
                if (! clean) return null;
                const entries = Object.entries(this.prefixes || {});
                const search = preferPartner
                    ? entries.filter(([key]) => (this.kinds[key] || '') === 'partner')
                    : entries;
                const matchPrefix = (list) => {
                    for (const [key, prefix] of list) {
                        const prefixClean = this.clean(prefix);
                        if (prefixClean && clean.startsWith(prefixClean)) {
                            const suffix = clean.slice(prefixClean.length);
                            if (suffix) return { type: key, number: suffix };
                        }
                    }
                    return null;
                };
                return matchPrefix(search) || matchPrefix(entries) || (! preferPartner ? { type: 'member', number: clean } : null);
            },
            applyScan(text) {
                const parsed = this.parseScan(text);
                if (! parsed) {
                    this.scanError = @js(__('site.card_verify.scan_error'));
                    return false;
                }
                this.type = parsed.type;
                if (this.$refs.suffix) {
                    this.$refs.suffix.value = parsed.number;
                }
                this.stopScan();
                this.$nextTick(() => {
                    if (this.$refs.form) this.$refs.form.submit();
                });
                return true;
            },
            async startScan() {
                this.scanError = '';
                this.scanOpen = true;
                await this.$nextTick();
                if (typeof BarcodeDetector === 'undefined') {
                    this.scanError = @js(__('site.card_verify.scan_unsupported'));
                    return;
                }
                try {
                    this.detector = new BarcodeDetector({ formats: ['qr_code'] });
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false,
                    });
                    const video = this.$refs.video;
                    video.srcObject = this.stream;
                    await video.play();
                    this.tick();
                } catch (e) {
                    this.scanError = @js(__('site.card_verify.scan_unsupported'));
                }
            },
            async tick() {
                if (! this.scanOpen || ! this.detector || ! this.$refs.video) return;
                try {
                    const codes = await this.detector.detect(this.$refs.video);
                    const value = codes?.[0]?.rawValue;
                    if (value && this.applyScan(value)) return;
                } catch (e) {}
                this.raf = window.setTimeout(() => this.tick(), 280);
            },
            async scanFromFile(event) {
                const file = event.target.files?.[0];
                event.target.value = '';
                if (! file) return;
                this.scanError = '';
                if (typeof BarcodeDetector === 'undefined') {
                    this.scanError = @js(__('site.card_verify.scan_unsupported'));
                    return;
                }
                try {
                    const detector = this.detector || new BarcodeDetector({ formats: ['qr_code'] });
                    const bitmap = await createImageBitmap(file);
                    const codes = await detector.detect(bitmap);
                    bitmap.close?.();
                    const value = codes?.[0]?.rawValue;
                    if (! value || ! this.applyScan(value)) {
                        this.scanError = @js(__('site.card_verify.scan_error'));
                    }
                } catch (e) {
                    this.scanError = @js(__('site.card_verify.scan_error'));
                }
            },
            stopScan() {
                this.scanOpen = false;
                if (this.raf) {
                    window.clearTimeout(this.raf);
                    this.raf = null;
                }
                if (this.stream) {
                    this.stream.getTracks().forEach((track) => track.stop());
                    this.stream = null;
                }
                const video = this.$refs.video;
                if (video) video.srcObject = null;
            },
        }));
            });
        </script>
    @endpush
@endonce
