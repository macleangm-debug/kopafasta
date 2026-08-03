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
])

@php
    $optionsList = is_array($options) ? $options : [];
    $selected = old($name ?? '', $value);
    $modelExpr = $model;
    $setterExpr = $setter; // e.g. setLoanPurpose — parent Alpine method (preferred)
    $optionEntries = collect($optionsList)
        ->map(fn ($optionLabel, $key) => ['value' => (string) $key, 'label' => (string) $optionLabel])
        ->values()
        ->all();
@endphp

<div x-data="{
        pickerOpen: false,
        optionEntries: @js($optionEntries),
        placeholder: @js($placeholder),
        @unless ($modelExpr || $setterExpr)
            selected: @js($selected),
        @endunless
        labelFor(val) {
            if (!val) return this.placeholder;
            const hit = this.optionEntries.find((o) => o.value === val);
            return hit ? hit.label : val;
        },
        currentValue() {
            @if ($modelExpr)
                return {{ $modelExpr }};
            @else
                return this.selected;
            @endif
        },
        choose(val) {
            @if ($setterExpr)
                {{ $setterExpr }}(val);
            @elseif ($modelExpr)
                {{ $modelExpr }} = val;
            @else
                this.selected = val;
            @endif
            this.pickerOpen = false;
            @if ($onPick)
                {{ $onPick }};
            @endif
        }
     }" {{ $attributes->only('class') }}>
    @if ($label)
        <label class="block text-sm font-semibold text-gray-700 mb-2">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    <div class="lg:hidden">
        <button type="button" @click="pickerOpen = true"
                class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
            <span class="flex-1 text-left truncate" x-text="labelFor(currentValue())"></span>
            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>

        <x-site.bottom-sheet :title="$label ?: $placeholder" open="pickerOpen">
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
        @if ($name) name="{{ $name }}" @endif
        @if ($setterExpr)
            :value="currentValue()"
            @change="choose($event.target.value)"
        @elseif ($modelExpr)
            x-model="{{ $modelExpr }}"
        @else
            x-model="selected"
            @change="choose(selected)"
        @endif
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
</div>
