{{--
    Usage:
    <x-admin.table-shell
        :records="$rows"
        :statuses="$statuses ?? []"
        searchPlaceholder="Search…"
        statusLabel="All statuses"
        statusKey="status"
    >
        <x-slot:headers>...</x-slot:headers>
        <x-slot:rows>...</x-slot:rows>
    </x-admin.table-shell>
--}}
@props([
    'records',
    'statuses' => [],
    'searchPlaceholder' => 'Search…',
    'statusLabel' => 'All statuses',
    'statusKey' => 'status',
    'statusLabels' => [],
    'statusGroup' => null,
    'headers' => null,
    'rows' => null,
])

<div class="space-y-4">
    {{-- Filter card --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 px-5 py-3 flex flex-col md:flex-row md:items-center gap-3">
        <div class="flex-1 relative">
            <svg class="size-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ $searchPlaceholder }}"
                   class="w-full pl-9 pr-3 py-2 text-sm bg-white border border-gray-300 rounded-lg shadow-sm
                          placeholder:text-gray-400
                          focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20
                          transition">
        </div>

        @if (count($statuses) > 0)
            <div class="relative">
                <select wire:model.live="{{ $statusKey }}"
                        class="appearance-none text-sm bg-white border border-gray-300 rounded-lg shadow-sm
                               pl-3.5 pr-9 py-2 font-medium text-gray-700 cursor-pointer
                               hover:border-gray-400
                               focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20
                               transition">
                    <option value="">{{ $statusLabel }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}">{{ $statusLabels[$s] ?? display_label($s, $statusGroup) }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 size-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        @endif

        <div class="text-xs text-gray-500 md:ml-auto">
            {{ $records->total() }} record{{ $records->total() === 1 ? '' : 's' }}
        </div>
    </div>

    {{-- Table card --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                    <tr>{{ $headers }}</tr>
                </thead>
                <tbody class="divide-y divide-gray-100">{{ $rows }}</tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-gray-200">
            {{ $records->links() }}
        </div>
    </div>
</div>
