@props([
    'label' => null,
    'scope' => null,
    'allowEmpty' => false,
])

@php
    $btnClass = $attributes->get('class') ?: 'bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm';
@endphp

<button
    type="submit"
    x-data="kfGatedSubmit({ scope: @js($scope), allowEmpty: @js((bool) $allowEmpty) })"
    x-show="ready"
    x-cloak
    {{ $attributes->except('class')->merge(['class' => $btnClass]) }}
>
    {{ $slot->isNotEmpty() ? $slot : ($label ?? __('borrower.profile.save')) }}
</button>
