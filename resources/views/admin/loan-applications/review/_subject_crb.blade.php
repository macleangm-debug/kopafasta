@php
    $customer = $review['customer'] ?? null;
    $isGuarantor = (bool) ($review['is_guarantor_subject'] ?? false);
    $isMember = (bool) ($review['is_member_subject'] ?? false);
    $crb = $isGuarantor
        ? ($review['guarantor_row']['crb'] ?? [])
        : ($review['crb'] ?? []);
    $explain = $isGuarantor
        ? ($review['guarantor_row']['crb_explanation'] ?? app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb))
        : app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb);
    $rec = strtolower((string) ($crb['recommendation'] ?? ''));
    $personal = $crb['personal'] ?? [];
    $detail = $crb['credit_detail'] ?? [];
    $meta = $crb['report_meta'] ?? [];
    $history = collect($crb['loan_history'] ?? $detail['loan_history'] ?? []);
    $externalLoans = (int) ($crb['existing_loans'] ?? 0);
    $outstanding = (float) ($crb['outstanding_balance'] ?? 0);
    $openAccounts = collect($detail['open_accounts'] ?? []);
    $closedAccounts = collect($detail['closed_accounts'] ?? []);
    $spouses = collect($personal['spouses'] ?? []);
    $related = collect($personal['related_persons'] ?? []);
    $addressHistory = collect($personal['address_history'] ?? []);
    $contactHistory = collect($personal['contact_history'] ?? []);
    $employmentHistory = collect($personal['employment_history'] ?? []);
    $ids = collect($personal['ids'] ?? []);
    $inquiries = collect($detail['inquiries'] ?? []);
    $inquirySummary = collect($detail['inquiries_summary'] ?? []);
    $buckets = collect($detail['overdue_buckets'] ?? []);
    $exposureProduct = collect($detail['exposure_by_product'] ?? []);
    $overview = $detail['overview'] ?? [];
    $crossCheck = $isGuarantor
        ? ($review['guarantor_row']['crb_cross_check'] ?? null)
        : ($review['crb_cross_check'] ?? null);
    if (! is_array($crossCheck)) {
        $crossCheck = null;
    }

    $crbPerson = $crbPerson ?? (request('review_person') ?: (($isMember ?? false) ? 'member' : (($isGuarantor ?? false) ? 'guarantor' : 'borrower')));
    $crbM = $crbM ?? (request()->filled('review_m') ? request()->integer('review_m') : ($review['member_row']['id'] ?? null));
    $crbG = $crbG ?? (request()->filled('review_g') ? request()->integer('review_g') : ($review['guarantor_row']['link_id'] ?? null));
    $evidenceCtx = app(\App\Services\GuidedEvidenceContext::class);
    $fromWizard = $evidenceCtx->from($record);
    $exceptionService = app(\App\Services\ScreeningExceptionService::class);
    $recLabel = match ($rec) {
        'refer' => 'Referred for manual review',
        'approve' => 'Approve',
        'reject' => 'Reject',
        '', '—' => 'Not provided',
        default => ucfirst($rec),
    };
    $scoreDisplay = filled($crb['score'] ?? null) && $crb['score'] !== '—' ? $crb['score'] : 'Not provided';
    $needsManualReview = $rec === 'refer' || $rec === 'reject';

    $kv = function (?string $label, mixed $value) {
        $display = filled($value) || $value === 0 || $value === '0' ? $value : '—';
        return [$label, $display];
    };
@endphp

