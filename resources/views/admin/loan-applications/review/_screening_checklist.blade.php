@php
    $checklist = $review['screening_checklist'] ?? [
        'groups' => [],
        'checked' => 0,
        'total' => 0,
        'percent' => 0,
        'can_edit' => false,
    ];
    $canEdit = (bool) ($checklist['can_edit'] ?? false);
    $percent = (int) ($checklist['percent'] ?? 0);
    $checked = (int) ($checklist['checked'] ?? 0);
    $total = (int) ($checklist['total'] ?? 0);
@endphp

<div id="tab-screening-checklist" class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Screening desk</p>
            <h4 class="text-base font-bold text-gray-900 mt-0.5">Verification checklist</h4>
            <p class="text-xs text-gray-500 mt-0.5">
                @if ($canEdit)
                    Tick each check you complete. This unifies screening work — committee can only review progress.
                @else
                    Read-only view of what the screening team has completed.
                @endif
            </p>
        </div>
        <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/15 px-4 py-3 text-right min-w-[8rem]">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Progress</p>
            <p class="text-lg font-bold text-brand tabular-nums mt-0.5">{{ $checked }}/{{ $total }}</p>
            <p class="text-xs text-brand/80">{{ $percent }}% done</p>
        </div>
    </div>

    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
        <div class="h-full rounded-full bg-brand transition-all" style="width: {{ min(100, max(0, $percent)) }}%"></div>
    </div>

    @if ($canEdit)
        <form method="POST" action="{{ route('admin.loan-applications.screening-checklist', $record) }}" class="space-y-5">
            @csrf
            @foreach ($checklist['groups'] ?? [] as $group)
                <section class="rounded-2xl ring-1 ring-gray-100 bg-white overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white">
                        <h5 class="text-sm font-bold text-gray-900">{{ $group['label'] }}</h5>
                    </div>
                    <ul class="divide-y divide-gray-50">
                        @foreach ($group['items'] as $item)
                            @php
                                [$itemGroup, $itemKey] = array_pad(explode('.', $item['key'], 2), 2, '');
                            @endphp
                            <li class="px-4 py-3 flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    id="checklist-{{ str_replace('.', '-', $item['key']) }}"
                                    name="items[{{ $itemGroup }}][{{ $itemKey }}]"
                                    value="1"
                                    @checked($item['checked'])
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand"
                                >
                                <label for="checklist-{{ str_replace('.', '-', $item['key']) }}" class="flex-1 cursor-pointer">
                                    <span class="text-sm font-medium text-gray-900">{{ $item['label'] }}</span>
                                    @if ($item['checked'] && (! empty($item['by_name']) || ! empty($item['at'])))
                                        <span class="block text-[11px] text-gray-500 mt-0.5">
                                            @if (! empty($item['by_name']))
                                                {{ $item['by_name'] }}
                                            @endif
                                            @if (! empty($item['at']))
                                                · {{ \Illuminate\Support\Carbon::parse($item['at'])->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                                            @endif
                                        </span>
                                    @endif
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-light transition">
                    Save checklist
                </button>
            </div>
        </form>
    @else
        <div class="space-y-5">
            @forelse ($checklist['groups'] ?? [] as $group)
                <section class="rounded-2xl ring-1 ring-gray-100 bg-white overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                        <h5 class="text-sm font-bold text-gray-900">{{ $group['label'] }}</h5>
                    </div>
                    <ul class="divide-y divide-gray-50">
                        @foreach ($group['items'] as $item)
                            <li class="px-4 py-3 flex items-start gap-3">
                                <span @class([
                                    'mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-[10px] font-bold ring-1',
                                    'bg-emerald-50 text-emerald-800 ring-emerald-200' => $item['checked'],
                                    'bg-gray-50 text-gray-400 ring-gray-200' => ! $item['checked'],
                                ])>
                                    @if ($item['checked'])
                                        ✓
                                    @else
                                        —
                                    @endif
                                </span>
                                <div class="flex-1">
                                    <p @class([
                                        'text-sm font-medium',
                                        'text-gray-900' => $item['checked'],
                                        'text-gray-500' => ! $item['checked'],
                                    ])>{{ $item['label'] }}</p>
                                    @if ($item['checked'] && (! empty($item['by_name']) || ! empty($item['at'])))
                                        <p class="text-[11px] text-gray-500 mt-0.5">
                                            @if (! empty($item['by_name']))
                                                {{ $item['by_name'] }}
                                            @endif
                                            @if (! empty($item['at']))
                                                · {{ \Illuminate\Support\Carbon::parse($item['at'])->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="text-sm text-gray-500">No checklist items configured.</p>
            @endforelse
        </div>
    @endif
</div>
