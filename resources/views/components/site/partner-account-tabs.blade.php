@props([
    'active' => 'profile',
    'tabs' => [],
])

@php
    /** @var list<array{key: string, label: string, url: string}> $tabs */
@endphp

@if ($tabs !== [])
    <nav class="mb-6" aria-label="Account">
        <div class="inline-flex flex-wrap rounded-xl ring-1 ring-gray-200/80 bg-white/80 backdrop-blur p-0.5 text-sm gap-0.5">
            @foreach ($tabs as $tab)
                <a href="{{ $tab['url'] }}"
                   data-kf-motion="tab"
                   @class([
                       'px-4 py-2 rounded-lg font-semibold transition',
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
