@props([
    'value' => null,
    'emptyLabel' => null,
])

@php
    $emptyLabel = $emptyLabel ?? __('borrower.profile.add_details');
@endphp

@if (filled($value))
    <dd {{ $attributes->merge(['class' => 'font-medium mt-0.5']) }}>{{ $value }}</dd>
@else
    <dd {{ $attributes->merge(['class' => 'mt-0.5']) }}>
        <button type="button" @click="open = true"
                class="text-sm font-semibold text-amber-700 hover:text-amber-800">
            {{ $emptyLabel }}
        </button>
    </dd>
@endif
