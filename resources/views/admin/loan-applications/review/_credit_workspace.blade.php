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
    $defaultTab = request('tab', 'personal');
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
    @endphp
    @if (! empty($anomalies))
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Decision guidance</p>
                    <h3 class="text-sm font-bold text-gray-900 mt-0.5">{{ count($anomalies) }} flag{{ count($anomalies) === 1 ? '' : 's' }} to review first</h3>
                </div>
                <p class="text-[11px] text-gray-500">System checks inspired by typical credit desk checklists — not a hard decline.</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($anomalies as $anomaly)
                    <li class="px-5 py-3 flex gap-3 {{ $anomalyTone[$anomaly['severity']] ?? 'bg-gray-50' }}">
                        <span class="mt-1.5 size-2 rounded-full shrink-0 {{ $anomalyDot[$anomaly['severity']] ?? 'bg-gray-400' }}"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $anomaly['title'] }}</p>
                            <p class="text-xs mt-0.5 opacity-80">{{ $anomaly['detail'] }}</p>
                        </div>
                        <span class="ml-auto shrink-0 text-[10px] uppercase tracking-wider font-semibold opacity-70">{{ $anomaly['severity'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

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

    {{-- Facility + risk + CRB suggestion --}}
    <div class="grid lg:grid-cols-12 gap-4">
        <div class="lg:col-span-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
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
        </div>

        <div class="lg:col-span-4 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $crbTone['card'] }} text-white">
            <div class="px-5 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">CRB suggestion</p>
                        <p class="text-2xl font-bold mt-1 uppercase tracking-tight">{{ $crbRec !== '' ? $crbRec : '—' }}</p>
                    </div>
                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 {{ $crbTone['badge'] }}">
                        Score {{ $crb['score'] ?? '—' }}
                    </span>
                </div>
                <p class="text-sm text-white/85 mt-3 leading-relaxed">{{ $crbExplain['summary'] ?? 'No CRB explanation available.' }}</p>
                @if (! empty($crbExplain['reasons']))
                    <ul class="mt-3 space-y-1 text-xs text-white/80">
                        @foreach (array_slice($crbExplain['reasons'], 0, 3) as $reason)
                            <li class="flex gap-2"><span class="opacity-60">•</span><span>{{ $reason }}</span></li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-white/10 px-2 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">Loans</p>
                        <p class="text-sm font-bold">{{ $crb['existing_loans'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-white/10 px-2 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">Delinq.</p>
                        <p class="text-sm font-bold">{{ $crb['delinquencies'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-white/10 px-2 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">Fresh</p>
                        <p class="text-sm font-bold truncate">{{ $crb['freshness_label'] ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Committee: dual CRB + screening recommendation --}}
    @if ($isCommitteeStage)
        @include('admin.loan-applications.review._committee_inputs')
    @endif

    {{-- Affordability (collapsed by default when pass) --}}
    @if (! empty($afford))
        <details class="group rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden" @if (! $affordPass || $affordWarn) open @endif>
            <summary class="cursor-pointer list-none px-5 py-4 flex flex-wrap items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                <div class="flex items-center gap-3 min-w-0">
                    <span @class([
                        'inline-flex text-xs font-bold rounded-full px-3 py-1',
                        'bg-emerald-100 text-emerald-800' => $affordPass && ! $affordWarn,
                        'bg-amber-100 text-amber-900' => $affordWarn,
                        'bg-rose-100 text-rose-800' => ! $affordPass && ! $affordWarn,
                    ])>
                        @if ($affordPass && ! $affordWarn) Affordability pass
                        @elseif ($affordWarn) Near limit
                        @else Affordability fail
                        @endif
                    </span>
                    <p class="text-sm text-gray-700 truncate">
                        Installment {{ format_money($afford['proposed_installment'] ?? $afford['new_emi'] ?? 0) }}
                        · capacity {{ format_money($afford['available_capacity'] ?? 0) }}
                    </p>
                </div>
                <span class="text-xs font-semibold text-brand flex items-center gap-1">
                    Details
                    <svg class="size-3.5 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </span>
            </summary>
            <div class="px-5 pb-5 border-t border-gray-100 pt-4">
                @include('admin.loan-applications.review._affordability-summary', [
                    'affordability' => $afford,
                    'counterOffer' => $counterOffer ?? null,
                    'embedded' => true,
                ])
            </div>
        </details>
    @endif

    {{-- CRB detail accordion --}}
    <details class="group rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <summary class="cursor-pointer list-none px-5 py-4 flex flex-wrap items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
            <div>
                <p class="text-sm font-semibold text-gray-900">CRB report details</p>
                <p class="text-xs text-gray-500 mt-0.5">Identity match, loan history, and bureau metadata</p>
            </div>
            <span class="text-xs font-semibold text-brand flex items-center gap-1">
                Expand
                <svg class="size-3.5 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </span>
        </summary>
        <div class="px-5 pb-5 border-t border-gray-100 pt-4">
            @include('admin.loan-applications.review._crb_body')
        </div>
    </details>

    {{-- Checklist chips --}}
    @if (! empty($review['checklist']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2">
            @foreach ($review['checklist'] as $item)
                @php
                    $tone = match ($item['tone'] ?? 'gray') {
                        'emerald' => 'bg-emerald-50 ring-emerald-200 text-emerald-900',
                        'amber'   => 'bg-amber-50 ring-amber-200 text-amber-950',
                        'red'     => 'bg-rose-50 ring-rose-200 text-rose-900',
                        default   => 'bg-white ring-gray-200 text-gray-700',
                    };
                @endphp
                <div class="rounded-xl ring-1 px-3.5 py-2.5 {{ $tone }}">
                    <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">{{ $item['label'] }}</p>
                    <p class="text-xs font-semibold mt-0.5">{{ $item['detail'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Recommendation / decision — primary CTA zone --}}
    <div id="review-action-zone" class="scroll-mt-24">
        @include('admin.loan-applications.review._recommendation')
    </div>
</section>

{{-- ── Profile file tabs ─────────────────────────────────────────── --}}
<section
    class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
    x-data="{ tab: @js($defaultTab) }"
>
    <div class="px-5 pt-5 pb-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Borrower file</p>
        <h3 class="text-base font-bold text-gray-900 mt-0.5">Profile sections</h3>
        <p class="text-xs text-gray-500 mt-0.5">Open one section at a time — keep the page short while you verify.</p>
    </div>

    @php
        $profileTabs = [
            ['personal', 'Personal'],
            ['residence', 'Residence'],
            ['activity', 'Activity'],
            ['documents', 'Documents'],
            ['guarantor', 'Guarantor'],
        ];
        if ($groupReview ?? null) {
            $profileTabs[] = ['group', 'Group'];
        }
    @endphp

    <div class="px-3 pt-3 flex gap-1.5 overflow-x-auto border-b border-gray-100" role="tablist">
        @foreach ($profileTabs as [$key, $label])
            <button type="button"
                    role="tab"
                    @click="tab = '{{ $key }}'"
                    :aria-selected="tab === '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'bg-brand text-white shadow-sm'
                        : 'bg-transparent text-gray-600 hover:bg-brand-muted/50'"
                    class="shrink-0 rounded-xl px-4 py-2.5 text-xs font-semibold transition">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="p-5">
        <div x-show="tab === 'personal'" x-cloak class="space-y-5">
            @include('admin.loan-applications.review._profile_personal')
        </div>
        <div x-show="tab === 'residence'" x-cloak>
            @include('admin.loan-applications.review._profile_residence')
        </div>
        <div x-show="tab === 'activity'" x-cloak>
            @include('admin.loan-applications.review._profile_activity')
        </div>
        <div x-show="tab === 'documents'" x-cloak class="space-y-5">
            @include('admin.loan-applications.review._documents')
            @include('admin.loan-applications.review._document-requests')
            @include('admin.loan-applications._asset-backed')
            @include('admin.loan-applications._asset-lending')
            @include('admin.loan-applications.review._asset')
        </div>
        <div x-show="tab === 'guarantor'" x-cloak>
            @include('admin.loan-applications.review._guarantors')
        </div>
        @if ($groupReview ?? null)
            <div x-show="tab === 'group'" x-cloak>
                @include('admin.loan-applications.review._group')
            </div>
        @endif
    </div>
</section>
