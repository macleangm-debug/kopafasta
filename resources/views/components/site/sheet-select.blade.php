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
        optionEntries: @js($optionEntries),
        placeholder: @js($placeholder),
        selected: @js((string) $selected),
        otherValue: @js((string) $otherValue),
        labelFor(val) {
            if (!val) return this.placeholder;
            const hit = this.optionEntries.find((o) => o.value === val);
            return hit ? hit.label : val;
        },
        currentValue() {
            return this.selected || '';
        },
        syncNative() {
            const sel = this.$refs.native;
            if (sel) sel.value = this.selected || '';
        },
        choose(val) {
            this.selected = val == null ? '' : String(val);
            this.pickerOpen = false;
            this.$nextTick(() => this.syncNative());
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
        @if ($modelExpr)
            $watch('selected', (val) => { try { {{ $modelExpr }} = val; } catch (e) {} });
        @endif
     "
     {{ $attributes->only('class') }}>
    @if ($label)
        <label class="block text-sm font-semibold text-gray-700 mb-2">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    <div class="lg:hidden">
        <button type="button" @click="pickerOpen = true"
                class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
            <span class="flex-1 text-left truncate" x-text="labelFor(currentValue())"></span>
            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>

        <x-site.bottom-sheet :title="$label ?: $placeholder" open="pickerOpen" layer="z-[10100]">
            <div class="space-y-1 max-h-[60vh] overflow-y-auto">
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
        </x-site.bottom-sheet>
    </div>

    <select
        x-ref="native"
        @if ($name) name="{{ $name }}" @endif
        x-model="selected"
        @change="choose($event.target.value)"
        @if ($required) required @endif
        {{ $attributes->except('class')->merge(['class' => $selectClass.' max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden']) }}
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
        <div class="mt-3" x-show="selected === otherValue" x-cloak>
            <label class="block text-xs font-medium text-gray-600 mb-1">
                {{ $otherFieldLabel }} <span class="text-red-500">*</span>
            </label>
            <input type="text"
                   name="{{ $otherField }}"
                   maxlength="80"
                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand"
                   :required="selected === otherValue"
                   :disabled="selected !== otherValue">
        </div>
    @endif
</div>
