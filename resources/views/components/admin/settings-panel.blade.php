@props(['id'])

<div x-show="tab === @js($id)" x-cloak {{ $attributes->merge(['class' => 'space-y-6']) }}>
    {{ $slot }}
</div>
