@props([
    'name',
    'label' => '',
    'options' => [],
    'value' => '',
    'required' => false,
    'placeholder' => '',
    'selectClass' => 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm',
])

@php
    $selected = old($name, $value);
    $optionsList = is_array($options) ? $options : [];
@endphp

<div class="w-full min-w-0" x-data="{
    pickerOpen: false,
    selected: @js($selected),
    options: @js($optionsList),
    placeholder: @js($placeholder),
    labelFor(val) {
        if (!val) return this.placeholder;
        return this.options[val] || val;
    },
    pick(val) {
        this.selected = val;
        this.pickerOpen = false;
    }
}">
    @if ($label)
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
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
                @foreach ($optionsList as $key => $optionLabel)
                    <button type="button" @click="pick(@js($key))"
                            class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                            :class="selected === @js($key) ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                        {{ $optionLabel }}
                    </button>
                @endforeach
            </div>
        </x-site.bottom-sheet>
    </div>

    <select name="{{ $name }}" x-model="selected" @if($required) required @endif
            class="w-full {{ $selectClass }} max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden">
        @if (! $required)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($optionsList as $key => $optionLabel)
            <option value="{{ $key }}" @selected($selected === (string) $key)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
