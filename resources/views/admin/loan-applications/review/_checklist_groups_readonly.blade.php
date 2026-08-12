{{-- Read-only Pass/Fail groups. Expects: $groups and Alpine openGroup/toggleGroup in parent. --}}
@foreach ($groups as $group)
    @php
        $groupKeyRo = (string) ($group['key'] ?? '');
    @endphp
    <div class="rounded-2xl ring-2 ring-brand/15 overflow-hidden shadow-sm bg-white">
        <button type="button" class="w-full px-4 py-3.5 bg-brand text-white text-left" @click="toggleGroup(@js($groupKeyRo))">
            <h4 class="text-base font-extrabold tracking-tight inline-flex items-center gap-2">
                <svg class="size-4 text-brand-gold transition" :class="openGroup === @js($groupKeyRo) ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                <span>{{ $group['label'] }}</span>
            </h4>
            <p class="text-[11px] text-white/80 mt-0.5 tabular-nums">
                {{ $group['decided'] ?? 0 }}/{{ $group['total'] ?? count($group['items'] ?? []) }} reviewed
            </p>
        </button>
        <ul x-show="openGroup === @js($groupKeyRo)" x-cloak class="divide-y divide-gray-50 bg-white">
            @foreach ($group['items'] as $item)
                <li class="p-4">
                    <div class="flex items-start gap-3">
                        <span @class([
                            'mt-0.5 inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-md px-1.5 text-[10px] font-bold ring-1',
                            'bg-emerald-50 text-emerald-800 ring-emerald-200' => ($item['verdict'] ?? '') === 'pass',
                            'bg-rose-50 text-rose-800 ring-rose-200' => ($item['verdict'] ?? '') === 'fail',
                            'bg-sky-50 text-sky-800 ring-sky-200' => ($item['verdict'] ?? '') === 'na',
                            'bg-gray-50 text-gray-400 ring-gray-200' => ($item['verdict'] ?? null) === null,
                        ])>
                            {{ match ($item['verdict'] ?? null) { 'pass' => '✓', 'fail' => '✗', 'na' => 'N/A', default => '—' } }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ $item['label'] }}</p>
                            @if (($item['verdict'] ?? '') === 'fail' && ($item['fail_reason_label'] ?? null))
                                <p class="text-xs text-rose-800 mt-1">{{ $item['fail_reason_label'] }}</p>
                            @endif
                            @if (! empty($item['evidence']['rows']))
                                <dl class="mt-2 grid sm:grid-cols-2 gap-1.5">
                                    @foreach (array_slice($item['evidence']['rows'], 0, 4) as $row)
                                        <div class="text-[11px] text-gray-600">
                                            <span class="font-semibold text-gray-500">{{ $row['label'] }}:</span>
                                            {{ $row['value'] }}
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endforeach
