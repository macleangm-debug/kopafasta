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
        <label class="block text-sm font-medium text-gray-700 mb-1.5" for="profile-select-{{ $name }}">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    {{-- Submitted value (works on both mobile bottom-sheet and desktop dropdown). --}}
    <input type="hidden" id="profile-select-{{ $name }}" name="{{ $name }}" :value="selected" @if ($required) required @endif>

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

    {{-- Desktop: premium dropdown panel (not native select). --}}
    <div class="hidden lg:block relative" @click.outside="pickerOpen = false">
        <button type="button" @click="pickerOpen = !pickerOpen"
                class="w-full inline-flex items-center gap-3 {{ $selectClass }} {{ $hasError ? 'border-rose-400' : '' }}">
            <span class="flex-1 text-left truncate" :class="!selected ? 'text-gray-400' : ''" x-text="labelFor(selected)"></span>
            <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="pickerOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>
        <div x-cloak x-show="pickerOpen" x-transition
             class="absolute left-0 right-0 top-full mt-1 z-20 rounded-xl border border-gray-200 bg-white shadow-xl py-1 max-h-56 overflow-y-auto">
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
    </div>

    @error($name)
        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
