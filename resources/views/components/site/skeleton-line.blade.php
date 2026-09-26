@props(['width' => 'w-full', 'height' => 'h-3'])

<div {{ $attributes->merge(['class' => "kf-skeleton {$width} {$height}"]) }}></div>
