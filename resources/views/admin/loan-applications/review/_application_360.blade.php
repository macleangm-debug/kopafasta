{{-- Application 360 — Member-360 visual language; canonical next-action services only. --}}
@php
    $app360 = $app360 ?? app(\App\Services\Application360Presenter::class)->forApplication(
        $record,
        auth()->user(),
        $stageHistory ?? null,
        $documentRequests ?? null,
    );
    $next = $app360['next'] ?? [];
    $lifecycle = $app360['lifecycle'] ?? [];
    $people = $app360['people'] ?? [];
    $readiness = $app360['readiness'] ?? [];
    $timeline = $app360['timeline'] ?? [];
    $percent = isset($app360['progress_percent']) ? (int) $app360['progress_percent'] : null;
@endphp

<section id="application-360" class="mb-6 space-y-4">
    {{-- Application Hero --}}
    <div class="rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">Application 360</p>
                    <h2 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">
                        {{ $app360['application_number'] ?? $record->application_number }}
                    </h2>
                    <p class="text-sm text-white/80 mt-1 truncate">
                        {{ $app360['member_name'] ?? $record->partyLabel() }}
                        @if (! empty($app360['member_no']))
                            <span class="text-white/50">·</span> Member {{ $app360['member_no'] }}
                        @endif
                    </p>
                    <p class="text-xs text-white/70 mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        <span>{{ $app360['product_name'] ?? '—' }}</span>
                        <span>{{ $app360['amount_label'] ?? '—' }}</span>
                        <span>{{ $app360['stage_label'] ?? '—' }}</span>
                        @if (! empty($app360['gate_label']))
                            <span>{{ $app360['gate_label'] }}</span>
                        @endif
                    </p>
                    @if ($percent !== null)
                        <div class="mt-3 max-w-md">
                            <div class="flex items-center justify-between text-[11px] text-white/75 mb-1">
                                <span>Progress</span>
                                <span class="tabular-nums font-semibold">{{ $percent }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-white/15 overflow-hidden">
                                <div class="h-full rounded-full bg-brand-gold" style="width: {{ max(0, min(100, $percent)) }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                        {{ $app360['status_label'] ?? $app360['overall_status'] ?? '—' }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-gold/20 text-brand-gold ring-1 ring-brand-gold/40">
                        {{ $app360['overall_status'] ?? '—' }}
                    </span>
                    @if (! empty($app360['member_url']))
                        <a href="{{ $app360['member_url'] }}"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-brand-gold text-brand hover:brightness-95">
                            Open Member 360 →
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Needs Attention --}}
    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-5 py-4">
        <div class="flex flex-col lg:flex-row lg:items-stretch gap-4">
            <div class="min-w-0 flex-1 space-y-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-bold">Needs Attention / Next Action</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">What is missing?</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">{{ $next['missing'] ?? $next['headline'] ?? 'Continue' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Who needs to act?</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">{{ $next['who'] ?? 'Staff' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Deadline / waiting</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">
                            @if (! empty($next['deadline']))
                                {{ is_string($next['deadline']) ? $next['deadline'] : format_app_datetime($next['deadline'], 'd M Y') }}
                            @elseif (($next['cta_kind'] ?? '') === 'waiting')
                                Waiting
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">What happens next?</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">{{ $next['headline'] ?? 'Continue' }}</p>
                    </div>
                </div>
            </div>
            <div class="shrink-0 flex lg:items-center">
                @if (! empty($next['href']) && ($next['cta_kind'] ?? '') !== 'waiting')
                    <a href="{{ $next['href'] }}"
                       class="w-full lg:w-auto inline-flex justify-center items-center px-5 py-3 rounded-xl bg-brand text-white text-sm font-bold shadow-sm hover:bg-brand-light">
                        {{ $next['cta'] ?? 'Continue' }}
                    </a>
                @elseif (($next['cta_kind'] ?? '') === 'waiting')
                    <span class="w-full lg:w-auto inline-flex justify-center items-center px-5 py-3 rounded-xl bg-amber-50 text-amber-950 text-sm font-bold ring-1 ring-amber-200">
                        {{ $next['cta'] ?? 'Waiting' }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- People --}}
    @if (count($people) > 0)
        <div>
            <div class="flex items-end justify-between gap-3 mb-2 px-0.5">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">People</p>
                <p class="text-[11px] text-slate-500">{{ count($people) }} participant{{ count($people) === 1 ? '' : 's' }}</p>
            </div>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach ($people as $person)
                    @php
                        $tone = match ($person['tone'] ?? '') {
                            'complete' => 'ring-emerald-200 bg-emerald-50/40',
                            'attention' => 'ring-amber-200 bg-amber-50/50',
                            default => 'ring-slate-200 bg-white',
                        };
                    @endphp
                    <div class="rounded-2xl ring-1 {{ $tone }} px-4 py-3 flex flex-col gap-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ $person['role'] }}</p>
                                <p class="text-sm font-bold text-slate-900 truncate">{{ $person['name'] }}</p>
                            </div>
                            @if (! empty($person['href']))
                                <a href="{{ $person['href'] }}" class="shrink-0 text-[11px] font-bold text-brand hover:underline">Open →</a>
                            @endif
                        </div>
                        <dl class="grid grid-cols-2 gap-x-2 gap-y-1 text-[11px]">
                            <div>
                                <dt class="text-slate-500">Profile / KYC</dt>
                                <dd class="font-semibold text-slate-800">{{ $person['kyc'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">CRB</dt>
                                <dd class="font-semibold text-slate-800">{{ $person['crb'] ?? '—' }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-slate-500">Readiness</dt>
                                <dd class="font-semibold text-slate-800">{{ $person['readiness'] ?? '—' }}</dd>
                            </div>
                        </dl>
                        @if (! empty($person['issue']))
                            <p class="text-[11px] font-semibold text-amber-900 bg-amber-100/70 rounded-lg px-2 py-1">{{ $person['issue'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Journey / Readiness --}}
    <div>
        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-2 px-0.5">Journey / readiness</p>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-2">
            @foreach ($readiness as $row)
                @php
                    $state = $row['state'] ?? 'upcoming';
                    if ($state === 'na') {
                        continue;
                    }
                    $tone = match ($state) {
                        'complete' => 'bg-emerald-50 ring-emerald-200 text-emerald-900',
                        'current' => 'bg-brand/5 ring-brand/20 text-brand',
                        'attention' => 'bg-amber-50 ring-amber-200 text-amber-950',
                        default => 'bg-slate-50 ring-slate-200 text-slate-600',
                    };
                    $mark = match ($state) {
                        'complete' => '✓ Complete',
                        'current' => '● Current',
                        'attention' => '! Needs attention',
                        default => '○ Upcoming',
                    };
                @endphp
                @if (! empty($row['href']) && in_array($state, ['current', 'attention'], true))
                    <a href="{{ $row['href'] }}" class="rounded-xl ring-1 px-3 py-2.5 {{ $tone }} block hover:brightness-95">
                        <p class="text-xs font-bold">{{ $row['label'] }}</p>
                        <p class="text-[11px] mt-0.5 opacity-90">{{ $mark }}</p>
                        @if (! empty($row['detail']) && $state !== 'complete')
                            <p class="text-[11px] mt-1 font-semibold">{{ $row['detail'] }}</p>
                        @endif
                    </a>
                @else
                    <div class="rounded-xl ring-1 px-3 py-2.5 {{ $tone }}">
                        <p class="text-xs font-bold">{{ $row['label'] }}</p>
                        <p class="text-[11px] mt-0.5 opacity-90">{{ $mark }}</p>
                        @if (! empty($row['detail']) && $state !== 'complete')
                            <p class="text-[11px] mt-1 font-semibold">{{ $row['detail'] }}</p>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Compact lifecycle strip --}}
    <div class="rounded-2xl bg-white ring-1 ring-slate-200 px-4 py-3">
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
                    <a href="{{ $step['href'] }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] ring-1 {{ $tone }}">
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

    {{-- Timeline (bottom) --}}
    @if (count($timeline) > 0)
        <details class="rounded-2xl ring-1 ring-slate-200 bg-white">
            <summary class="cursor-pointer list-none px-4 py-3 text-xs font-bold text-slate-700 flex items-center justify-between">
                <span>Application timeline</span>
                <span class="text-slate-400 font-normal">{{ count($timeline) }} events</span>
            </summary>
            <ol class="border-t border-slate-100 px-4 py-3 space-y-2 max-h-64 overflow-y-auto">
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
</section>
