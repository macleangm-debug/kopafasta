@php
    $customer = $review['customer'] ?? null;
    $product = $review['product'] ?? null;
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
    $groupReview = is_array($groupReview ?? null) ? $groupReview : [];
    $isGroupLoan = $groupReview !== [] && ! empty($groupReview['members']);
    $groupMembers = collect($groupReview['members'] ?? []);
    $groupScoring = $groupReview['scoring'] ?? null;
    $groupVerified = (int) ($groupReview['verified_count'] ?? $groupMembers->where('kyc_complete', true)->count());
    $groupMemberCount = (int) ($groupReview['member_count'] ?? $groupMembers->count());
    $groupTarget = (int) ($groupReview['target_member_count'] ?? $groupMemberCount);
    $fileIsClosed = $fileIsClosed ?? $record->isClosed();
    $closedStatus = $closedStatus ?? ($fileIsClosed ? $record->closedStatus() : null);
    $isServicingFile = $isServicingFile ?? $record->hasActiveFacility();
    $linkedLoan = $linkedLoan ?? $record->loan;

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

    // Deep-links: profile ?tab=… opens Profiles; review desk subject switches stay on Checklist.
    $workspace = request('workspace');
    $allowedWorkspaces = $isServicingFile
        ? ['facility', 'checklist', 'profiles']
        : ['checklist', 'profiles', 'decision'];
    if (! in_array($workspace, $allowedWorkspaces, true)) {
        if ($isServicingFile) {
            $workspace = 'facility';
        } elseif (request()->has('tab') || (request('person') === 'guarantor' && request()->filled('g'))) {
            $workspace = 'profiles';
        } else {
            $workspace = 'checklist';
        }
    }

    $workspaceUrl = function (string $key) use ($record) {
        $person = request('review_person', request('person', 'borrower'));
        if (! in_array($person, ['borrower', 'guarantor', 'member'], true)) {
            $person = 'borrower';
        }
        $g = request('review_g', request('g'));
        $m = request('review_m', request('m'));

        $params = array_filter([
            'loan_application' => $record,
            'workspace' => $key,
            'person' => $person,
            'tab' => $key === 'profiles' ? (request('tab') ?: 'personal') : null,
            'g' => $g,
            'm' => $m,
            'review_person' => $person,
            'review_g' => $g,
            'review_m' => $m,
        ], fn ($v) => $v !== null && $v !== '');

        return route('admin.loan-applications.show', $params).'#credit-workspace';
    };
@endphp

