@props([
    'id' => null,
    'title',
    'subtitle' => null,
])

<section @if ($id) id="{{ $id }}" @endif class="scroll-mt-24 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-sm font-semibold text-gray-900">{{ $title }}</h2>
            @if ($subtitle)
                <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
    <div class="p-6">
        {{ $slot }}
    </div>
</section>
