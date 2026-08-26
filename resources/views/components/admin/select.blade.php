@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'help' => null,
])

@php
    $current = old($name, $value);
    $optionItems = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $optionsAreList = array_is_list($optionItems);
    $optionEntries = [];
    foreach ($optionItems as $key => $optionLabel) {
        if (is_array($optionLabel)) {
            $optionLabel = $optionLabel['label'] ?? $key;
        }
        $optValue = $optionsAreList ? $optionLabel : $key;
        $optionEntries[] = ['value' => (string) $optValue, 'label' => (string) $optionLabel];
    }
    $selectId = $attributes->get('id') ?: $name;
@endphp

<div @error($name) data-has-error="true" @enderror>
    @if ($label)
        <label for="{{ $selectId }}" class="block text-xs font-semibold text-gray-700 mb-1">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <div class="relative hidden md:block">
        <select
            id="{{ $selectId }}"
            name="{{ $name }}"
            @if ($required) required @endif
            {{ $attributes->merge(['class' => 'appearance-none w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm pl-3.5 pr-9 py-2.5 font-medium text-gray-700 cursor-pointer hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition']) }}
        >
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($optionEntries as $entry)
                <option value="{{ $entry['value'] }}" @selected((string) $current === (string) $entry['value'])>{{ $entry['label'] }}</option>
            @endforeach
        </select>
        <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
    <div class="md:hidden" x-data="{
            pickerOpen: false,
            options: @js($optionEntries),
            placeholder: @js((string) ($placeholder ?? 'Choose…')),
            current: @js((string) ($current ?? '')),
            selectId: @js($selectId),
            labelFor(val) {
                if (val === '' || val === null) return this.placeholder;
                const hit = this.options.find((o) => o.value === String(val));
                return hit ? hit.label : val;
            },
            choose(val) {
                this.current = val == null ? '' : String(val);
                this.pickerOpen = false;
                const sel = document.getElementById(this.selectId);
                if (! sel) return;
                sel.value = this.current;
                sel.dispatchEvent(new Event('input', { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            },
         }">
        <button type="button" class="w-full inline-flex items-center justify-between rounded-xl border border-brand/15 bg-white px-3.5 py-2.5 text-sm font-medium text-gray-700"
                @click="pickerOpen = true">
            <span class="truncate" x-text="labelFor(current)"></span>
            <svg class="size-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <x-site.bottom-sheet :title="$label ?: 'Choose'" open="pickerOpen">
            <div class="space-y-1">
                @if ($placeholder !== null)
                    <button type="button" class="w-full text-left rounded-xl px-3 py-3 text-sm hover:bg-brand-muted/40" @click="choose('')">{{ $placeholder }}</button>
                @endif
                @foreach ($optionEntries as $entry)
                    <button type="button" class="w-full text-left rounded-xl px-3 py-3 text-sm hover:bg-brand-muted/40"
                            @click="choose(@js($entry['value']))">{{ $entry['label'] }}</button>
                @endforeach
            </div>
        </x-site.bottom-sheet>
    </div>
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
