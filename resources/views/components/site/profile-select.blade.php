@props([
    'name',
    'label' => '',
    'options' => [],
    'value' => '',
    'required' => false,
    'placeholder' => '',
    'selectClass' => 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition',
])

@php
    $selected = old($name, $value);
    $optionsList = is_array($options) ? $options : [];
    $hasError = $errors->has($name);
@endphp

{{-- Shared Profile selector: mobile bottom-sheet + desktop teleported panel (escapes card overflow). --}}
<div class="w-full min-w-0" x-data="{
    pickerOpen: false,
    selected: @js($selected),
    options: @js($optionsList),
    placeholder: @js($placeholder),
    desktopStyle: '',
    labelFor(val) {
        if (!val) return this.placeholder;
        return this.options[val] || val;
    },
    isNarrow() {
        return typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches;
    },
    openPicker() {
        if (this.isNarrow()) {
            this.pickerOpen = true;
            return;
        }
        this.pickerOpen = ! this.pickerOpen;
        if (this.pickerOpen) {
            this.positionDesktop();
        }
    },
    positionDesktop() {
        this.$nextTick(() => {
            const btn = this.$refs.triggerBtn;
            if (! btn) return;
            const r = btn.getBoundingClientRect();
            const panelW = Math.max(r.width, 220);
            const maxH = 224;
            let left = Math.max(12, Math.min(r.left, window.innerWidth - panelW - 12));
            let top = r.bottom + 6;
            if (top + maxH > window.innerHeight - 12) {
                top = Math.max(12, r.top - maxH - 6);
            }
            this.desktopStyle = `left:${left}px;top:${top}px;width:${panelW}px;`;
        });
    },
    pick(val) {
        this.selected = String(val ?? '');
        this.pickerOpen = false;
        this.$nextTick(() => {
            const input = this.$refs.hiddenInput || this.$el.querySelector('input[type=hidden]');
            if (input) {
                input.value = this.selected;
                input.setAttribute('value', this.selected);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            // Single path: document profile-select listener coalesces kfAutosave flush.
            this.$dispatch('profile-select', { name: @js($name), value: this.selected });
        });
    }
}"
@resize.window="if (pickerOpen && ! isNarrow()) positionDesktop()">
    @if ($label)
        <label class="block text-sm font-medium text-gray-700 mb-1.5" for="profile-select-{{ $name }}">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    {{-- Submitted value (works on both mobile bottom-sheet and desktop dropdown). --}}
    <input type="hidden" id="profile-select-{{ $name }}" name="{{ $name }}" x-ref="hiddenInput" :value="selected" @if ($required) required @endif>

    <div class="lg:hidden">
        <button type="button" @click="pickerOpen = true"
                class="w-full inline-flex items-center gap-3 rounded-xl border bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition {{ $hasError ? 'border-rose-400' : 'border-gray-200' }}">
            <span class="flex-1 text-left truncate" :class="!selected ? 'text-gray-400' : ''" x-text="labelFor(selected)"></span>
            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>

        <x-site.bottom-sheet :title="$label ?: $placeholder" open="pickerOpen">
            <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                @if (! $required)
                    <button type="button" @click="pick('')"
                            class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-500 hover:bg-gray-50"
                            :class="!selected ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                        {{ $placeholder }}
                    </button>
                @endif
                @foreach ($optionsList as $key => $optionLabel)
                    <button type="button" @click="pick(@js((string) $key))"
                            class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                            :class="selected === @js((string) $key) ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                        {{ $optionLabel }}
                    </button>
                @endforeach
            </div>
        </x-site.bottom-sheet>
    </div>

    {{-- Desktop: teleported panel so glass-card overflow-hidden cannot clip options (income, marital, kin, etc.). --}}
    <div class="hidden lg:block relative">
        <button type="button" x-ref="triggerBtn" @click="openPicker()"
                class="w-full inline-flex items-center gap-3 {{ $selectClass }} {{ $hasError ? 'border-rose-400' : '' }}">
            <span class="flex-1 text-left truncate" :class="!selected ? 'text-gray-400' : ''" x-text="labelFor(selected)"></span>
            <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="pickerOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>
    </div>

    <template x-teleport="body">
        <div x-cloak x-show="pickerOpen && ! isNarrow()" x-transition
             @click.outside="pickerOpen = false"
             @keydown.escape.window="pickerOpen = false"
             class="fixed z-[10200] rounded-xl border border-gray-200 bg-white shadow-xl py-1 max-h-56 overflow-y-auto"
             :style="desktopStyle">
            @if (! $required)
                <button type="button" @click="pick('')"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm hover:bg-brand-muted transition text-gray-500"
                        :class="!selected ? 'bg-brand-muted/60 text-brand font-semibold' : ''">
                    {{ $placeholder }}
                </button>
            @endif
            @foreach ($optionsList as $key => $optionLabel)
                <button type="button" @click="pick(@js((string) $key))"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm hover:bg-brand-muted transition"
                        :class="selected === @js((string) $key) ? 'bg-brand-muted/60 text-brand font-semibold' : 'text-gray-700'">
                    {{ $optionLabel }}
                </button>
            @endforeach
        </div>
    </template>

    @error($name)
        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
