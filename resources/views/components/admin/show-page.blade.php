@props([
    'title',
    'heading',
    'subheading' => null,
    'backUrl',
    'backLabel' => 'Back',
    'editUrl' => null,
    'fields' => [],
])

<x-admin.layout
    :title="$title"
    heading=""
    :backUrl="$backUrl"
    :backLabel="$backLabel">

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-brand/10">
        <div class="bg-gradient-to-r from-brand via-brand to-brand-light px-6 py-5 text-white flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
                <h1 class="text-xl font-bold tracking-tight mt-1">{{ $heading }}</h1>
                @if ($subheading)
                    <p class="text-sm text-white/75 mt-1 font-mono">{{ $subheading }}</p>
                @endif
            </div>
            @if ($editUrl)
                <a href="{{ $editUrl }}"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl shadow-sm transition">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
            @endif
        </div>

        @if (! empty($fields))
            <div class="p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 text-sm">
                    @foreach ($fields as $key => $row)
                        @php
                            $label = is_array($row) ? ($row['label'] ?? $key) : $key;
                            $value = is_array($row) ? ($row['value'] ?? null) : $row;
                            $wide  = is_array($row) ? ($row['wide']  ?? false) : false;
                        @endphp
                        <div @class(['sm:col-span-2' => $wide, 'rounded-xl bg-brand-muted/25 px-4 py-3 ring-1 ring-brand/5'])>
                            <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">{{ $label }}</dt>
                            <dd class="text-gray-900 mt-1 font-semibold tabular-nums">
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
                                    <span class="text-gray-400 font-normal">—</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>

    {{ $slot }}
</x-admin.layout>
