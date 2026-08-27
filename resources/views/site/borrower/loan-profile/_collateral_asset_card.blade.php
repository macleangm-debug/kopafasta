@props([
    'selected' => null,
    'typeIcons' => [],
    'showInsured' => false,
    'sourceLabel' => null,
])

<x-site.collateral-card
    :selected="$selected"
    :type-icons="$typeIcons"
    :show-insured="$showInsured"
    :source-label="$sourceLabel"
>
    {{ $slot }}
</x-site.collateral-card>
