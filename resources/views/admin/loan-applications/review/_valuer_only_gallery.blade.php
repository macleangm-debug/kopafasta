@php
    $extraPairs = collect($extraPairs ?? []);
    $extraTypes = $extraPairs
        ->groupBy('angle')
        ->map(fn ($rows, $angle) => [
            'angle' => (string) $angle,
            'label' => (string) ($rows->first()['label'] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $angle))),
            'count' => $rows->count(),
        ])
        ->values();
@endphp
<div x-show="photoTab === 'extra'" x-cloak class="p-3 space-y-3">
    <div>
        <p class="text-[11px] font-bold text-brand uppercase tracking-widest">Valuer-only types</p>
        <p class="text-[12px] text-slate-600 mt-0.5">Taken by the valuer — the borrower does not upload these (engine, VIN, dashboard, damage, interior, access).</p>
    </div>
    @if ($extraPairs->isEmpty())
        <p class="text-sm text-slate-600">No valuer-only photos on this file yet.</p>
    @else
        <div class="flex gap-1.5 overflow-x-auto pb-0.5">
            <button type="button" @click="extraType = 'all'"
                    class="shrink-0 rounded-lg px-2.5 py-1.5 text-[11px] font-bold ring-1"
                    :class="extraType === 'all' ? 'bg-brand text-white ring-brand' : 'bg-white text-slate-700 ring-slate-200'">
                All types · {{ $extraPairs->count() }}
            </button>
            @foreach ($extraTypes as $type)
                <button type="button" @click="extraType = @js($type['angle'])"
                        class="shrink-0 rounded-lg px-2.5 py-1.5 text-[11px] font-bold ring-1"
                        :class="extraType === @js($type['angle']) ? 'bg-brand text-white ring-brand' : 'bg-white text-slate-700 ring-slate-200'">
                    {{ $type['label'] }}
                    @if ($type['count'] > 1)
                        · {{ $type['count'] }}
                    @endif
                </button>
            @endforeach
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach ($extraPairs as $pair)
                @php
                    $valuerUrl = $pair['valuer']['url'] ?? null;
                    $pairAssetId = (int) ($pair['asset_id'] ?? 0);
                    $pairAngle = (string) ($pair['angle'] ?? '');
                    $pairLabel = (string) ($pair['label'] ?? $pairAngle);
                @endphp
                <div x-show="(extraType === 'all' || extraType === @js($pairAngle)) && (!assetTab || assetTab === {{ $pairAssetId }})"
                     x-cloak>
                    <button type="button" class="w-full text-left group"
                            @if ($valuerUrl)
                                @click="open(@js($valuerUrl), @js($pair['valuer']['label'] ?? $pairLabel))"
                            @endif>
                        @if ($valuerUrl)
                            <img src="{{ $valuerUrl }}" alt="{{ $pairLabel }}"
                                 class="w-full h-36 sm:h-44 object-cover rounded-xl ring-1 ring-gray-200 group-hover:ring-brand">
                        @else
                            <div class="h-36 sm:h-44 grid place-items-center rounded-xl bg-slate-50 text-[11px] text-slate-600 ring-1 ring-slate-100">No photo</div>
                        @endif
                        <p class="mt-1.5 text-[11px] font-bold text-brand">{{ $pairLabel }}</p>
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-500">Not taken by the borrower</p>
                        @if ($valuerUrl)
                            <span class="text-[11px] font-semibold text-brand">Enlarge</span>
                        @endif
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
