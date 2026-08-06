@php
    $rec = $review['recommendation'] ?? [];
    $crb = $review['crb'] ?? [];
    $crbExplain = app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb);
    $crbRec = strtolower((string) ($crb['recommendation'] ?? ''));
    $screenType = strtolower((string) ($rec['type'] ?? ''));
    $differs = (bool) ($rec['differs_from_crb'] ?? false);
    $gSug = $review['guarantor_suggestion'] ?? [];
    $gRec = strtolower((string) ($gSug['recommendation'] ?? ''));
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
    $gTone = match ($gRec) {
        'approve' => 'from-emerald-600 to-emerald-800',
        'refer' => 'from-amber-500 to-amber-700',
        'reject' => 'from-rose-600 to-rose-800',
        default => 'from-slate-600 to-slate-800',
    };
@endphp

<section class="mb-5 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Committee inputs</p>
        <h3 class="text-base font-bold text-gray-900 mt-0.5">Borrower CRB · Guarantor · Screening</h3>
        <p class="text-xs text-gray-500 mt-0.5">Use all three signals — then record the committee decision below.</p>
        @if ($differs)
            <p class="mt-2 inline-flex text-xs font-bold rounded-full px-3 py-1 bg-amber-100 text-amber-950 ring-1 ring-amber-200">
                Screening differs from CRB — read the analyst notes carefully
            </p>
        @endif
    </div>

    <div class="grid lg:grid-cols-3 gap-0 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">
        <div class="p-5">
            <div class="rounded-2xl overflow-hidden bg-gradient-to-br {{ $crbTone }} text-white p-5 h-full">
                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Borrower · CRB</p>
                <p class="text-2xl font-bold mt-1 uppercase">{{ $crbRec !== '' ? $crbRec : '—' }}</p>
                <p class="text-sm text-white/85 mt-3 leading-relaxed">{{ $crbExplain['summary'] ?? 'No CRB explanation available.' }}</p>
                @if (! empty($crbExplain['reasons']))
                    <ul class="mt-3 space-y-1 text-xs text-white/80">
                        @foreach (array_slice($crbExplain['reasons'], 0, 3) as $reason)
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
            <div class="rounded-2xl overflow-hidden bg-gradient-to-br {{ $gTone }} text-white p-5 h-full">
                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Guarantor</p>
                <p class="text-2xl font-bold mt-1 uppercase">
                    @if ($gRec === 'not_required')
                        N/A
                    @elseif ($gRec === 'pending_profile')
                        Profile
                    @elseif ($gRec === 'missing')
                        Missing
                    @elseif ($gRec !== '')
                        {{ $gRec }}
                    @else
                        —
                    @endif
                </p>
                @if (! empty($gSug['name']))
                    <p class="text-xs text-white/70 mt-2 truncate">{{ $gSug['name'] }}</p>
                @endif
                <p class="text-sm text-white/85 mt-3 leading-relaxed">{{ $gSug['summary'] ?? 'No guarantor signal yet.' }}</p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs text-white/80">
                    @if (! empty($gSug['score']))
                        <span>Score {{ $gSug['score'] }}</span>
                    @endif
                    @if (! empty($gSug['existing_loans']))
                        <span>Loans {{ $gSug['existing_loans'] }}</span>
                    @endif
                    @if (! empty($gSug['freshness_label']))
                        <span>{{ $gSug['freshness_label'] }}</span>
                    @endif
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
                @php
                    $cl = $review['screening_checklist'] ?? null;
                    if ((! $cl || ($cl['total'] ?? 0) < 1) && isset($record)) {
                        $cl = app(\App\Services\ScreeningChecklistService::class)->viewModel($record, auth()->user(), 'borrower');
                    }
                @endphp
                @if ($cl && ($cl['total'] ?? 0) > 0)
                    <p class="mt-4 text-xs text-white/85">
                        Checklist {{ (int) ($cl['checked'] ?? 0) }}/{{ (int) $cl['total'] }}
                        ({{ (int) ($cl['percent'] ?? 0) }}%)
                    </p>
                    <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record]) }}#review-desk"
                       class="mt-2 inline-flex text-[11px] font-semibold underline underline-offset-2 text-white/90 hover:text-white">
                        View what was done
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
