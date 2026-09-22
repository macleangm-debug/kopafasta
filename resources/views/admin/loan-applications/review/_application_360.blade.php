{{-- Application 360 — operational snapshot under the credit-file letterhead. --}}
@php
    $app360 = $app360 ?? app(\App\Services\Application360Presenter::class)->forApplication(
        $record,
        auth()->user(),
        $stageHistory ?? null,
        $documentRequests ?? null,
    );
    $next = $app360['next'] ?? [];
    $lifecycle = $app360['lifecycle'] ?? [];
    $attention = $app360['attention'] ?? [];
    $timeline = $app360['timeline'] ?? [];
@endphp

<section id="application-360" class="mb-5 rounded-2xl bg-white ring-1 ring-slate-200 shadow-sm overflow-hidden">
    <div class="px-4 sm:px-5 py-4 border-b border-slate-100 bg-slate-50/80">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-500 font-bold">Application 360</p>
                <p class="text-sm font-bold text-slate-900">
                    {{ $app360['application_number'] ?? $record->application_number }}
                    <span class="text-slate-400 font-semibold">·</span>
                    {{ $app360['member_name'] ?? $record->partyLabel() }}
                    @if (! empty($app360['member_no']))
                        <span class="text-slate-400 font-normal">({{ $app360['member_no'] }})</span>
                    @endif
                </p>
                <p class="text-xs text-slate-600 flex flex-wrap gap-x-3 gap-y-1">
                    <span>{{ $app360['product_name'] ?? '—' }}</span>
                    <span>{{ $app360['amount_label'] ?? '—' }}</span>
                    <span>{{ $app360['stage_label'] ?? '—' }}</span>
                    @if (! empty($app360['gate_label']))
                        <span>{{ $app360['gate_label'] }}</span>
                    @endif
                    <span class="font-semibold text-slate-800">{{ $app360['overall_status'] ?? '—' }}</span>
                </p>
            </div>
            @if (! empty($app360['member_url']))
                <a href="{{ $app360['member_url'] }}"
                   class="shrink-0 text-xs font-semibold text-brand hover:underline">
                    Open Member 360 →
                </a>
            @endif
        </div>
    </div>

    <div class="px-4 sm:px-5 py-4 space-y-4">
        <div class="rounded-xl bg-brand/[0.04] ring-1 ring-brand/15 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-widest text-brand font-bold">Needs Attention / Next Action</p>
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ $next['headline'] ?? 'Continue' }}</p>
                @if (! empty($next['gate_label']))
                    <p class="text-xs text-slate-600 mt-0.5">{{ $next['gate_label'] }}
                        @if (isset($next['percent']))
                            · {{ (int) $next['percent'] }}%
                        @endif
                    </p>
                @endif
            </div>
            @if (! empty($next['href']) && ($next['cta_kind'] ?? '') !== 'waiting')
                <a href="{{ $next['href'] }}"
                   class="shrink-0 inline-flex justify-center items-center px-4 py-2.5 rounded-xl bg-brand text-white text-xs font-bold shadow-sm hover:bg-brand-light">
                    {{ $next['cta'] ?? 'Continue' }}
                </a>
            @elseif (($next['cta_kind'] ?? '') === 'waiting')
                <span class="shrink-0 inline-flex justify-center items-center px-4 py-2.5 rounded-xl bg-amber-50 text-amber-950 text-xs font-bold ring-1 ring-amber-200">
                    {{ $next['cta'] ?? 'Waiting' }}
                </span>
            @endif
        </div>

        <div>
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-2">Lifecycle</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($lifecycle as $step)
                    @php
                        $tone = match ($step['state'] ?? '') {
                            'complete' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                            'current' => 'bg-brand/10 text-brand ring-brand/25 font-bold',
                            'attention' => 'bg-amber-50 text-amber-950 ring-amber-200 font-bold',
                            default => 'bg-slate-50 text-slate-500 ring-slate-200',
                        };
                        $mark = match ($step['state'] ?? '') {
                            'complete' => '✓',
                            'current' => '●',
                            'attention' => '!',
                            default => '○',
                        };
                    @endphp
                    @if (! empty($step['href']))
                        <a href="{{ $step['href'] }}"
                           class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] ring-1 {{ $tone }}">
                            <span aria-hidden="true">{{ $mark }}</span> {{ $step['label'] }}
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] ring-1 {{ $tone }}">
                            <span aria-hidden="true">{{ $mark }}</span> {{ $step['label'] }}
                        </span>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-2">
            @foreach ($attention as $row)
                @php
                    $tone = match ($row['tone'] ?? 'quiet') {
                        'attention' => 'bg-amber-50 ring-amber-200 text-amber-950',
                        'current' => 'bg-sky-50 ring-sky-200 text-sky-950',
                        default => 'bg-slate-50 ring-slate-200 text-slate-700',
                    };
                @endphp
                <div class="rounded-xl px-3 py-2.5 ring-1 {{ $tone }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold">
                                {{ $row['label'] }}
                                @if (($row['tone'] ?? '') === 'quiet' && ($row['detail'] ?? '') === 'Complete')
                                    <span class="text-emerald-700">✓</span>
                                @endif
                            </p>
                            @if (! empty($row['detail']) && ($row['detail'] ?? '') !== 'Complete')
                                <p class="text-[11px] opacity-80 mt-0.5">{{ $row['detail'] }}</p>
                            @endif
                        </div>
                        @if (! empty($row['href']) && ($row['tone'] ?? '') !== 'quiet')
                            <a href="{{ $row['href'] }}" class="shrink-0 text-[11px] font-bold underline">Open</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if (count($timeline) > 0)
            <details class="rounded-xl ring-1 ring-slate-200 bg-white">
                <summary class="cursor-pointer list-none px-3 py-2.5 text-xs font-bold text-slate-700 flex items-center justify-between">
                    <span>Application timeline</span>
                    <span class="text-slate-400 font-normal">{{ count($timeline) }} events</span>
                </summary>
                <ol class="border-t border-slate-100 px-3 py-2 space-y-2 max-h-56 overflow-y-auto">
                    @foreach ($timeline as $event)
                        <li class="text-[11px] text-slate-700">
                            <span class="text-slate-400 tabular-nums">{{ $event['at'] }}</span>
                            <span class="mx-1 text-slate-300">·</span>
                            {{ $event['label'] }}
                        </li>
                    @endforeach
                </ol>
            </details>
        @endif
    </div>
</section>
