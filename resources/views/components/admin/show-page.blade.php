@props([
    'title',
    'heading',
    'subheading' => null,
    'backUrl',
    'backLabel' => 'Back',
    'editUrl' => null,
    'fields' => [],          // ['Label' => 'value', ...]  or  [['label'=>..., 'value'=>..., 'wide'=>bool], ...]
])

<x-admin.layout
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :backUrl="$backUrl"
    :backLabel="$backLabel">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($editUrl)
        <div class="flex justify-end mb-4">
            <a href="{{ $editUrl }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg shadow-sm transition">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
        </div>
    @endif

    @if (! empty($fields))
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                @foreach ($fields as $key => $row)
                    @php
                        $label = is_array($row) ? ($row['label'] ?? $key) : $key;
                        $value = is_array($row) ? ($row['value'] ?? null) : $row;
                        $wide  = is_array($row) ? ($row['wide']  ?? false) : false;
                    @endphp
                    <div @class(['sm:col-span-2' => $wide])>
                        <dt class="text-xs text-gray-500">{{ $label }}</dt>
                        <dd class="text-gray-900 mt-0.5 tabular-nums">
                            @if ($value !== null && $value !== '')
                                @if (is_array($row) && ($row['money'] ?? false))
                                    {{ format_money($value, true, (int) ($row['decimals'] ?? 0)) }}
                                @elseif (is_array($row) && ($row['numeric'] ?? false))
                                    {{ format_number($value, (int) ($row['decimals'] ?? 0)) }}
                                @elseif (is_numeric($value))
                                    {{ format_number($value) }}
                                @else
                                    {!! e($value) !!}
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    {{ $slot }}
</x-admin.layout>
