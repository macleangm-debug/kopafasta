@php
    $panel = is_array($panel ?? null) ? $panel : null;
    $record = $record ?? null;
@endphp

@if ($panel)
    <div data-gate3-conclusion class="rounded-xl ring-1 ring-brand/20 bg-white px-3.5 py-3 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $panel['title'] ?? 'GATE 3 · CRB & CREDIT HISTORY' }}</p>
                <p class="text-sm font-semibold text-slate-900 mt-0.5">
                    {{ $panel['participant_name'] ?? '—' }}
                    <span class="text-slate-500 font-medium">· {{ $panel['role'] ?? 'Participant' }}</span>
                </p>
            </div>
            @php $chip = (string) ($panel['chip'] ?? 'WAITING'); @endphp
            <span @class([
                'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1',
                'bg-emerald-50 text-emerald-900 ring-emerald-200' => $chip === 'PASSED',
                'bg-rose-50 text-rose-950 ring-rose-200' => $chip === 'FAILED',
                'bg-amber-50 text-amber-950 ring-amber-200' => $chip === 'REFER',
                'bg-slate-50 text-slate-700 ring-slate-200' => ! in_array($chip, ['PASSED', 'FAILED', 'REFER'], true),
            ])>
                @if ($chip === 'PASSED') ✓ GATE 3 PASSED
                @elseif ($chip === 'FAILED') ✕ GATE 3 FAILED
                @elseif ($chip === 'REFER') ! GATE 3 REFER
                @else GATE 3 WAITING
                @endif
            </span>
        </div>

        {{-- 1–2 Freshness + subject --}}
        <div class="grid gap-3 md:grid-cols-2">
            <div class="rounded-lg bg-slate-50 ring-1 ring-slate-100 px-3 py-2 space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">CRB report</p>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Report date</dt>
                        <dd class="font-semibold text-right">{{ $panel['freshness']['report_date'] ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Age</dt>
                        <dd class="font-semibold text-right">
                            @if ($panel['freshness']['age_days'] !== null)
                                {{ (int) $panel['freshness']['age_days'] }} {{ (int) $panel['freshness']['age_days'] === 1 ? 'day' : 'days' }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Valid for</dt>
                        <dd class="font-semibold text-right">{{ (int) ($panel['freshness']['valid_for_days'] ?? 90) }} days</dd>
                    </div>
                    @if (! empty($panel['freshness']['source']))
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Provider</dt>
                            <dd class="font-semibold text-right {{ ($panel['freshness']['provider_mode'] ?? '') === 'STUB' ? 'text-amber-800' : 'text-emerald-800' }}">{{ $panel['freshness']['source'] }}</dd>
                        </div>
                    @endif
                </dl>
                <p @class([
                    'text-sm font-bold',
                    'text-emerald-800' => ($panel['freshness']['status'] ?? '') === 'CURRENT',
                    'text-rose-800' => ($panel['freshness']['status'] ?? '') !== 'CURRENT',
                ])>{{ $panel['freshness']['label'] ?? '—' }}</p>
                @if (($panel['freshness']['status'] ?? '') !== 'CURRENT' && ! empty($panel['crb_href']))
                    <a href="{{ guided_evidence_url($panel['crb_href'], 'guided') }}"
                       class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-brand ring-1 ring-brand/25">
                        Obtain current CRB report
                    </a>
                @endif
            </div>

            <div class="rounded-lg bg-slate-50 ring-1 ring-slate-100 px-3 py-2 space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Is this CRB for this person?</p>
                <dl class="space-y-1.5 text-sm">
                    <div>
                        <dt class="text-slate-500">Profile</dt>
                        <dd class="font-semibold">{{ $panel['subject']['profile_name'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">CRB subject</dt>
                        <dd class="font-semibold">{{ $panel['subject']['crb_name'] ?? '—' }}</dd>
                    </div>
                </dl>
                <p @class([
                    'text-sm font-bold',
                    'text-emerald-800' => ! empty($panel['subject']['matches']),
                    'text-rose-800' => empty($panel['subject']['matches']),
                ])>{{ $panel['subject']['label'] ?? '—' }}</p>
            </div>
        </div>

        <div @class([
            'rounded-lg px-3 py-2 ring-1',
            'bg-rose-50 ring-rose-200 text-rose-950' => str_contains((string) ($panel['result_banner'] ?? ''), 'CRITICAL') || str_contains((string) ($panel['result_banner'] ?? ''), 'WAITING'),
            'bg-amber-50 ring-amber-200 text-amber-950' => str_contains((string) ($panel['result_banner'] ?? ''), 'NEEDS'),
            'bg-emerald-50 ring-emerald-200 text-emerald-950' => str_contains((string) ($panel['result_banner'] ?? ''), 'CLEAR'),
        ])>
            <p class="text-sm font-bold">{{ $panel['result_banner'] ?? '' }}</p>
        </div>

        {{-- Identity comparison — system findings, not Pass/Concern --}}
        @if (! empty($panel['comparisons']))
            <div class="space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Identity comparison</p>
                <ul class="space-y-2">
                    @foreach ($panel['comparisons'] as $row)
                        <li class="rounded-lg ring-1 ring-slate-200 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ $row['label'] ?? 'Field' }}</p>
                                <span @class([
                                    'text-xs font-bold',
                                    'text-emerald-800' => ($row['status'] ?? '') === 'MATCH',
                                    'text-rose-800' => ($row['status'] ?? '') === 'MISMATCH',
                                    'text-slate-600' => ! in_array(($row['status'] ?? ''), ['MATCH', 'MISMATCH'], true),
                                ])>
                                    @if (($row['status'] ?? '') === 'MATCH') ✓ MATCH
                                    @elseif (($row['status'] ?? '') === 'MISMATCH') ✕ MISMATCH
                                    @else —
                                    @endif
                                </span>
                            </div>
                            <dl class="mt-1 grid sm:grid-cols-2 gap-1 text-sm">
                                <div><dt class="text-[10px] uppercase text-slate-500">Profile</dt><dd class="font-semibold">{{ $row['profile'] ?? '—' }}</dd></div>
                                <div><dt class="text-[10px] uppercase text-slate-500">CRB</dt><dd class="font-semibold">{{ $row['crb'] ?? '—' }}</dd></div>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Credit snapshot --}}
        <div class="rounded-lg bg-slate-50 ring-1 ring-slate-100 px-3 py-2 space-y-2">
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Credit facilities / bureau</p>
            <dl class="grid sm:grid-cols-2 gap-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Recommendation</dt><dd class="font-bold">{{ $panel['credit']['recommendation'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Score</dt><dd class="font-semibold">{{ $panel['credit']['score'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Active loans</dt><dd class="font-semibold">{{ (int) ($panel['credit']['existing_loans'] ?? 0) }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Outstanding</dt><dd class="font-semibold">{{ format_money((float) ($panel['credit']['outstanding'] ?? 0)) }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Delinquencies</dt><dd class="font-semibold">{{ (int) ($panel['credit']['delinquencies'] ?? 0) }}</dd></div>
            </dl>
            @if (! empty($panel['credit']['loan_history']))
                <ul class="text-xs text-slate-700 space-y-1 border-t border-slate-200 pt-2">
                    @foreach ($panel['credit']['loan_history'] as $loan)
                        <li>{{ $loan['lender'] ?? 'Lender' }} · {{ $loan['status'] ?? '—' }} · {{ isset($loan['balance']) ? format_money($loan['balance']) : '—' }}</li>
                    @endforeach
                </ul>
            @endif
            @if (! empty($panel['crb_href']))
                <a href="{{ guided_evidence_url($panel['crb_href'], 'guided') }}"
                   class="inline-flex text-xs font-bold text-brand underline">Open full CRB evidence</a>
            @endif
        </div>

        {{-- Flags: automatic / human resolution / information --}}
        @if (! empty($panel['flags']))
            <div class="space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Findings</p>
                <ul class="space-y-2">
                    @foreach ($panel['flags'] as $row)
                        @php
                            $level = $row['level'] ?? 'information';
                            $tone = match ($level) {
                                'critical' => 'bg-red-50 text-red-950 ring-red-200',
                                'needs_review' => 'bg-amber-50 text-amber-950 ring-amber-200',
                                'resolved' => 'bg-emerald-50 text-emerald-950 ring-emerald-200',
                                default => 'bg-white text-slate-800 ring-slate-200',
                            };
                            $levelLabel = match ($level) {
                                'critical' => 'Critical',
                                'needs_review' => 'Needs review',
                                'resolved' => 'Resolved',
                                default => 'Information',
                            };
                        @endphp
                        <li class="rounded-lg ring-1 px-3 py-2 {{ $tone }}">
                            <p class="text-[10px] uppercase tracking-wide font-bold">{{ $levelLabel }} · {{ $row['classification'] ?? '' }}</p>
                            <p class="text-sm font-semibold mt-0.5">{{ $row['title'] ?? 'Finding' }}</p>
                            <p class="text-sm mt-0.5">{{ $row['detail'] ?? '' }}</p>

                            @if (is_array($row['waiver'] ?? null))
                                <p class="text-sm text-emerald-900 mt-2 font-semibold">
                                    Resolved ✓ {{ $row['waiver']['by_name'] ?? 'analyst' }}
                                    @if (! empty($row['waiver']['reason']))
                                        — “{{ $row['waiver']['reason'] }}”
                                    @endif
                                </p>
                            @elseif (! empty($row['hard']))
                                <p class="text-xs font-semibold text-rose-900 mt-2">Hard policy failure — cannot be accepted as a discrepancy.</p>
                            @elseif (! empty($panel['block_secondary']))
                                <p class="text-xs text-slate-700 mt-2">Resolve freshness and subject match before accepting secondary discrepancies.</p>
                            @elseif (! empty($row['reviewable']) && ! empty($panel['allow_accept']) && ! empty($panel['actor_can_accept']) && $record)
                                <form method="POST" action="{{ route('admin.loan-applications.discrepancy-waiver', $record) }}"
                                      class="mt-2 space-y-1.5" data-no-draft>
                                    @csrf
                                    <input type="hidden" name="code" value="{{ $row['code'] }}">
                                    <input type="hidden" name="detail" value="{{ $row['detail'] ?? '' }}">
                                    <input type="hidden" name="from" value="guided">
                                    <input type="hidden" name="review_person" value="{{ $panel['person'] ?? 'borrower' }}">
                                    @if (! empty($panel['m']))
                                        <input type="hidden" name="review_m" value="{{ $panel['m'] }}">
                                    @endif
                                    @if (! empty($panel['g']))
                                        <input type="hidden" name="review_g" value="{{ $panel['g'] }}">
                                    @endif
                                    <label class="block text-[11px] font-semibold text-gray-700">Why are you accepting this?</label>
                                    <textarea name="reason" required minlength="12" rows="2" maxlength="500"
                                              placeholder="Explain why this discrepancy is acceptable for this credit decision."
                                              class="w-full rounded-lg border-gray-300 text-xs ring-1 ring-gray-200 px-3 py-2"></textarea>
                                    <button type="submit" class="inline-flex text-xs font-semibold text-brand bg-white ring-1 ring-brand/20 px-3 py-1.5 rounded-lg">
                                        Accept discrepancy &amp; continue
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-1 border-t border-slate-100 pt-2">
            <p class="text-sm font-bold text-slate-900">Next</p>
            <p class="text-sm text-slate-800">{{ $panel['next_action'] ?? '' }}</p>
        </div>
    </div>
@endif
