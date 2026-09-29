@props([
    'label' => '',
    'options' => [],
    'value' => '',
    'name' => null,
    'model' => null,
    'setter' => null,
    'required' => false,
    'placeholder' => '',
    'selectClass' => 'w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand',
    'onPick' => null,
    'otherName' => null,
    'otherLabel' => null,
    'otherValue' => 'other',
    // When true (inside an action-panel sheet), mobile uses an in-place list — never a second bottom sheet.
    'inline' => false,
])

@php
    $optionsList = is_array($options) ? $options : [];
    $selected = old($name ?? '', $value);
    $modelExpr = $model;
    $setterExpr = $setter;
    $optionEntries = collect($optionsList)
        ->map(fn ($optionLabel, $key) => ['value' => (string) $key, 'label' => (string) $optionLabel])
        ->values()
        ->all();
    $hasOther = array_key_exists((string) $otherValue, $optionsList);
    $otherField = $otherName ?: 'category_other';
    $otherFieldLabel = $otherLabel ?: __('plus.money.other_name');
@endphp

<div x-data="{
        pickerOpen: false,
        desktopOpen: false,
        optionEntries: @js($optionEntries),
        placeholder: @js($placeholder),
        selected: @js((string) $selected),
        otherValue: @js((string) $otherValue),
        otherText: @js((string) old($otherField, '')),
        labelFor(val) {
            if (!val) return this.placeholder;
            if (val === this.otherValue && (this.otherText || '').trim() !== '') {
                return this.otherText.trim();
            }
            const hit = this.optionEntries.find((o) => o.value === val);
            return hit ? hit.label : val;
        },
        currentValue() {
            return this.selected || '';
        },
        syncNative() {
            const sel = this.$refs.native;
            if (sel) {
                sel.value = this.selected || '';
                // Notify parent form completeness (@input/@change) — Alpine x-model alone does not bubble.
                sel.dispatchEvent(new Event('input', { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
        notifyOther() {
            const el = this.$refs.otherHidden;
            if (! el) return;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
        cancelOther() {
            this.selected = '';
            this.otherText = '';
            this.$nextTick(() => {
                this.syncNative();
                this.notifyOther();
            });
        },
        confirmOther() {
            if ((this.otherText || '').trim() === '') return;
            this.pickerOpen = false;
            this.desktopOpen = false;
            this.$nextTick(() => {
                this.syncNative();
                this.notifyOther();
            });
        },
        choose(val) {
            this.selected = val == null ? '' : String(val);
            if (this.selected !== this.otherValue) {
                this.otherText = '';
                this.pickerOpen = false;
                this.desktopOpen = false;
            }
            this.$nextTick(() => {
                this.syncNative();
                this.notifyOther();
            });
            @if ($setterExpr)
                if (typeof {{ $setterExpr }} === 'function') { {{ $setterExpr }}(this.selected); }
            @elseif ($modelExpr)
                try { {{ $modelExpr }} = this.selected; } catch (e) {}
            @endif
            @if ($onPick)
                {{ $onPick }};
            @endif
        }
     }"
     x-init="
        $nextTick(() => syncNative());
        $watch('otherText', () => $nextTick(() => notifyOther()));
        @if ($modelExpr)
            $watch('selected', (val) => { try { {{ $modelExpr }} = val; } catch (e) {} });
        @endif
     "
     {{ $attributes->only('class') }}>
    @if ($label)
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    <div class="lg:hidden">
        @if ($inline)
            <button type="button" @click="pickerOpen = !pickerOpen"
                    class="w-full h-12 inline-flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                <span class="flex-1 text-left truncate" x-text="labelFor(currentValue())"></span>
                <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="pickerOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
            <div x-cloak x-show="pickerOpen" class="mt-2 rounded-xl border border-gray-200 bg-white shadow-sm py-1 max-h-56 overflow-y-auto overscroll-contain">
                @if (! $required)
                    <button type="button" @click="choose('')"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-500 hover:bg-gray-50"
                            :class="!currentValue() ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                        {{ $placeholder }}
                    </button>
                @endif
                <template x-for="opt in optionEntries" :key="'i-'+opt.value">
                    <button type="button" @click="choose(opt.value)"
                            class="w-full text-left px-4 py-3 text-sm font-medium text-gray-800 hover:bg-gray-50"
                            :class="currentValue() === opt.value ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                            x-text="opt.label"></button>
                </template>
            </div>
            @if ($hasOther)
                <div class="mt-3" x-show="selected === otherValue" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        {{ $otherFieldLabel }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           x-model="otherText"
                           maxlength="80"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand"
                           :required="selected === otherValue">
                </div>
            @endif
        @else
            <button type="button" @click="pickerOpen = true"
                    class="w-full h-12 inline-flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                <span class="flex-1 text-left truncate" x-text="labelFor(currentValue())"></span>
                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>

            <x-site.bottom-sheet :title="$label ?: $placeholder" open="pickerOpen" layer="z-[10100]">
                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                    {{-- Standard options: hide entirely once Other is chosen (same-surface replacement). --}}
                    <div x-show="selected !== otherValue" x-cloak>
                        @if (! $required)
                            <button type="button" @click="choose('')"
                                    class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-500 hover:bg-gray-50"
                                    :class="!currentValue() ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                                {{ $placeholder }}
                            </button>
                        @endif
                        <template x-for="opt in optionEntries" :key="opt.value">
                            <button type="button" @click="choose(opt.value)"
                                    class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                    :class="currentValue() === opt.value ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                                    x-text="opt.label"></button>
                        </template>
                    </div>
                    @if ($hasOther)
                        <div class="space-y-3" x-show="selected === otherValue" x-cloak>
                            <label class="block text-sm font-semibold text-gray-800">
                                {{ $otherFieldLabel }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   x-model="otherText"
                                   maxlength="80"
                                   x-ref="otherInput"
                                   x-init="$watch('selected', (v) => { if (v === otherValue) $nextTick(() => $refs.otherInput?.focus()); })"
                                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand"
                                   placeholder="{{ $otherFieldLabel }}">
                            <div class="flex items-center justify-between gap-3 pt-1">
                                <button type="button" @click="cancelOther()"
                                        class="text-sm font-semibold text-gray-600 px-3 py-2.5">
                                    {{ __('borrower.apply.cancel') }}
                                </button>
                                <button type="button" @click="confirmOther()"
                                        class="rounded-xl bg-brand text-white text-sm font-semibold px-5 py-2.5"
                                        :disabled="!(otherText || '').trim()"
                                        :class="!(otherText || '').trim() ? 'opacity-40 pointer-events-none' : ''">
                                    {{ __('borrower.apply.continue') }}
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </x-site.bottom-sheet>
        @endif
    </div>

    <div class="hidden lg:block relative" @keydown.escape.window="if (selected !== otherValue) desktopOpen = false">
        <button type="button" @click.stop="desktopOpen = !desktopOpen"
                class="w-full h-12 inline-flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
            <span class="flex-1 text-left truncate" x-text="labelFor(currentValue())"></span>
            <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="desktopOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>
        <div x-cloak x-show="desktopOpen"
             @click.outside="if (selected !== otherValue) desktopOpen = false"
             class="absolute z-30 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-xl py-1 max-h-72 overflow-y-auto">
            {{-- Same-surface Other: hide option list once Other is chosen. --}}
            <div x-show="selected !== otherValue" x-cloak>
                @if (! $required)
                    <button type="button" @click="choose('')"
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 hover:bg-brand-muted"
                            :class="!currentValue() ? 'bg-brand-muted text-brand font-semibold' : ''">{{ $placeholder }}</button>
                @endif
                <template x-for="opt in optionEntries" :key="'d-'+opt.value">
                    <button type="button" @click="choose(opt.value)"
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-800 hover:bg-brand-muted"
                            :class="currentValue() === opt.value ? 'bg-brand-muted text-brand font-semibold' : ''"
                            x-text="opt.label"></button>
                </template>
            </div>
            @if ($hasOther)
                <div class="space-y-3 p-3" x-show="selected === otherValue" x-cloak>
                    <label class="block text-sm font-semibold text-gray-800">
                        {{ $otherFieldLabel }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           x-model="otherText"
                           maxlength="80"
                           x-ref="otherInputDesktop"
                           x-init="$watch('selected', (v) => { if (v === otherValue && desktopOpen) $nextTick(() => $refs.otherInputDesktop?.focus()); })"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand"
                           placeholder="{{ $otherFieldLabel }}">
                    <div class="flex items-center justify-between gap-3 pt-1">
                        <button type="button" @click="cancelOther()"
                                class="text-sm font-semibold text-gray-600 px-3 py-2.5">
                            {{ __('borrower.apply.cancel') }}
                        </button>
                        <button type="button" @click="confirmOther()"
                                class="rounded-xl bg-brand text-white text-sm font-semibold px-5 py-2.5"
                                :disabled="!(otherText || '').trim()"
                                :class="!(otherText || '').trim() ? 'opacity-40 pointer-events-none' : ''">
                            {{ __('borrower.apply.continue') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <select
        x-ref="native"
        @if ($name) name="{{ $name }}" @endif
        x-model="selected"
        @if ($required) required @endif
        tabindex="-1"
        aria-hidden="true"
        class="sr-only"
    >
        @if (! $required)
            <option value="">{{ $placeholder }}</option>
        @elseif ($placeholder)
            <option value="" disabled hidden>{{ $placeholder }}</option>
        @endif
        @foreach ($optionEntries as $opt)
            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
        @endforeach
    </select>

    @if ($hasOther)
        <input type="hidden" x-ref="otherHidden" name="{{ $otherField }}" x-model="otherText" :disabled="selected !== otherValue">
    @endif
</div>
