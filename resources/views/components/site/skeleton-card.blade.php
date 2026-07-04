@props(['lines' => 3])

<div {{ $attributes->merge(['class' => 'glass-card p-5 space-y-3']) }}>
    <x-site.skeleton-line width="w-1/3" height="h-4" />
    @for ($i = 0; $i < $lines; $i++)
        <x-site.skeleton-line :width="$i === $lines - 1 ? 'w-2/3' : 'w-full'" />
    @endfor
</div>
