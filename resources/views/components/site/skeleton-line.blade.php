@props(['width' => 'w-full', 'height' => 'h-3'])

<div {{ $attributes->merge(['class' => "animate-pulse rounded-md bg-gray-200/80 {$width} {$height}"]) }}></div>
