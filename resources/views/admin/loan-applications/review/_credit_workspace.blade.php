@php
    $customer = $review['customer'];
    $product = $review['product'];
    $risk = $review['risk'] ?? [];
    $crb = $review['crb'] ?? [];
    $crbExplain = app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb);
    $stage = $record->current_stage ?? 'submitted';
    $isCommitteeStage = $stage === 'pre_approval';
    $isScreeningStage = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
    $afford = $affordability ?? ($review['affordability'] ?? []);
    $affordPass = (bool) ($afford['pass'] ?? false);
    $affordWarn = ($afford['verdict'] ?? '') === 'warn';
    $crbRec = strtolower((string) ($crb['recommendation'] ?? ''));
    $riskBand = $risk['band'] ?? 'high';

    $crbTone = match ($crbRec) {
        'approve' => ['card' => 'from-emerald-600 to-emerald-800', 'badge' => 'bg-emerald-100 text-emerald-900'],
        'refer' => ['card' => 'from-amber-500 to-amber-700', 'badge' => 'bg-amber-100 text-amber-950'],
        'reject' => ['card' => 'from-rose-600 to-rose-800', 'badge' => 'bg-rose-100 text-rose-900'],
        default => ['card' => 'from-brand to-brand-light', 'badge' => 'bg-white/20 text-white'],
    };
    $riskTone = match ($riskBand) {
        'low' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        'medium' => 'bg-amber-50 text-amber-950 ring-amber-200',
        default => 'bg-rose-50 text-rose-900 ring-rose-200',
    };
@endphp

