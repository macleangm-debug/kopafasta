@props([
    'name',
    'id' => null,
    'label' => null,
    'required' => false,
    'value' => null,
])

@php
    $inputId = $id ?: $name;
@endphp

<div class="rounded-2xl bg-white ring-2 ring-gray-200 px-4 py-4 focus-within:ring-brand">
    @if ($label)
        <p class="text-xs font-medium text-gray-600">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</p>
    @endif
    <div class="mt-2 flex items-center gap-3">
        <span class="text-lg font-bold text-brand shrink-0">{{ currency_code() }}</span>
        <x-site.numeric-input
            :name="$name"
            :id="$inputId"
            :money="true"
            :required="$required"
            :value="$value"
            class="flex-1 rounded-xl border border-gray-300 bg-white ring-1 ring-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/20 px-3 py-2.5 text-2xl sm:text-3xl font-bold text-gray-900 tabular-nums"
        />
    </div>
</div>