<section class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden {{ $fromWizard ? 'pb-28' : '' }}"
         x-data="{ crbTab: 'summary', crbConcern: false }">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $isGuarantor ? 'Guarantor' : ($isMember ? 'Member' : 'Borrower') }} · CRB</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">
                    {{ $needsManualReview ? 'Manual CRB review required' : 'Credit bureau report' }}
                </h2>
                @if ($needsManualReview)
                    <p class="text-sm text-slate-700 mt-1">
                        Why: CRB recommended {{ $rec === 'reject' ? 'rejection' : 'referral' }}.
                    </p>
                @else
                    <p class="text-xs text-gray-500 mt-0.5">View-only bureau data. Pass / Concern is recorded on the Review Checklist.</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-2 min-w-[9rem]">
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">CRB recommendation</p>
                    <p @class([
                        'text-sm font-bold mt-0.5',
                        'text-amber-800' => $rec === 'refer',
                        'text-rose-800' => $rec === 'reject',
                        'text-emerald-800' => $rec === 'approve',
                        'text-slate-800' => ! in_array($rec, ['refer', 'reject', 'approve'], true),
                    ])>{{ $rec === 'refer' ? 'Status: REFER' : $recLabel }}</p>
                    @if ($rec === 'refer')
                        <p class="text-[11px] text-slate-600 mt-0.5">The bureau referred this record for human review. This is information, not an action.</p>
                    @endif
                </div>
                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-2 min-w-[7rem]">
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">CRB score</p>
                    <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $scoreDisplay }}</p>
                </div>
            </div>
        </div>
        @if ($fromWizard)
            <p class="text-xs">
                <a href="{{ $evidenceCtx->backUrl($record) }}" class="font-bold text-brand underline">← {{ $evidenceCtx->backLabel($record) }}</a>
            </p>
        @endif
    </div>

    @php
        $identityFlags = collect(is_array($crossCheck) ? ($crossCheck['identity_flags'] ?? []) : []);
        $creditFlags = collect(is_array($crossCheck) ? ($crossCheck['credit_flags'] ?? []) : []);
        $allFlags = $identityFlags->merge($creditFlags)->values();
        $matches = collect(is_array($crossCheck) ? ($crossCheck['matches'] ?? []) : []);
        $subjectKey = match ($crbPerson) {
            'member' => 'member:'.(int) $crbM,
            'guarantor' => 'guarantor:'.(int) $crbG,
            default => 'borrower',
        };
        $nameItem = data_get($record->screening_payload, 'screening_checklist.by_subject.'.$subjectKey.'.items.identity.name_vs_crb', []);
        $nameCode = (string) ($nameItem['fail_reason_code'] ?? '');
        if ($rec === 'refer' && ! $allFlags->contains(fn ($flag) => ($flag['code'] ?? '') === 'crb_refer')) {
            $allFlags->prepend([
                'code' => 'crb_refer',
                'severity' => 'warning',
                'title' => 'CRB recommends referral',
                'detail' => 'The bureau referred this record for human review.',
            ]);
        }
        if (in_array($nameCode, ['crb_name_unusable', 'crb_no_record'], true)
            && ! $allFlags->contains(fn ($flag) => ($flag['code'] ?? '') === $nameCode)) {
            $allFlags->prepend([
                'code' => $nameCode,
                'severity' => 'warning',
                'title' => $nameCode === 'crb_no_record' ? 'No CRB record' : 'CRB name not usable',
                'detail' => $nameItem['fail_reason_label'] ?? 'CRB returned a record without a usable name.',
            ]);
        }
        $flagRows = $allFlags->map(function ($flag) use ($exceptionService, $record, $crbPerson, $crbM, $crbG) {
            $code = (string) ($flag['code'] ?? '');
            $waiver = $code !== '' ? $exceptionService->waiverFor($record, $code, $crbPerson, $crbM ? (int) $crbM : null, $crbG ? (int) $crbG : null) : null;
            $level = $exceptionService->flagLevel($flag, is_array($waiver));

            return [
                'flag' => $flag,
                'code' => $code,
                'waiver' => $waiver,
                'level' => $level,
                'reviewable' => $exceptionService->isReviewableCode($code),
            ];
        });
        $openReviewable = $flagRows->first(fn ($row) => $row['reviewable'] && $row['level'] !== 'resolved');
        $openCount = $flagRows->filter(fn ($row) => in_array($row['level'], ['critical', 'needs_review'], true))->count();
        $criticalOpen = $flagRows->where('level', 'critical')->count();
        $needsReviewOpen = $flagRows->where('level', 'needs_review')->count();
        $infoOpen = $flagRows->where('level', 'information')->count();
        $resolvedCount = $flagRows->where('level', 'resolved')->count();
        $panelTone = $criticalOpen > 0
            ? 'ring-red-200 bg-red-50/60'
            : ($needsReviewOpen > 0 ? 'ring-amber-200 bg-amber-50/50' : 'ring-slate-200 bg-slate-50');
        $ctxPeek = $evidenceCtx->peek($record) ?? [];
        $openItem = (string) ($ctxPeek['item'] ?? request('open_item') ?? ($openReviewable['code'] ?? null ? $exceptionService->itemKeyForCode($openReviewable['code'], $crbPerson) : 'identity.name_vs_crb'));
        $openGroup = explode('.', $openItem)[0] ?? 'identity';
        $openShort = explode('.', $openItem)[1] ?? 'name_vs_crb';
        $concernReasons = config('screening_checklist.identity.items.'.$openShort.'.fail_reasons')
            ?? config('screening_checklist.identity.items.name_vs_crb.fail_reasons')
            ?? ['custom' => 'Other (write reason)'];
    @endphp

    <div class="px-5 py-4 space-y-4 border-b border-gray-100">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Other active institutions</p>
                <p class="text-xl font-bold text-slate-900 mt-1">{{ $externalLoans }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Outstanding</p>
                <p class="text-xl font-bold text-slate-900 mt-1">{{ format_money($outstanding) }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Delinquencies</p>
                <p class="text-xl font-bold text-slate-900 mt-1">{{ $crb['delinquencies'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">CRB score</p>
                <p class="text-xl font-bold text-slate-900 mt-1">{{ $scoreDisplay }}</p>
            </div>
        </div>

        @if ($openCount > 0)
            <p class="text-sm font-bold text-slate-900">{{ $openCount }} {{ \Illuminate\Support\Str::plural('item', $openCount) }} require{{ $openCount === 1 ? 's' : '' }} your review</p>
        @elseif ($resolvedCount > 0 && $flagRows->isNotEmpty())
            <p class="text-sm font-semibold text-emerald-800">Review items on this report are resolved.</p>
        @endif

        @if ($flagRows->isNotEmpty() || $matches->isNotEmpty())
            <div class="rounded-xl ring-1 {{ $panelTone }} px-4 py-4 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-600 font-semibold">Profile vs CRB</p>
                        <p class="text-sm font-semibold text-slate-900 mt-0.5">Items on this bureau report</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        @if ($criticalOpen > 0)
                            <span class="rounded-full px-2.5 py-1 bg-red-100 text-red-900 font-semibold">{{ $criticalOpen }} critical</span>
                        @endif
                        @if ($needsReviewOpen > 0)
                            <span class="rounded-full px-2.5 py-1 bg-amber-100 text-amber-900 font-semibold">{{ $needsReviewOpen }} needs review</span>
                        @endif
                        @if ($infoOpen > 0)
                            <span class="rounded-full px-2.5 py-1 bg-slate-200 text-slate-800 font-semibold">{{ $infoOpen }} information</span>
                        @endif
                        @if ($resolvedCount > 0)
                            <span class="rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-900 font-semibold">{{ $resolvedCount }} resolved</span>
                        @endif
                    </div>
                </div>
                <ul class="space-y-2">
                    @foreach ($flagRows as $row)
                        @php
                            $flag = $row['flag'];
                            $flagCode = $row['code'];
                            $waiver = $row['waiver'];
                            $level = $row['level'];
                            $levelLabel = match ($level) {
                                'critical' => 'Critical',
                                'needs_review' => 'Needs review',
                                'resolved' => 'Resolved',
                                default => 'Information',
                            };
                            $tone = match ($level) {
                                'critical' => 'bg-red-100 text-red-950 ring-red-200',
                                'needs_review' => 'bg-amber-50 text-amber-950 ring-amber-200',
                                'resolved' => 'bg-white text-slate-700 ring-slate-200',
                                default => 'bg-white text-slate-800 ring-slate-200',
                            };
                        @endphp
                        <li class="rounded-lg ring-1 px-3 py-2 {{ $tone }}">
                            <p class="text-xs font-bold uppercase tracking-wide">{{ $levelLabel }} · {{ $flag['title'] ?? 'Flag' }}</p>
                            <p class="text-sm mt-0.5">{{ $flag['detail'] ?? '' }}</p>
                            @if (is_array($waiver))
                                <div class="mt-2 text-sm text-emerald-900">
                                    <p class="font-semibold">Resolved ✓ Accepted by {{ $waiver['by_name'] ?? 'analyst' }}</p>
                                    <p class="text-xs text-slate-600 mt-0.5">
                                        {{ format_app_datetime($waiver['at'] ?? null, 'd M Y') }}
                                        @if (! empty($waiver['at']))
                                            · {{ format_app_datetime($waiver['at'], 'g:i A') }}
                                        @endif
                                    </p>
                                    @if (! empty($waiver['reason']))
                                        <p class="text-sm text-slate-700 mt-1">“{{ $waiver['reason'] }}”</p>
                                    @endif
                                </div>
                            @elseif ($row['reviewable'] && auth()->user()?->hasPermission('applications.review'))
                                <form method="POST" action="{{ route('admin.loan-applications.discrepancy-waiver', $record) }}" class="mt-2 space-y-1.5" data-no-draft
                                      @if ($openReviewable && ($openReviewable['code'] ?? '') === $flagCode) id="crb-accept-form" @endif>
                                    @csrf
                                    <input type="hidden" name="code" value="{{ $flagCode }}">
                                    <input type="hidden" name="detail" value="{{ $flag['detail'] ?? '' }}">
                                    @if ($fromWizard)
                                        <input type="hidden" name="from" value="{{ $fromWizard }}">
                                    @endif
                                    <input type="hidden" name="review_person" value="{{ $crbPerson }}">
                                    @if ($crbM)
                                        <input type="hidden" name="review_m" value="{{ $crbM }}">
                                    @endif
                                    @if ($crbG)
                                        <input type="hidden" name="review_g" value="{{ $crbG }}">
                                    @endif
                                    <input type="hidden" name="open_item" value="{{ $openItem }}">
                                    <label class="block text-[11px] font-semibold text-gray-700">Why are you accepting this?</label>
                                    <textarea name="reason" required minlength="12" rows="2" maxlength="500"
                                              placeholder="e.g. CRB does not contain spouse information and other identity information is consistent."
                                              class="w-full rounded-lg border-gray-300 text-xs ring-1 ring-gray-200 px-3 py-2"></textarea>
                                    <button type="submit" class="inline-flex text-xs font-semibold text-brand bg-white ring-1 ring-brand/20 px-3 py-1.5 rounded-lg">
                                        {{ $fromWizard ? 'Accept discrepancy & continue' : 'Accept discrepancy' }}
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($matches->isNotEmpty())
                    <details class="rounded-lg bg-white/80 ring-1 ring-emerald-200 px-3 py-2">
                        <summary class="cursor-pointer text-xs font-semibold text-emerald-900">{{ $matches->count() }} field(s) matched profile</summary>
                        <ul class="mt-2 grid sm:grid-cols-2 gap-2 text-xs text-gray-700">
                            @foreach ($matches as $match)
                                <li><span class="font-semibold">{{ $match['label'] ?? $match['code'] }}:</span> {{ $match['profile'] ?? '—' }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">What happens next</p>
            <p class="text-sm text-slate-800 mt-1">
                Review the CRB information above. If the discrepancy is acceptable, continue Screening. If something concerns you, record the concern and Kopafasta will guide you through the required next action.
            </p>
        </div>
    </div>

    <div class="px-5 pt-4 flex flex-wrap gap-1.5 border-b border-gray-100 pb-3">
        @foreach ([
            'summary' => 'Summary',
            'identity' => 'Identity',
            'credit' => 'Credit behaviour',
            'accounts' => 'Accounts',
        ] as $tabKey => $tabLabel)
            <button type="button"
                    @click="crbTab = @js($tabKey)"
                    :class="crbTab === @js($tabKey)
                        ? 'bg-brand text-white ring-brand'
                        : 'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40'"
                    class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ring-1 transition">
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    <div class="p-5 space-y-8">
        <div x-show="crbTab === 'summary'" class="space-y-8">
        <p class="text-sm text-gray-700">{{ $explain['summary'] ?? 'No CRB explanation available.' }}</p>
        @if (! empty($crb['freshness_label']))
            <p class="text-xs text-slate-500">Report freshness: {{ $crb['freshness_label'] }}</p>
        @endif
        </div>{{-- /summary --}}

        {{-- Personal / identity --}}
        <div x-show="crbTab === 'identity'" x-cloak class="space-y-8">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Personal &amp; identity</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                @foreach ([
                    $kv('Full name', $personal['full_name'] ?? $crb['identity']['full_name'] ?? null),
                    $kv('First name', $personal['first_name'] ?? null),
                    $kv('Middle names', $personal['middle_names'] ?? null),
                    $kv('Surname', $personal['surname'] ?? null),
                    $kv('Gender', $personal['gender'] ?? $crb['identity']['gender'] ?? null),
                    $kv('Date of birth', $personal['date_of_birth'] ?? $crb['identity']['date_of_birth'] ?? null),
                    $kv('Nationality', $personal['nationality'] ?? null),
                    $kv('Country of birth', $personal['country_of_birth'] ?? null),
                    $kv('District of birth', $personal['district_of_birth'] ?? null),
                    $kv('Marital status', $personal['marital_status'] ?? null),
                    $kv('Number of spouses', $personal['number_of_spouses'] ?? null),
                    $kv('Number of children', $personal['number_of_children'] ?? null),
                    $kv('Education', $personal['education'] ?? null),
                    $kv('Profession', $personal['profession'] ?? null),
                    $kv('Employer', $personal['employer'] ?? null),
                    $kv('Mobile', $personal['mobile'] ?? null),
                    $kv('Current address', $personal['address'] ?? null),
                    $kv('NIDA (profile)', $crb['identity']['national_id'] ?? null),
                    $kv('Search score', $crb['search_score'] ?? $meta['search_score'] ?? null),
                    $kv('CRB RUID', $crb['crb_ruid'] ?? $meta['ruid'] ?? null),
                    $kv('CIR number', $meta['cir_number'] ?? null),
                ] as [$label, $value])
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ $label }}</p>
                        <p class="font-medium text-gray-900 mt-0.5 break-words">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            @if ($spouses->isNotEmpty() || $related->isNotEmpty())
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-700 mb-2">Spouse &amp; related persons</p>
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($spouses as $spouse)
                            <li class="px-4 py-2.5 flex justify-between gap-3">
                                <span class="font-medium text-gray-900">{{ $spouse['name'] ?? '—' }}</span>
                                <span class="text-xs text-gray-500 uppercase">Spouse</span>
                            </li>
                        @endforeach
                        @foreach ($related as $person)
                            <li class="px-4 py-2.5 flex justify-between gap-3">
                                <span class="font-medium text-gray-900">{{ $person['name'] ?? '—' }}</span>
                                <span class="text-xs text-gray-500">{{ $person['relation'] ?? 'Related' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($ids->isNotEmpty())
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-700 mb-2">IDs on file</p>
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($ids as $id)
                            <li class="px-4 py-2.5 flex flex-wrap justify-between gap-2">
                                <span class="font-mono text-xs text-gray-900">{{ $id['id_number'] ?? '—' }}</span>
                                <span class="text-xs text-gray-500">{{ $id['id_type'] ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Address / contact / employment history --}}
        <div class="grid lg:grid-cols-3 gap-4">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Address history</h3>
                @if ($addressHistory->isEmpty())
                    <p class="text-sm text-gray-500">No address history on this pull.</p>
                @else
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($addressHistory as $row)
                            <li class="px-3 py-2.5">
                                <p class="font-medium text-gray-900">{{ $row['address'] ?? '—' }}</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $row['type'] ?? '—' }} · {{ $row['date_reported'] ?? '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Contact history</h3>
                @if ($contactHistory->isEmpty())
                    <p class="text-sm text-gray-500">No contact history on this pull.</p>
                @else
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($contactHistory as $row)
                            <li class="px-3 py-2.5">
                                <p class="font-medium text-gray-900">{{ $row['detail'] ?? '—' }}</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $row['type'] ?? '—' }} · {{ $row['date_reported'] ?? '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Employment history</h3>
                @if ($employmentHistory->isEmpty())
                    <p class="text-sm text-gray-500">No employment history on this pull.</p>
                @else
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($employmentHistory as $row)
                            <li class="px-3 py-2.5">
                                <p class="font-medium text-gray-900">{{ $row['employer'] ?? '—' }}</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $row['profession'] ?? '—' }} · {{ $row['date_reported'] ?? '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        </div>{{-- /identity --}}

        {{-- Credit behaviour overview --}}
        <div x-show="crbTab === 'credit'" x-cloak class="space-y-8">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Credit behaviour overview</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-4">
                @foreach ([
                    $kv('Accounts', $overview['accounts'] ?? $externalLoans),
                    $kv('Creditors', $overview['creditors'] ?? null),
                    $kv('Collateral count', $overview['collateral_count'] ?? null),
                    $kv('Most negative status', $overview['most_negative_status'] ?? $detail['most_negative_status'] ?? null),
                    $kv('Unpaid instal. 30d', $overview['unpaid_instal_30'] ?? null),
                    $kv('Unpaid instal. 60d', $overview['unpaid_instal_60'] ?? null),
                    $kv('Unpaid instal. 360d', $overview['unpaid_instal_360'] ?? null),
                    $kv('Loans guaranteed', $overview['loans_guaranteed'] ?? null),
                    $kv('Legal dispute accounts', $overview['legal_dispute_accounts'] ?? null),
                    $kv('Inquiries (FA)', $overview['inquiries_by_fa'] ?? null),
                ] as [$label, $value])
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ $label }}</p>
                        <p class="font-medium text-gray-900 mt-0.5">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            @if ($buckets->isNotEmpty())
                <p class="text-xs font-semibold text-gray-700 mb-2">Overdue aging buckets</p>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 mb-4">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Bucket</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($buckets as $bucket)
                                <tr>
                                    <td class="px-3 py-2">{{ $bucket['bucket'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($bucket['amount'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($exposureProduct->isNotEmpty())
                <p class="text-xs font-semibold text-gray-700 mb-2">Exposure by product</p>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Product</th>
                                <th class="px-3 py-2 text-left">Currency</th>
                                <th class="px-3 py-2 text-right">Not overdue</th>
                                <th class="px-3 py-2 text-right">Overdue</th>
                                <th class="px-3 py-2 text-right">Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($exposureProduct as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['product'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $row['currency'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($row['not_overdue'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($row['amount_overdue'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $row['active_facilities'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        </div>{{-- /credit --}}

        {{-- Open / closed facilities --}}
        <div x-show="crbTab === 'accounts'" x-cloak class="space-y-8">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Open accounts</h3>
            @if ($openAccounts->isEmpty())
                <p class="text-sm text-gray-500 mb-4">No open accounts on this pull.</p>
            @else
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 mb-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Lender</th>
                                <th class="px-3 py-2 text-left">Product</th>
                                <th class="px-3 py-2 text-right">Approved</th>
                                <th class="px-3 py-2 text-right">Outstanding</th>
                                <th class="px-3 py-2 text-right">Overdue</th>
                                <th class="px-3 py-2 text-right">Instalment</th>
                                <th class="px-3 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($openAccounts as $acc)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $acc['lender'] ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <p>{{ $acc['product'] ?? '—' }}</p>
                                        @if (! empty($acc['purpose']))
                                            <p class="text-[11px] text-gray-500">{{ $acc['purpose'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($acc['approval_amount'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ format_money((float) ($acc['outstanding'] ?? $acc['balance'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($acc['overdue'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($acc['installment_amount'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $acc['negative_status'] ?? 'open' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Closed accounts</h3>
            @if ($closedAccounts->isEmpty())
                <p class="text-sm text-gray-500">No closed accounts on this pull.</p>
            @else
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Lender</th>
                                <th class="px-3 py-2 text-left">Product</th>
                                <th class="px-3 py-2 text-right">Sanctioned</th>
                                <th class="px-3 py-2 text-left">Activated</th>
                                <th class="px-3 py-2 text-left">Closed</th>
                                <th class="px-3 py-2 text-left">Phase</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($closedAccounts as $acc)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $acc['lender'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $acc['product'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($acc['sanction_amount'] ?? 0)) }}</td>
                                    <td class="px-3 py-2">{{ $acc['activated_date'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $acc['closure_date'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $acc['phase'] ?? $acc['loan_status'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Inquiries --}}
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Inquiry history</h3>
            @if ($inquirySummary->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach ($inquirySummary as $row)
                        <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-gray-100 text-gray-700">
                            {{ $row['institution_type'] ?? 'Institution' }}: {{ $row['count'] ?? 0 }}
                        </span>
                    @endforeach
                </div>
            @endif
            @if ($inquiries->isEmpty())
                <p class="text-sm text-gray-500">No inquiry details on this pull.</p>
            @else
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Purpose</th>
                                <th class="px-3 py-2 text-left">Institution type</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($inquiries as $inq)
                                <tr>
                                    <td class="px-3 py-2">{{ $inq['date'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $inq['purpose'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $inq['institution_type'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        @if (($inq['amount'] ?? 0) > 0)
                                            {{ format_money((float) $inq['amount']) }} {{ $inq['currency'] ?? '' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Compact loan history fallback --}}
        @if ($history->isNotEmpty() && $openAccounts->isEmpty())
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Loans at other institutions</h3>
                <ul class="divide-y divide-gray-100 rounded-xl ring-1 ring-gray-200 overflow-hidden bg-white">
                    @foreach ($history as $row)
                        <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $row['lender'] ?? $row['institution'] ?? 'Other lender' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 capitalize">{{ $row['status'] ?? '—' }} @if(!empty($row['product'])) · {{ $row['product'] }} @endif</p>
                            </div>
                            <p class="font-semibold text-gray-900">{{ format_money((float) ($row['balance'] ?? $row['outstanding'] ?? 0)) }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        </div>{{-- /accounts --}}
    </div>

    @if ($fromWizard)
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pointer-events-none">
            <div class="max-w-3xl mx-auto space-y-2 pointer-events-auto">
                <div x-show="crbConcern" x-cloak class="rounded-xl bg-white ring-1 ring-slate-200 px-3 py-3 space-y-2">
                    <form method="POST" action="{{ route('admin.loan-applications.guided-screening.save', $record) }}" class="space-y-2" data-no-draft>
                        @csrf
                        <input type="hidden" name="person" value="{{ $crbPerson }}">
                        @if ($crbM)
                            <input type="hidden" name="m" value="{{ $crbM }}">
                        @endif
                        @if ($crbG)
                            <input type="hidden" name="g" value="{{ $crbG }}">
                        @endif
                        <input type="hidden" name="open_item" value="{{ $openItem }}">
                        <input type="hidden" name="items[{{ $openGroup }}][{{ $openShort }}][verdict]" value="fail">
                        <label class="block text-xs font-bold text-slate-700">What is the concern?</label>
                        <select name="items[{{ $openGroup }}][{{ $openShort }}][fail_reason_code]" required class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">Select a reason</option>
                            @foreach ($concernReasons as $code => $label)
                                <option value="{{ $code }}">{{ is_string($label) ? $label : $code }}</option>
                            @endforeach
                        </select>
                        <textarea name="items[{{ $openGroup }}][{{ $openShort }}][fail_reason_custom]" rows="2" maxlength="500"
                                  placeholder="Short explanation"
                                  class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                        <div class="flex gap-2">
                            <button type="button" @click="crbConcern = false" class="flex-1 rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-2">Cancel</button>
                            <button type="submit" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-2">Save concern</button>
                        </div>
                    </form>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 items-stretch">
                    <a href="{{ $evidenceCtx->backUrl($record) }}"
                       class="flex-1 min-w-0 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3 px-2 leading-snug">{{ $evidenceCtx->backLabel($record) }}</a>
                    <button type="button" @click="crbConcern = true"
                            class="flex-1 min-w-0 rounded-xl bg-white ring-1 ring-rose-200 text-rose-900 font-bold text-sm py-3 px-2 leading-snug">Record concern</button>
                    @if ($openReviewable)
                        <button type="submit" form="crb-accept-form"
                                class="flex-[2] min-w-0 rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug">Accept &amp; continue</button>
                    @else
                        <a href="{{ $evidenceCtx->backUrl($record) }}"
                           class="flex-[2] min-w-0 text-center rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug">Continue Review →</a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</section>
