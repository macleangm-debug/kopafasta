@props(['sort', 'direction', 'col', 'label'])
<th class="px-5 py-3 cursor-pointer select-none text-white/90 hover:text-brand-gold transition" wire:click="sortBy('{{ $col }}')">
    <span class="inline-flex items-center gap-1.5 font-semibold">
        {{ $label }}
        @if ($sort === $col)
            <span class="text-brand-gold">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
        @endif
    </span>
</th>
