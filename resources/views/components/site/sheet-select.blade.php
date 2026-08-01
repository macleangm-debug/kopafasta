@props([
    'label' => '',
    'options' => [],
    'value' => '',
    'name' => null,
    'model' => null,
    'required' => false,
    'placeholder' => '',
    'selectClass' => 'w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand',
    'onPick' => null,
])

@php
    $optionsList = is_array($options) ? $options : [];
    $selected = old($name ?? '', $value);
    $modelExpr = $model; // e.g. form.purpose or group.purpose
@endphp

<div x-data="{
        pickerOpen: false,
        options: @js($optionsList),
        placeholder: @js($placeholder),
        @if ($modelExpr)
            get selected() { return {{ $modelExpr }}; },
            set selected(val) { {{ $modelExpr }} = val; },
        @else
            selected: @js($selected),
        @endif
        labelFor(val) {
            if (!val) return this.placeholder;
            return this.options[val] || val;
        },
        pick(val) {
            this.selected = val;
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
            <span class="flex-1 text-left truncate" x-text="labelFor(selected)"></span>
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
                <template x-for="(optLabel, key) in options" :key="key">
                    <button type="button" @click="pick(key)"
                            class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                            :class="selected === key ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                            x-text="optLabel"></button>
                </template>
            </div>
        </x-site.bottom-sheet>
    </div>

    <select
        @if ($name) name="{{ $name }}" @endif
        x-model="selected"
        @if ($required) required @endif
        {{ $attributes->except('class')->merge(['class' => $selectClass.' max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden']) }}
    >
        @if (! $required || $placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($optionsList as $key => $optionLabel)
            <option value="{{ $key }}" @selected((string) $selected === (string) $key)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
