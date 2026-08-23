@props([
    'active' => 'profile',
    'tabs' => [],
])

@php
    /** @var list<array{key: string, label: string, url: string}> $tabs */
@endphp

@if ($tabs !== [])
    <nav class="mb-6" aria-label="Account">
        <div @class([
                 'grid gap-1 rounded-2xl ring-1 ring-gray-200 bg-white p-1 text-sm',
                 'grid-cols-2' => count($tabs) <= 2,
                 'grid-cols-3' => count($tabs) === 3,
                 'grid-cols-4' => count($tabs) >= 4,
             ])>
            @foreach ($tabs as $tab)
                <a href="{{ $tab['url'] }}"
                   data-kf-motion="tab"
                   @class([
                       'px-3 py-2.5 rounded-xl font-semibold text-center transition',
                       $active === ($tab['key'] ?? '')
                           ? 'bg-brand text-white shadow-sm'
                           : 'text-gray-600 hover:bg-brand-muted/50',
                   ])>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
@endif
