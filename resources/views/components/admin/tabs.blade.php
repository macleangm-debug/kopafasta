{{-- Horizontal tab bar. Pass items=[ ['label' => 'All', 'route' => 'admin.vendors.index'], ... ] --}}
@props(['items' => [], 'current' => null])

@php
    $current = $current ?? request()->route()?->getName();
@endphp

<div class="border-b border-gray-200 mb-5 -mt-2">
    <nav class="flex gap-1 overflow-x-auto" aria-label="Tabs">
        @foreach ($items as $tab)
            @php
                $isActive = $tab['route'] === $current;
            @endphp
            <a href="{{ route($tab['route']) }}"
               class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                      {{ $isActive
                           ? 'border-amber-500 text-amber-700'
                           : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' }}">
                {{ $tab['label'] }}
                @if (isset($tab['count']))
                    <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                 {{ $isActive ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $tab['count'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
