@php
    $rec = $review['recommendation'] ?? [];
    $crb = $review['crb'] ?? [];
    $crbExplain = app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb);
    $crbRec = strtolower((string) ($crb['recommendation'] ?? ''));
    $screenType = strtolower((string) ($rec['type'] ?? ''));
    $differs = (bool) ($rec['differs_from_crb'] ?? false);
    $crbTone = match ($crbRec) {
        'approve' => 'from-emerald-600 to-emerald-800',
        'refer' => 'from-amber-500 to-amber-700',
        'reject' => 'from-rose-600 to-rose-800',
        default => 'from-slate-600 to-slate-800',
    };
    $screenTone = match ($screenType) {
        'approve' => 'from-brand to-brand-light',
        'counter' => 'from-amber-500 to-amber-700',
        'asset_alternative' => 'from-sky-600 to-sky-800',
        default => 'from-slate-600 to-slate-800',
    };
@endphp

<section class="mb-5 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Committee inputs</p>
        <h3 class="text-base font-bold text-gray-900 mt-0.5">CRB suggestion vs screening recommendation</h3>
        <p class="text-xs text-gray-500 mt-0.5">Use both signals — then record the committee decision below.</p>
        @if ($differs)
            <p class="mt-2 inline-flex text-xs font-bold rounded-full px-3 py-1 bg-amber-100 text-amber-950 ring-1 ring-amber-200">
                Screening differs from CRB — read the analyst notes carefully
            </p>
        @endif
    </div>

    <div class="grid lg:grid-cols-2 gap-0 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">
        <div class="p-5">
            <div class="rounded-2xl overflow-hidden bg-gradient-to-br {{ $crbTone }} text-white p-5 h-full">
                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Bureau · CRB</p>
                <p class="text-2xl font-bold mt-1 uppercase">{{ $crbRec !== '' ? $crbRec : '—' }}</p>
                <p class="text-sm text-white/85 mt-3 leading-relaxed">{{ $crbExplain['summary'] ?? 'No CRB explanation available.' }}</p>
                @if (! empty($crbExplain['reasons']))
                    <ul class="mt-3 space-y-1 text-xs text-white/80">
                        @foreach (array_slice($crbExplain['reasons'], 0, 4) as $reason)
                            <li>• {{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-4 flex flex-wrap gap-3 text-xs text-white/80">
                    <span>Score {{ $crb['score'] ?? '—' }}</span>
                    <span>Loans {{ $crb['existing_loans'] ?? 0 }}</span>
                    <span>Delinq. {{ $crb['delinquencies'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <div class="p-5">
            <div class="rounded-2xl overflow-hidden bg-gradient-to-br {{ $screenTone }} text-white p-5 h-full">
                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Screening team</p>
                @if ($screenType !== '')
                    <p class="text-2xl font-bold mt-1 uppercase">{{ str_replace('_', ' ', $screenType) }}</p>
                    @if (! empty($rec['amount']))
                        <p class="text-sm text-white/90 mt-1 tabular-nums">{{ format_money((float) $rec['amount']) }}</p>
                    @endif
                    @if (! empty($rec['rationale_label']))
                        <p class="mt-3 text-xs font-semibold rounded-lg bg-white/15 px-3 py-2">{{ $rec['rationale_label'] }}</p>
                    @endif
                    @if (! empty($rec['remarks']))
                        <p class="text-sm text-white/90 mt-3 leading-relaxed">{{ $rec['remarks'] }}</p>
                    @endif
                    @if (! empty($rec['recommended_by']))
                        <p class="text-xs text-white/70 mt-4">
                            By {{ $rec['recommended_by']->name ?? 'Analyst' }}
                            @if (! empty($rec['recommended_at']))
                                · {{ $rec['recommended_at']->format('d M Y, H:i') }}
                            @endif
                        </p>
                    @endif
                @else
                    <p class="text-2xl font-bold mt-1">Pending</p>
                    <p class="text-sm text-white/80 mt-3">No screening recommendation submitted yet.</p>
                @endif
            </div>
        </div>
    </div>
</section>
