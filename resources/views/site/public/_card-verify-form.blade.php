@php
    /** @var array<string, array{label_key: string, prefix: string, kind: string, category?: string}> $types */
    $selectedType = $selectedType ?? 'member';
    $number = $number ?? '';
    $action = $action ?? route('site.card.verify.lookup');
    $prefixes = collect($types)->mapWithKeys(fn ($meta, $key) => [$key => $meta['prefix']])->all();
    $typeLabels = collect($types)->mapWithKeys(fn ($meta, $key) => [$key => __($meta['label_key'])])->all();
@endphp

<div class="relative overflow-hidden rounded-[1.5rem] bg-white/95 backdrop-blur shadow-[0_24px_60px_rgba(8,47,39,0.12)] ring-1 ring-brand/10 p-5 sm:p-7"
     x-data="{
        type: @js($selectedType),
        prefixes: @js($prefixes),
        labels: @js($typeLabels),
        sheetOpen: false,
        get prefix() { return this.prefixes[this.type] || '' },
        get typeLabel() { return this.labels[this.type] || '' },
        pick(key) { this.type = key; this.sheetOpen = false }
     }">
    <div class="absolute -right-16 -top-20 h-48 w-48 rounded-full bg-brand-gold/15 pointer-events-none"></div>
    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand via-brand-gold to-brand pointer-events-none"></div>

    <div class="relative">
        <p class="text-[11px] uppercase tracking-[0.18em] text-brand font-semibold">{{ __('site.card_verify.eyebrow') }}</p>
        <h1 class="mt-1.5 text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">{{ __('site.card_verify.heading') }}</h1>
        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ __('site.card_verify.subtitle') }}</p>

        <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
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
                           value="{{ $number }}"
                           required
                           maxlength="24"
                           autocomplete="off"
                           spellcheck="false"
                           inputmode="text"
                           placeholder="{{ __('site.card_verify.number_placeholder') }}"
                           class="flex-1 border-0 px-3 py-3 text-sm font-mono tracking-wider uppercase focus:ring-0 bg-transparent">
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
</div>
