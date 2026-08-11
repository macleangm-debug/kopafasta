@php
    $standing = $dossier['repayment_standing'] ?? [];
    $crb = $dossier['crb'] ?? [];
    $eligibility = $dossier['eligibility'] ?? [];
    $checklist = collect($dossier['checklist'] ?? []);
    $completeCount = $checklist->where('tone', 'emerald')->count();
    $totalCount = $checklist->count();
    $eligItems = collect($eligibility['items'] ?? []);
    $eligReady = $eligItems->where('complete', true)->count();
    $eligTotal = $eligItems->count();
@endphp

<div class="space-y-8">
    {{-- Profile readiness --}}
    <section>
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">At a glance</p>
                <h4 class="text-base font-bold text-gray-900 mt-0.5">Profile readiness</h4>
                <p class="text-xs text-gray-500 mt-0.5">What is complete on this member’s file — green is ready, amber still needs the borrower.</p>
            </div>
            <p class="text-sm font-semibold tabular-nums text-gray-700">
                {{ $completeCount }}/{{ $totalCount }} sections ready
            </p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($checklist as $item)
                @php
                    $ok = ($item['tone'] ?? '') === 'emerald';
                @endphp
                <div @class([
                    'rounded-2xl px-4 py-3.5 ring-1 flex items-start gap-3',
                    'bg-emerald-50/80 ring-emerald-200' => $ok,
                    'bg-amber-50/80 ring-amber-200' => ! $ok,
                ])>
                    <span @class([
                        'mt-0.5 grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold',
                        'bg-emerald-600 text-white' => $ok,
                        'bg-amber-500 text-white' => ! $ok,
                    ])>{{ $ok ? '✓' : '!' }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ $item['label'] }}</p>
                        <p @class([
                            'text-xs mt-0.5',
                            'text-emerald-800' => $ok,
                            'text-amber-900' => ! $ok,
                        ])>{{ $item['detail'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Trust --}}
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Repayment standing</p>
                <div class="mt-2 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p class="text-3xl font-bold tabular-nums text-gray-900">{{ $standing['trust_percent'] ?? 0 }}%</p>
                        <p class="text-sm text-gray-600 mt-0.5">{{ $standing['label'] ?? '—' }} · streak {{ $standing['streak'] ?? 0 }}</p>
                    </div>
                    <p class="text-xs text-gray-500">
                        Loyalty
                        <span class="font-semibold text-gray-800">{{ number_format((int) ($standing['loyalty_points'] ?? 0)) }}</span>
                    </p>
                </div>
            </div>
            <div class="p-5 space-y-2">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Trust factors</p>
                @forelse ($standing['factors'] ?? [] as $factor)
                    @php
                        $score = (int) ($factor['score'] ?? 0);
                        $max = max(1, (int) ($factor['max'] ?? 100));
                        $pct = min(100, (int) round(($score / $max) * 100));
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm mb-1">
                            <span class="text-gray-700">{{ $factor['label'] ?? $factor['key'] }}</span>
                            <span class="font-semibold tabular-nums text-gray-900">{{ $score }}/{{ $max }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Not enough repayment history yet.</p>
                @endforelse
            </div>
        </section>

        {{-- CRB + eligibility --}}
        <div class="space-y-6">
            <section class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Credit bureau</p>
                    <h4 class="text-base font-bold text-gray-900 mt-0.5">CRB snapshot</h4>
                </div>
                <div class="p-5">
                    @if (! ($crb['available'] ?? false))
                        <p class="text-sm text-gray-600">{{ $crb['message'] ?? 'CRB not available for this member yet.' }}</p>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-3">
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Score</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $crb['score'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-3">
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Grade</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $crb['risk_grade'] ?? '—' }}</p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">
                            {{ ($crb['fresh'] ?? false) ? 'Fresh check' : 'May be stale' }}
                            @if (! empty($crb['message']))
                                · {{ $crb['message'] }}
                            @endif
                        </p>
                    @endif
                </div>
            </section>

            @if ($eligItems->isNotEmpty())
                <section class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Apply gate</p>
                            <h4 class="text-base font-bold text-gray-900 mt-0.5">Eligibility to apply</h4>
                        </div>
                        <p class="text-xs font-semibold tabular-nums text-gray-600">{{ $eligReady }}/{{ $eligTotal }} ready</p>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($eligItems->take(8) as $item)
                            @php $ok = (bool) ($item['complete'] ?? false); @endphp
                            <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-800 min-w-0">{{ $item['label'] ?? $item['key'] }}</span>
                                <span @class([
                                    'shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold',
                                    'bg-emerald-100 text-emerald-800' => $ok,
                                    'bg-amber-100 text-amber-900' => ! $ok,
                                ])>{{ $ok ? 'Ready' : 'Incomplete' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</div>