{{-- ── Decision deck ─────────────────────────────────────────────── --}}
<section class="space-y-4 mb-6">
    @php
        $anomalies = $underwritingAnomalies
            ?? app(\App\Services\UnderwritingAnomalyService::class)->forApplication($record, $review);
        $anomalyTone = [
            'critical' => 'bg-rose-50 ring-rose-200 text-rose-950',
            'warning' => 'bg-amber-50 ring-amber-200 text-amber-950',
            'info' => 'bg-sky-50 ring-sky-200 text-sky-950',
        ];
        $anomalyDot = [
            'critical' => 'bg-rose-500',
            'warning' => 'bg-amber-500',
            'info' => 'bg-sky-500',
        ];
        $anomalyCounts = collect($anomalies)->countBy(fn ($a) => $a['severity'] ?? 'info');
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">
                {{ $isCommitteeStage ? 'Committee workspace' : 'Screening workspace' }}
            </p>
            <h2 class="text-lg font-bold text-gray-900 mt-0.5">What you need to decide</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $isCommitteeStage
                    ? 'Review the analyst recommendation, CRB and affordability — then record the committee decision.'
                    : 'Review CRB, affordability and the borrower file — then submit your credit recommendation.' }}
            </p>
        </div>
        @if ($isScreeningStage || $isCommitteeStage)
            <details class="group rounded-xl bg-white ring-1 ring-brand/15 overflow-hidden">
                <summary class="cursor-pointer list-none px-4 py-2.5 text-xs font-semibold text-brand flex items-center gap-2 [&::-webkit-details-marker]:hidden">
                    Assign analyst
                    <span class="text-gray-400 font-normal">{{ $record->assignedAnalyst?->name ?? 'Unassigned' }}</span>
                    <svg class="size-3.5 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </summary>
                <form method="POST" action="{{ route('admin.loan-applications.assign-analyst', $record) }}"
                      class="px-4 pb-3 pt-1 border-t border-gray-100 flex flex-col sm:flex-row sm:items-end gap-3">
                    @csrf
                    <div class="flex-1 min-w-0">
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Credit analyst</label>
                        <select name="assigned_analyst_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                            <option value="">Unassigned</option>
                            @foreach ($assignableAnalysts ?? [] as $analyst)
                                <option value="{{ $analyst->id }}" @selected((int) $record->assigned_analyst_id === (int) $analyst->id)>
                                    {{ $analyst->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="inline-flex justify-center bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm shrink-0">Save</button>
                </form>
            </details>
        @endif
    </div>

    {{-- Facility + risk + borrower CRB + guarantor --}}
    @php
        $gSug = $review['guarantor_suggestion'] ?? [];
        $gRec = strtolower((string) ($gSug['recommendation'] ?? ''));
        $gTone = match ($gRec) {
            'approve' => ['card' => 'from-emerald-600 to-emerald-800', 'badge' => 'bg-emerald-100 text-emerald-900'],
            'refer' => ['card' => 'from-amber-500 to-amber-700', 'badge' => 'bg-amber-100 text-amber-950'],
            'reject' => ['card' => 'from-rose-600 to-rose-800', 'badge' => 'bg-rose-100 text-rose-900'],
            'missing', 'pending_profile' => ['card' => 'from-slate-600 to-slate-800', 'badge' => 'bg-white/20 text-white'],
            'not_required' => ['card' => 'from-slate-500 to-slate-700', 'badge' => 'bg-white/20 text-white'],
            default => ['card' => 'from-brand to-brand-light', 'badge' => 'bg-white/20 text-white'],
        };
    @endphp
    <div class="grid lg:grid-cols-12 gap-4">
        <div class="lg:col-span-3 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Facility summary</p>
                <p class="text-2xl font-bold mt-1 tabular-nums">{{ format_money((float) $record->requested_amount) }}</p>
                <p class="text-sm text-white/75 mt-1">
                    {{ $record->requested_tenure_months }} months
                    @if ($product) · {{ $product->name }} @endif
                </p>
            </div>
            <div class="px-5 py-4 grid grid-cols-2 gap-3 text-sm">
                @if ($record->recommended_amount)
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Recommended</p>
                        <p class="font-bold text-brand mt-0.5">{{ format_money((float) $record->recommended_amount) }}</p>
                    </div>
                @endif
                @if ($record->offered_amount)
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Offered</p>
                        <p class="font-bold text-amber-800 mt-0.5">{{ format_money((float) $record->offered_amount) }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Submitted</p>
                    <p class="font-semibold text-gray-900 mt-0.5">{{ optional($record->submitted_at)->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Member</p>
                    <p class="font-semibold text-gray-900 mt-0.5 font-mono text-xs">{{ $customer->member_no ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-3 rounded-2xl ring-1 p-5 {{ $riskTone }} shadow-sm">
            <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">Risk score</p>
            <div class="flex items-end gap-1.5 mt-2">
                <span class="text-4xl font-bold leading-none tabular-nums">{{ $risk['score'] ?? '—' }}</span>
                <span class="text-sm font-semibold pb-1 opacity-70">/100</span>
            </div>
            <p class="text-sm font-bold mt-2">{{ $risk['label'] ?? '—' }}</p>
            <p class="text-xs mt-2 opacity-90">
                System: <span class="font-bold uppercase">{{ $risk['recommendation'] ?? '—' }}</span>
            </p>
            @if (! empty($risk['explanation']))
                <p class="mt-3 text-[11px] leading-relaxed opacity-95 border-t border-current/15 pt-2">
                    {{ $risk['explanation'] }}
                </p>
            @endif
            @if (! empty($risk['factors']))
                <ul class="mt-2 space-y-1 text-[11px] opacity-90">
                    @foreach (array_slice($risk['factors'], 0, 5) as $factor)
                        <li>• {{ $factor }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-2 text-[10px] opacity-70 leading-snug">
                Includes borrower CRB and guarantor CRB/profile. ≥75 approve · ≥50 refer · below 50 reject.
            </p>
        </div>

        <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $crbTone['card'] }} text-white">
            <div class="px-5 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Borrower CRB</p>
                        <p class="text-2xl font-bold mt-1 uppercase tracking-tight">{{ $crbRec !== '' ? $crbRec : '—' }}</p>
                    </div>
                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 {{ $crbTone['badge'] }}">
                        Score {{ $crb['score'] ?? '—' }}
                    </span>
                </div>
                <p class="text-sm text-white/85 mt-3 leading-relaxed">{{ $crbExplain['summary'] ?? 'No CRB explanation available.' }}</p>

                @php
                    $bAffordVerdict = strtolower((string) ($afford['verdict'] ?? ($affordPass ? 'pass' : 'fail')));
                    $bExternalLoans = (int) ($crb['existing_loans'] ?? 0);
                    $bOutstanding = (float) ($crb['outstanding_balance'] ?? 0);
                @endphp
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-white/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">Affordability</p>
                        <p class="text-sm font-bold mt-0.5 uppercase">
                            @if ($bAffordVerdict === 'pass' && ! $affordWarn) Pass
                            @elseif ($bAffordVerdict === 'warn' || $affordWarn) Near limit
                            @else Fail
                            @endif
                        </p>
                        <p class="text-[11px] text-white/75 mt-0.5 truncate">
                            EMI {{ format_money($afford['proposed_installment'] ?? $afford['new_emi'] ?? 0) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">Other institutions</p>
                        <p class="text-sm font-bold mt-0.5">{{ $bExternalLoans }} loan{{ $bExternalLoans === 1 ? '' : 's' }}</p>
                        <p class="text-[11px] text-white/75 mt-0.5 truncate">
                            @if ($bOutstanding > 0)
                                Outst. {{ format_money($bOutstanding) }}
                            @else
                                No outstanding reported
                            @endif
                        </p>
                    </div>
                </div>
                <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'person' => 'borrower', 'tab' => 'crb']) }}#borrower-file"
                   class="mt-3 inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition">
                    Full borrower CRB →
                </a>
            </div>
        </div>

        <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $gTone['card'] }} text-white">
            <div class="px-5 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Guarantor</p>
                        <p class="text-2xl font-bold mt-1 uppercase tracking-tight">
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
                    </div>
                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 {{ $gTone['badge'] }}">
                        @if (! empty($gSug['score'])) Score {{ $gSug['score'] }}
                        @elseif (! empty($gSug['profile_percent'])) {{ (int) $gSug['profile_percent'] }}%
                        @else {{ $gSug['label'] ?? '—' }}
                        @endif
                    </span>
                </div>
                @if (! empty($gSug['name']))
                    <p class="text-xs text-white/70 mt-2 truncate">{{ $gSug['name'] }}</p>
                @endif
                <p class="text-sm text-white/85 mt-3 leading-relaxed">{{ $gSug['summary'] ?? 'No guarantor signal yet.' }}</p>

                @php
                    $gAfford = $gSug['affordability'] ?? null;
                    $gAffordVerdict = strtolower((string) ($gAfford['verdict'] ?? ''));
                    $gExternalLoans = (int) ($gSug['existing_loans'] ?? 0);
                    $gOutstanding = (float) ($gSug['outstanding_balance'] ?? 0);
                @endphp
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-white/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">Affordability</p>
                        <p class="text-sm font-bold mt-0.5 uppercase">
                            @if ($gRec === 'pending_profile' || $gRec === 'missing' || $gRec === 'not_required')
                                —
                            @elseif ($gAffordVerdict === 'pass') Pass
                            @elseif ($gAffordVerdict === 'warn') Near limit
                            @elseif ($gAffordVerdict === 'fail') Fail
                            @else {{ $gAfford['status_label'] ?? '—' }}
                            @endif
                        </p>
                        <p class="text-[11px] text-white/75 mt-0.5 truncate">
                            {{ $gAfford['status_label'] ?? 'Capacity vs proposed EMI' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">Other institutions</p>
                        <p class="text-sm font-bold mt-0.5">
                            @if ($gRec === 'pending_profile' || $gRec === 'missing')
                                —
                            @else
                                {{ $gExternalLoans }} loan{{ $gExternalLoans === 1 ? '' : 's' }}
                            @endif
                        </p>
                        <p class="text-[11px] text-white/75 mt-0.5 truncate">
                            @if ($gOutstanding > 0)
                                Outst. {{ format_money($gOutstanding) }}
                            @elseif ($gRec === 'pending_profile' || $gRec === 'missing')
                                Finish profile for CRB
                            @else
                                No outstanding reported
                            @endif
                        </p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('admin.loan-applications.show', [
                            'loan_application' => $record,
                            'person' => 'guarantor',
                            'tab' => 'affordability',
                            'g' => $gSug['link_id'] ?? null,
                        ]) }}#borrower-file"
                       class="inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition">
                        Open guarantor file →
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if (! empty($anomalies))
        <details class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden group">
            <summary class="cursor-pointer list-none px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Decision guidance</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">
                        {{ count($anomalies) }} flag{{ count($anomalies) === 1 ? '' : 's' }} under the cards
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if (($anomalyCounts['critical'] ?? 0) > 0)
                        <span class="rounded-full bg-rose-100 text-rose-900 ring-1 ring-rose-200 px-2.5 py-1 text-[11px] font-bold">
                            {{ $anomalyCounts['critical'] }} critical
                        </span>
                    @endif
                    @if (($anomalyCounts['warning'] ?? 0) > 0)
                        <span class="rounded-full bg-amber-100 text-amber-950 ring-1 ring-amber-200 px-2.5 py-1 text-[11px] font-bold">
                            {{ $anomalyCounts['warning'] }} warning
                        </span>
                    @endif
                    @if (($anomalyCounts['info'] ?? 0) > 0)
                        <span class="rounded-full bg-sky-100 text-sky-950 ring-1 ring-sky-200 px-2.5 py-1 text-[11px] font-bold">
                            {{ $anomalyCounts['info'] }} info
                        </span>
                    @endif
                    <span class="text-[11px] text-gray-500 group-open:hidden">Tap to expand</span>
                    <span class="text-[11px] text-gray-500 hidden group-open:inline">Tap to collapse</span>
                </div>
            </summary>
            <ul class="divide-y divide-gray-100 border-t border-gray-100 max-h-72 overflow-y-auto">
                @foreach ($anomalies as $anomaly)
                    <li class="px-5 py-2.5 flex gap-3 {{ $anomalyTone[$anomaly['severity']] ?? 'bg-gray-50' }}">
                        <span class="mt-1.5 size-2 rounded-full shrink-0 {{ $anomalyDot[$anomaly['severity']] ?? 'bg-gray-400' }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $anomaly['title'] }}</p>
                            <p class="text-xs mt-0.5 opacity-80">{{ $anomaly['detail'] }}</p>
                        </div>
                        <span class="ml-auto shrink-0 text-[10px] uppercase tracking-wider font-semibold opacity-70">{{ $anomaly['severity'] }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- Clear jump to screening / committee decision (same pattern both stages) --}}
    @if ($isScreeningStage || $isCommitteeStage)
        <a href="#review-recommendation"
           class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-brand-gold px-5 py-4 text-brand shadow-sm ring-1 ring-brand/20 hover:brightness-95 transition">
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">
                    {{ $isCommitteeStage ? 'Credit committee' : 'Screening team' }}
                </p>
                <p class="text-sm font-bold mt-0.5">
                    {{ $isCommitteeStage
                        ? 'Validate screening or record a different decision with reasons'
                        : 'Record the screening decision — Approve / Reject / Counter (if enabled)' }}
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 text-sm font-bold shrink-0">
                {{ $isCommitteeStage ? 'Go to decision' : 'Go to decision' }}
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 5l5 5-5 5"/></svg>
            </span>
        </a>
    @endif

    {{-- Committee: dual CRB + screening recommendation --}}
    @if ($isCommitteeStage)
        @include('admin.loan-applications.review._committee_inputs')
    @endif

    @include('admin.loan-applications.review._review_desk')

    {{-- Primary decision zone — same placement for screening and committee --}}
    <div id="review-action-zone" class="scroll-mt-24">
        @include('admin.loan-applications.review._recommendation')
    </div>
</section>

@include('admin.loan-applications.review._borrower_file_tabs')

@include('admin.loan-applications.review._decision_sticky_bar')
