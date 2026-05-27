@props(['sort', 'direction', 'col', 'label'])
<th class="px-5 py-2.5 cursor-pointer select-none" wire:click="sortBy('{{ $col }}')">
    <span class="inline-flex items-center gap-1">
        {{ $label }}
        @if ($sort === $col)
            <span class="text-amber-600">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
        @endif
    </span>
</th>
