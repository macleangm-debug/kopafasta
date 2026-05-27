@props(['value', 'map' => [], 'default' => 'bg-gray-100 text-gray-700'])
@php
    $class = $map[$value] ?? $default;
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $class }}">
    {{ str_replace('_', ' ', $value ?? 'unknown') }}
</span>
