@props(['value', 'label' => null, 'group' => null, 'map' => [], 'default' => 'bg-gray-100 text-gray-700'])
@php
    $class = $map[$value] ?? $default;
    $text = $label ?? display_label($value, $group);
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $class }}">
    {{ $text !== '' ? $text : 'Unknown' }}
</span>