{{-- ── Decision deck ─────────────────────────────────────────────── --}}
<section id="credit-workspace" class="space-y-4 mb-6 scroll-mt-24">
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
        $screeningReadiness = ($isScreeningStage || $isCommitteeStage)
            ? app(\App\Services\ScreeningReadinessService::class)->forApplication(
                $record,
                $review,
                $groupReview ?? null,
                is_array($anomalies) ? $anomalies : $anomalies->all(),
                auth()->user(),
            )
            : null;
    @endphp

    @include('admin.loan-applications.review._submissions_inbox')

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">
                @if ($isServicingFile)
                    Credit management workspace
                    @if ($isGroupLoan)
                        · Group loan
                    @endif
                @elseif ($fileIsClosed)
                    Closed file
                    @if ($isGroupLoan)
                        · Group loan
                    @endif
                @else
                    {{ $isCommitteeStage ? 'Committee workspace' : 'Screening workspace' }}
                    @if ($isGroupLoan)
                        · Group loan
                    @endif
                @endif
            </p>
            <h2 class="text-lg font-bold text-gray-900 mt-0.5">
                @if ($isServicingFile)
                    {{ $linkedLoan && $linkedLoan->status === 'arrears' ? 'Loan in arrears' : ($linkedLoan && $linkedLoan->status === 'defaulted' ? 'Defaulted facility' : 'Active facility') }}
                @elseif ($fileIsClosed)
                    Application record
                @else
                    What you need to decide
                @endif
            </h2>
            <p class="text-sm text-gray-500 mt-0.5">
                @if ($isServicingFile)
                    Outstanding, repayments and collections on the same credit file used at screening.
                @elseif ($fileIsClosed)
                    This file is {{ display_label($closedStatus, 'application_status') }}. It is view-only — no edit, withdraw, or workflow actions.
                @else
                    {{ $isCommitteeStage
                        ? 'Sprint critical areas on the same evidence screening used, change anything that needs a reason, then record the committee decision.'
                        : ($isGroupLoan
                            ? 'Review the leader and each member on the checklist, then record your recommendation.'
                            : 'Review CRB, affordability and the borrower file — then submit your credit recommendation.') }}
                @endif
            </p>
        </div>
        @if (! $fileIsClosed && ($isScreeningStage || $isCommitteeStage))
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

    @if (is_array($screeningReadiness ?? null) && ! $fileIsClosed)
        @include('admin.loan-applications.review._screening_readiness', [
            'screeningReadiness' => $screeningReadiness,
        ])
    @endif

    {{-- Facility + risk + borrower/leader CRB + guarantor/roster --}}
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
        $showRosterCard = $isGroupLoan && in_array($gRec, ['not_required', ''], true);
    @endphp
    <div class="grid lg:grid-cols-12 gap-4">
        <div class="lg:col-span-3 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">
                    {{ $isServicingFile ? 'Outstanding' : 'Facility summary' }}
                </p>
                <p class="text-2xl font-bold mt-1 tabular-nums">
                    {{ format_money((float) ($isServicingFile ? ($linkedLoan->outstanding_balance ?? $record->offered_amount ?? $record->requested_amount) : $record->requested_amount)) }}
                </p>
                <p class="text-sm text-white/75 mt-1">
                    @if ($isServicingFile && $linkedLoan)
                        {{ $linkedLoan->tenure_months ?? $record->requested_tenure_months }} months
                    @else
                        {{ $record->requested_tenure_months }} months
                    @endif
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
                @if ($isGroupLoan)
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Members</p>
                        <p class="font-semibold text-gray-900 mt-0.5 tabular-nums">{{ $groupMemberCount }} / {{ $groupTarget }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Leader</p>
                        <p class="font-semibold text-gray-900 mt-0.5 truncate">{{ is_string($groupReview['leader'] ?? null) ? $groupReview['leader'] : ($customer?->full_name ?? '—') }}</p>
                    </div>
                    @if (($groupReview['amount_per_member'] ?? 0) > 0)
                        <div class="col-span-2">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500">Per member</p>
                            <p class="font-semibold text-gray-900 mt-0.5">{{ format_money((float) $groupReview['amount_per_member']) }}</p>
                        </div>
                    @endif
                @else
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Member</p>
                        <p class="font-semibold text-gray-900 mt-0.5 font-mono text-xs">{{ $customer?->member_no ?? '—' }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="lg:col-span-3 rounded-2xl ring-1 p-5 {{ $riskTone }} shadow-sm">
            @if ($isGroupLoan && $groupMembers->isNotEmpty())
                @php
                    $crbFeedback = app(\App\Services\CrbCreditCheckService::class);
                    $memberRiskSlides = $groupMembers->values()->map(function (array $m) use ($crbFeedback) {
                        $score = isset($m['crb_score']) && is_numeric($m['crb_score']) ? (int) $m['crb_score'] : null;
                        $band = $crbFeedback->scoreBandFeedback($score);

                        return [
                            'name' => (string) ($m['name'] ?? 'Member'),
                            'role' => ucfirst((string) ($m['role'] ?? 'member')),
                            'score' => $score,
                            'ready' => (bool) ($m['kyc_complete'] ?? false),
                            'status' => (string) ($m['status_label'] ?? ''),
                            'crb' => (string) ($m['crb_status'] ?? ''),
                            'amount' => (float) ($m['requested_amount'] ?? 0),
                            'band_label' => $band['label'],
                            'band_detail' => $band['detail'],
                            'band_rec' => strtoupper($band['recommendation']),
                            'band_tone' => $band['tone'],
                        ];
                    })->all();
                @endphp
                <div x-data="{ i: 0, slides: @js($memberRiskSlides) }" class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">Member risk</p>
                        <p class="text-[10px] font-bold tabular-nums opacity-70" x-text="(i + 1) + ' / ' + slides.length"></p>
                    </div>
                    <template x-if="slides[i]">
                        <div>
                            <p class="text-[11px] font-semibold opacity-80 truncate" x-text="slides[i].role + ' · ' + slides[i].name"></p>
                            <div class="flex items-end gap-1.5 mt-1">
                                <span class="text-4xl font-bold leading-none tabular-nums" x-text="slides[i].score ?? '—'"></span>
                                <span class="text-sm font-semibold pb-1 opacity-70">CRB</span>
                            </div>
                            <p class="text-sm font-bold mt-2">
                                <span x-text="slides[i].band_label"></span>
                                <span class="opacity-70"> · </span>
                                <span x-text="slides[i].band_rec"></span>
                            </p>
                            <p class="text-[11px] mt-1 leading-snug opacity-90" x-text="slides[i].band_detail"></p>
                            <p class="text-sm font-bold mt-2">
                                <span x-text="slides[i].ready ? 'Profile ready' : 'Profile incomplete'"></span>
                            </p>
                            <p class="text-xs mt-1 opacity-90 truncate" x-text="slides[i].status || slides[i].crb || '—'"></p>
                            <p class="text-[11px] mt-2 opacity-80" x-show="slides[i].amount > 0"
                               x-text="'Ask ' + new Intl.NumberFormat().format(slides[i].amount)"></p>
                        </div>
                    </template>
                    <div class="flex items-center justify-between gap-2 pt-1">
                        <button type="button" class="text-xs font-bold px-2.5 py-1.5 rounded-lg bg-white/60 ring-1 ring-current/20 disabled:opacity-40"
                                @click="i = Math.max(0, i - 1)" :disabled="i === 0">← Prev</button>
                        <div class="flex gap-1">
                            <template x-for="(slide, idx) in slides" :key="idx">
                                <button type="button" class="size-1.5 rounded-full"
                                        :class="idx === i ? 'bg-current' : 'bg-current/30'"
                                        @click="i = idx"></button>
                            </template>
                        </div>
                        <button type="button" class="text-xs font-bold px-2.5 py-1.5 rounded-lg bg-white/60 ring-1 ring-current/20 disabled:opacity-40"
                                @click="i = Math.min(slides.length - 1, i + 1)" :disabled="i >= slides.length - 1">Next →</button>
                    </div>
                    <p class="mt-1 text-[10px] opacity-70 leading-snug">
                        CRB bands: ≥650 approve · 500–649 refer · &lt;500 reject. One weak member can fail the group. App risk {{ $risk['score'] ?? '—' }}/100 · {{ strtoupper((string) ($risk['recommendation'] ?? '—')) }}.
                    </p>
                </div>
            @else
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
                    Includes borrower CRB and guarantor CRB/profile. Identity photos are compared on the checklist — not scored as a separate procedure.
                    ≥75 approve · ≥50 refer · below 50 reject.
                </p>
            @endif
        </div>

        <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 {{ $isGroupLoan && $groupMembers->isNotEmpty() ? '' : 'bg-gradient-to-br '.$crbTone['card'].' text-white' }}">
            @if ($isGroupLoan && $groupMembers->isNotEmpty())
                @php
                    $memberCrbSlides = $groupMembers->values()->map(function (array $m) {
                        $rec = strtolower((string) ($m['crb_recommendation'] ?? ''));
                        $tone = match ($rec) {
                            'approve' => 'from-emerald-600 to-emerald-800',
                            'refer' => 'from-amber-500 to-amber-700',
                            'reject' => 'from-rose-600 to-rose-800',
                            default => 'from-brand to-brand-light',
                        };

                        return [
                            'id' => (int) ($m['id'] ?? 0),
                            'name' => (string) ($m['name'] ?? 'Member'),
                            'role' => ucfirst((string) ($m['role'] ?? 'member')),
                            'rec' => $rec !== '' ? $rec : '—',
                            'score' => $m['crb_score'] ?? null,
                            'summary' => (string) ($m['crb_summary'] ?? 'No CRB explanation available.'),
                            'loans' => (int) ($m['crb_existing_loans'] ?? 0),
                            'outstanding' => (float) ($m['crb_outstanding'] ?? 0),
                            'delinq' => (int) ($m['crb_delinquencies'] ?? 0),
                            'status' => (string) ($m['crb_status'] ?? ''),
                            'tone' => $tone,
                        ];
                    })->all();
                    $leaderIdx = $groupMembers->values()->search(fn ($m) => strtolower((string) ($m['role'] ?? '')) === 'leader');
                    if ($leaderIdx === false) {
                        $leaderIdx = 0;
                    }
                @endphp
                <div x-data="{ i: {{ (int) $leaderIdx }}, slides: @js($memberCrbSlides) }" class="relative text-white">
                    <template x-for="(slide, idx) in slides" :key="idx">
                        <div x-show="i === idx" x-cloak
                             class="bg-gradient-to-br px-5 py-5"
                             :class="slide.tone">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Member CRB</p>
                                <p class="text-[10px] font-bold tabular-nums text-white/70" x-text="(i + 1) + ' / ' + slides.length"></p>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold text-white/85 truncate" x-text="slide.role + ' · ' + slide.name"></p>
                                    <p class="text-2xl font-bold mt-1 uppercase tracking-tight" x-text="slide.rec"></p>
                                </div>
                                <span class="inline-flex shrink-0 text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 bg-white/20 text-white"
                                      x-text="'Score ' + (slide.score ?? '—')"></span>
                            </div>
                            <p class="text-sm text-white/85 mt-3 leading-relaxed" x-text="slide.summary"></p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <div class="rounded-xl bg-white/10 px-3 py-2.5">
                                    <p class="text-[10px] uppercase tracking-wider text-white/60">Other institutions</p>
                                    <p class="text-sm font-bold mt-0.5" x-text="slide.loans + ' loan' + (slide.loans === 1 ? '' : 's')"></p>
                                    <p class="text-[11px] text-white/75 mt-0.5 truncate"
                                       x-text="slide.outstanding > 0 ? ('Outst. ' + new Intl.NumberFormat().format(slide.outstanding)) : 'No outstanding reported'"></p>
                                </div>
                                <div class="rounded-xl bg-white/10 px-3 py-2.5">
                                    <p class="text-[10px] uppercase tracking-wider text-white/60">Delinquencies</p>
                                    <p class="text-sm font-bold mt-0.5" x-text="slide.delinq"></p>
                                    <p class="text-[11px] text-white/75 mt-0.5 truncate" x-text="slide.status || '—'"></p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-4">
                                <button type="button" class="text-xs font-bold px-2.5 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 disabled:opacity-40"
                                        @click="i = Math.max(0, i - 1)" :disabled="i === 0">← Prev</button>
                                <div class="flex gap-1">
                                    <template x-for="(s, idx) in slides" :key="'dot-'+idx">
                                        <button type="button" class="size-1.5 rounded-full"
                                                :class="idx === i ? 'bg-white' : 'bg-white/35'"
                                                @click="i = idx"></button>
                                    </template>
                                </div>
                                <button type="button" class="text-xs font-bold px-2.5 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 disabled:opacity-40"
                                        @click="i = Math.min(slides.length - 1, i + 1)" :disabled="i >= slides.length - 1">Next →</button>
                            </div>
                            <a :href="'{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'checklist', 'review_person' => 'member']) }}&review_m=' + slide.id + '#checklist-documents'"
                               class="mt-3 inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition">
                                Member CRB on checklist →
                            </a>
                        </div>
                    </template>
                </div>
            @else
            <div class="bg-gradient-to-br {{ $crbTone['card'] }} text-white px-5 py-5">
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
                <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'checklist', 'review_person' => 'borrower']) }}#checklist-documents"
                   class="mt-3 inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition">
                    Review CRB on checklist →
                </a>
            </div>
            @endif
        </div>

        @if ($showRosterCard)
            <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br from-indigo-600 to-indigo-900 text-white">
                <div class="px-5 py-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Group roster</p>
                            <p class="text-2xl font-bold mt-1 tracking-tight tabular-nums">{{ $groupMemberCount }}/{{ $groupTarget }}</p>
                        </div>
                        <span class="inline-flex text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 bg-white/20 text-white">
                            {{ $groupVerified }} ready
                        </span>
                    </div>
                    <p class="text-sm text-white/85 mt-3 leading-relaxed">
                        {{ $groupVerified }} of {{ $groupMemberCount }} members have complete profiles for underwriting.
                    </p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-xl bg-white/10 px-3 py-2.5">
                            <p class="text-[10px] uppercase tracking-wider text-white/60">Completion</p>
                            <p class="text-sm font-bold mt-0.5">
                                {{ isset($groupScoring['member_completion_percent'])
                                    ? number_format((float) $groupScoring['member_completion_percent'], 0).'%'
                                    : ($groupMemberCount > 0 ? round(($groupVerified / $groupMemberCount) * 100).'%' : '—') }}
                            </p>
                            <p class="text-[11px] text-white/75 mt-0.5 truncate">Member readiness</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-3 py-2.5">
                            <p class="text-[10px] uppercase tracking-wider text-white/60">Avg credit</p>
                            <p class="text-sm font-bold mt-0.5">
                                {{ isset($groupScoring['average_credit_score']) ? number_format((float) $groupScoring['average_credit_score'], 0) : '—' }}
                            </p>
                            <p class="text-[11px] text-white/75 mt-0.5 truncate">Across checked members</p>
                        </div>
                    </div>
                    <a href="{{ $workspaceUrl('checklist') }}"
                       class="mt-3 inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition">
                        Review members on checklist →
                    </a>
                </div>
            </div>
        @else
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
                                'workspace' => 'profiles',
                                'person' => 'guarantor',
                                'tab' => 'personal',
                                'g' => $gSug['link_id'] ?? null,
                            ]) }}#borrower-file"
                           class="inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition">
                            Open guarantor file →
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Person switcher is for Checklist / Profiles only. --}}
    @if (! in_array($workspace, ['decision', 'facility'], true))
        @include('admin.loan-applications.review._workspace_person_switcher')
    @endif

    {{-- Top workspace tabs --}}
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <nav class="flex gap-1 overflow-x-auto px-2 pt-2 border-b border-gray-100" aria-label="{{ $isServicingFile ? 'Credit management workspace' : 'Screening workspace' }}">
            @foreach (($isServicingFile
                ? ['facility' => 'Facility', 'checklist' => 'Review checklist', 'profiles' => 'Profiles']
                : ['checklist' => 'Review checklist', 'profiles' => 'Profiles', 'decision' => 'Decision']
            ) as $key => $label)
                <a href="{{ $workspaceUrl($key) }}"
                   @class([
                       'shrink-0 px-4 py-3 text-sm font-semibold rounded-t-xl border-b-2 transition',
                       'border-brand text-brand bg-brand-muted/40' => $workspace === $key,
                       'border-transparent text-gray-600 hover:text-brand hover:bg-gray-50' => $workspace !== $key,
                   ])
                   @if ($workspace === $key) aria-current="page" @endif>
                    {{ $label }}
                    @if ($key === 'checklist' && ($anomalyCounts['critical'] ?? 0) + ($anomalyCounts['warning'] ?? 0) > 0)
                        <span class="ml-1.5 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-amber-100 text-amber-950 text-[10px] font-bold">
                            {{ ($anomalyCounts['critical'] ?? 0) + ($anomalyCounts['warning'] ?? 0) }}
                        </span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="p-4 sm:p-5 space-y-4">
            @if ($workspace === 'facility')
                @include('admin.loan-applications.review._facility_tab')
            @elseif ($workspace === 'checklist')
                @if (! empty($anomalies))
                    <details class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden group">
                        <summary class="cursor-pointer list-none px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Review flags</p>
                                <p class="text-sm font-bold text-gray-900 mt-0.5">
                                    {{ count($anomalies) }} flag{{ count($anomalies) === 1 ? '' : 's' }} for this checklist
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

                @include('admin.loan-applications.review._review_desk')
            @elseif ($workspace === 'profiles')
                @include('admin.loan-applications.review._borrower_file_tabs')
            @elseif ($fileIsClosed)
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-5 py-4">
                    <p class="text-sm font-semibold text-gray-900">No further actions on this file</p>
                    <p class="text-sm text-gray-500 mt-1">
                        Status is {{ display_label($closedStatus, 'application_status') }}. Use Checklist or Profiles to read the record.
                    </p>
                </div>
            @else
                @if ($isCommitteeStage)
                    @include('admin.loan-applications.review._committee_inputs')
                    @include('admin.loan-applications.review._committee_sprint', [
                        'screeningReadiness' => $screeningReadiness ?? null,
                        'documentRequests' => $documentRequests ?? ($review['document_requests'] ?? []),
                    ])
                @endif

                <div id="review-action-zone" class="scroll-mt-24">
                    @include('admin.loan-applications.review._recommendation')
                </div>
            @endif
        </div>
    </div>
</section>

@unless ($isServicingFile)
@include('admin.loan-applications.review._decision_sticky_bar', [
    'workspace' => $workspace,
    'screeningReadiness' => $screeningReadiness ?? null,
])
@endunless
