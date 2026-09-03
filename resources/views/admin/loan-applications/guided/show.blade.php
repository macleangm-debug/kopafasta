@php
    $step = $guided['step'] ?? [];
    $type = $step['type'] ?? 'human';
    $participant = $step['participant'] ?? $guided['participant'] ?? [];
    $contact = $step['contact'] ?? null;
    $outcomes = $step['outcomes'] ?? [];
    $failReasons = $step['fail_reasons'] ?? [];
    $record = $record ?? $guided['application'] ?? null;
    $gateIndex = (int) ($guided['gate_index'] ?? $step['gate_index'] ?? 1);
    $personIndex = (int) ($participant['index'] ?? 1);
    $personTotal = (int) ($participant['total'] ?? 1);
    $personChip = null;
    if (($participant['name'] ?? $participant['label'] ?? null) && $personTotal > 1) {
        $personChip = ($participant['name'] ?? $participant['label']).' · '.($participant['label'] ?? 'Participant').' '.$personIndex.' of '.$personTotal;
    }
    $evidenceReturn = [
        'open_item' => $step['item_key'] ?? null,
        'review_person' => $participant['person'] ?? null,
        'review_m' => $participant['m'] ?? null,
        'review_g' => $participant['g'] ?? null,
    ];
    $idPhoto = collect($step['evidence']['photos'] ?? [])
        ->first(fn ($p) => ($p['role'] ?? '') === 'id' && filled($p['url'] ?? null))
        ?? collect($step['evidence']['photos'] ?? [])->first(fn ($p) => filled($p['url'] ?? null));
    $idEvidenceUrl = is_array($idPhoto) ? ($idPhoto['url'] ?? null) : null;
    $nationalIdMissing = is_array($contact) && (
        ! empty($contact['national_id_missing'])
        || (! filled($contact['national_id'] ?? $contact['detail'] ?? null) && ! $idEvidenceUrl)
    );
    $queuedRequests = ($record->relationLoaded('documentRequests')
        ? $record->documentRequests
        : $record->documentRequests()->orderByDesc('id')->get()
    )->filter(fn ($row) => $row->isQueued())->values();
    $queuedDueDays = app(\App\Services\UnderwritingSettingsService::class)->documentRequestDefaultDueDays();
@endphp

<x-admin.guided-review-shell
    :record="$record"
    mode="screening"
    :percent="$guided['percent'] ?? 0"
    :gateChip="'Gate '.$gateIndex.' of 6'.(! empty($step['gate_label']) ? ' · '.$step['gate_label'] : '')"
    :personChip="$personChip"
    :gateProgress="$guided['gate_progress']['label'] ?? null"
    :backUrl="$guided['desk_href'] ?? route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'overview'])"
    backLabel="Back to Screening">

        @if (count($guided['subjects'] ?? []) > 1)
            <div class="mt-4 flex gap-2 overflow-x-auto pb-1" data-participant-switcher>
                @foreach ($guided['subjects'] as $subject)
                    @php
                        $current = ($participant['person'] ?? null) === ($subject['person'] ?? null)
                            && (int) ($participant['m'] ?? 0) === (int) ($subject['m'] ?? 0)
                            && (int) ($participant['g'] ?? 0) === (int) ($subject['g'] ?? 0);
                    @endphp
                    <a href="{{ $subject['href'] }}"
                       @class([
                           'shrink-0 max-w-[10rem] truncate rounded-full px-3 py-1.5 text-xs font-bold ring-1',
                           'bg-brand text-white ring-brand' => $current,
                           'bg-white text-slate-800 ring-slate-200' => ! $current,
                       ])>{{ $subject['label'] ?: 'Participant' }}</a>
                @endforeach
            </div>
        @endif

        @if (! in_array($type, ['human', 'request', 'attention'], true))
            <h2 class="text-lg font-bold text-slate-900 mt-5 break-words">{{ $step['title'] ?? $guided['cta'] }}</h2>
        @endif

        @if (! empty($guided['what_happens_next']))
            <div class="mt-3 rounded-2xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">What happens next</p>
                <p class="text-sm text-slate-800 mt-1">{{ $guided['what_happens_next'] }}</p>
            </div>
        @endif

        @if (! empty($guided['recommended']['detail']))
            <div class="mt-3 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800">{{ $guided['recommended']['label'] ?? 'Required next step' }}</p>
                <p class="text-sm text-amber-950 mt-1">{{ $guided['recommended']['detail'] }}</p>
            </div>
        @endif

        @if ($queuedRequests->isNotEmpty())
            <div class="mt-3 rounded-2xl bg-white ring-1 ring-brand/20 px-4 py-4 space-y-3" x-data="{ step: 'review' }">
                <p class="text-[11px] font-bold uppercase tracking-wide text-brand">Send together</p>
                <p class="text-base font-bold text-slate-900">{{ $queuedRequests->count() }} {{ $queuedRequests->count() === 1 ? 'request' : 'requests' }} ready — one {{ $queuedDueDays }}-day deadline</p>
                <ul class="text-sm text-slate-700 space-y-1">
                    @foreach ($queuedRequests as $queued)
                        <li>{{ $queued->label }}</li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}" class="space-y-2" data-no-draft>
                    @csrf
                    <input type="hidden" name="dispatch_queued" value="1">
                    <input type="hidden" name="type" value="document">
                    <input type="hidden" name="return_workspace" value="guided">
                    <button type="button" x-show="step === 'review'" @click="step = 'confirm'"
                            class="w-full rounded-xl bg-brand text-white font-bold text-sm py-2.5 hover:bg-brand-light">
                        Review & send all
                    </button>
                    <div x-show="step === 'confirm'" x-cloak class="space-y-2">
                        <p class="text-sm text-slate-800">We will notify the borrower{{ $record->loanGroup ? ' and group leader' : '' }} of all {{ $queuedRequests->count() }} items with the same {{ $queuedDueDays }}-day deadline. Screening pauses until they respond.</p>
                        <div class="flex gap-2">
                            <button type="button" @click="step = 'review'" class="flex-1 rounded-xl bg-white ring-1 ring-brand/30 text-brand font-bold text-sm py-2.5">Go back</button>
                            <button type="submit" name="confirmed" value="1" data-loading-label="Sending…" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-2.5">Send all requests</button>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        @if ($type === 'resolution')
            <div class="mt-6 rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3">
                <p class="text-sm text-slate-700">{{ $step['prompt'] }}</p>
                <ul class="space-y-2">
                    @foreach ($step['participants'] ?? [] as $row)
                        <li class="flex items-center justify-between text-sm">
                            <span class="font-semibold text-slate-900">{{ $row['name'] }}</span>
                            <span class="{{ ! empty($row['pass']) ? 'text-emerald-800' : 'text-rose-800' }} font-bold">
                                {{ ! empty($row['pass']) ? 'Pass' : 'Not met' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if (! empty($step['customer_reason']))
                    <p class="text-sm text-slate-600">Customer-facing reason: {{ $step['customer_reason'] }}</p>
                @endif
                <p class="text-sm font-semibold text-amber-950">Screening paused after this request is sent.</p>

                @if (($step['kind'] ?? '') === 'guarantor' && ! empty($step['guarantor_link_id']))
                    <form method="POST" action="{{ route('admin.loan-applications.guarantor-change', [$record, $step['guarantor_link_id']]) }}"
                          class="space-y-3" x-data="{ step: 'review' }" data-no-draft>
                        @csrf
                        <input type="hidden" name="return_workspace" value="guided">
                        <input type="hidden" name="notes" value="{{ $step['customer_reason'] }}">
                        <button type="button" x-show="step === 'review'" @click="step = 'confirm'"
                                class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3">Review & confirm</button>
                        <div x-show="step === 'confirm'" x-cloak class="space-y-2">
                            <p class="text-sm font-semibold text-slate-900">Ask the borrower to replace this guarantor and pause Screening?</p>
                            <div class="flex gap-2">
                                <button type="button" @click="step = 'review'" class="flex-1 rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3">Go back</button>
                                <button type="submit" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-3">Replace guarantor & pause</button>
                            </div>
                        </div>
                    </form>
                @elseif (($step['kind'] ?? '') === 'group')
                    <div class="space-y-3">
                        @if (! empty($step['allow_continue']))
                            <form method="POST" action="{{ route('admin.loan-applications.continue-with-eligible-members', $record) }}"
                                  class="space-y-2" x-data="{ step: 'review' }" data-no-draft>
                                @csrf
                                <input type="hidden" name="return_workspace" value="guided">
                                <input type="hidden" name="reason" value="Continue with {{ $step['eligible'] }} eligible members. Failed members remain on the file historically.">
                                <button type="button" x-show="step === 'review'" @click="step = 'confirm'"
                                        class="w-full rounded-xl bg-white ring-1 ring-brand/30 text-brand font-bold text-sm py-3">
                                    {{ $step['continue_cta'] ?? 'Continue with eligible members' }}
                                </button>
                                <div x-show="step === 'confirm'" x-cloak class="space-y-2">
                                    <p class="text-sm">Continue with {{ $step['eligible'] }} eligible members? Failed members stay on the file. This application is not restarted.</p>
                                    <div class="flex gap-2">
                                        <button type="button" @click="step = 'review'" class="flex-1 rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3">Go back</button>
                                        <button type="submit" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-3">Confirm continue</button>
                                    </div>
                                </div>
                            </form>
                        @endif
                        @if (! empty($step['member_ids']))
                            <form method="POST" action="{{ route('admin.loan-applications.guided-member-replacements', $record) }}"
                                  class="space-y-2" x-data="{ step: 'review' }" data-no-draft>
                                @csrf
                                @foreach ($step['member_ids'] as $mid)
                                    <input type="hidden" name="member_ids[]" value="{{ $mid }}">
                                @endforeach
                                <input type="hidden" name="reason" value="{{ $step['customer_reason'] }}">
                                <button type="button" x-show="step === 'review'" @click="step = 'confirm'"
                                        class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3">{{ $step['replace_cta'] ?? 'Replace member' }}</button>
                                <div x-show="step === 'confirm'" x-cloak class="space-y-2">
                                    <p class="text-sm">Notify the group leader to replace or add members, then pause Screening?</p>
                                    <div class="flex gap-2">
                                        <button type="button" @click="step = 'review'" class="flex-1 rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3">Go back</button>
                                        <button type="submit" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-3">Request replacement & pause</button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        @elseif ($type === 'clarification')
            <div class="mt-6 rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3">
                <p class="text-sm text-slate-800">{{ $step['prompt'] }}</p>
                <form method="POST" action="{{ route('admin.loan-applications.guided-screening.save', $record) }}"
                      id="guided-screening-form" class="space-y-3" data-no-draft>
                    @csrf
                    <textarea name="committee_clarification_response" required minlength="8" rows="4"
                              class="w-full rounded-xl border-slate-300 text-sm"
                              placeholder="Record the clarification for Committee"></textarea>
                </form>
            </div>
        @elseif ($type === 'return_to_committee')
            <div class="mt-6 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-5 space-y-2">
                <p class="text-sm font-bold text-emerald-950">Clarification recorded</p>
                <p class="text-sm text-emerald-900">{{ $step['response'] }}</p>
                <form method="POST" action="{{ route('admin.loan-applications.guided-screening.return-to-committee', $record) }}" data-no-draft>
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3">Return to Committee</button>
                </form>
            </div>
        @elseif ($type === 'waiting')
            <div class="mt-6 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-5 space-y-2">
                <p class="text-sm font-bold text-emerald-800">Request sent ✓</p>
                <p class="text-sm font-bold text-amber-950">{{ $step['title'] }}</p>
                <p class="text-sm text-amber-900">{{ $step['prompt'] }}</p>
                <p class="text-xs text-amber-800">You can leave this file. It will return to Do now when the resolution arrives.</p>
            </div>
        @elseif ($type === 'collateral_secure')
            <div class="mt-6 rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3" x-data="{ step: 'review' }">
                <p class="text-sm text-slate-700">{{ $step['prompt'] }}</p>
                <form method="POST" action="{{ route('admin.loan-applications.request-collateral-secure', $record) }}" class="space-y-3" data-no-draft>
                    @csrf
                    <input type="hidden" name="return_workspace" value="guided">
                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Internal note (optional)</span>
                        <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></textarea>
                    </label>
                    <button type="button" x-show="step === 'review'" @click="step = 'confirm'"
                            class="w-full rounded-xl bg-brand text-white font-bold text-sm py-2.5 hover:bg-brand-light">
                        {{ $step['primary'] ?? 'Review & request collateral' }}
                    </button>
                    <div x-show="step === 'confirm'" x-cloak class="space-y-2">
                        <p class="text-sm text-slate-800">We will notify the {{ $record->loanGroup ? 'group leader' : 'borrower' }} on their loan profile to pledge collateral. Screening pauses until they respond. A valuer is not assigned yet.</p>
                        <div class="flex gap-2">
                            <button type="button" @click="step = 'review'" class="flex-1 rounded-xl bg-white ring-1 ring-brand/30 text-brand font-bold text-sm py-2.5">Go back</button>
                            <button type="submit" name="confirmed" value="1" data-loading-label="Sending…" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-2.5">Send collateral request</button>
                        </div>
                    </div>
                </form>
            </div>
        @elseif ($type === 'gate_1')
            <div class="mt-6 rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3">
                <p class="text-sm text-slate-700">{{ $step['prompt'] }}</p>
                <ul class="space-y-2">
                    @foreach ($step['participants'] ?? [] as $row)
                        <li class="flex items-center justify-between text-sm">
                            <span class="font-semibold text-slate-900">{{ $row['name'] }}</span>
                            <span class="{{ ! empty($row['pass']) ? 'text-emerald-800' : 'text-rose-800' }} font-bold">
                                {{ ! empty($row['pass']) ? 'Pass' : 'Not met' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($type === 'decision')
            <div class="mt-6 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-5">
                <p class="text-sm font-bold text-emerald-950">{{ $step['title'] }}</p>
                <p class="text-sm text-emerald-900 mt-1">{{ $step['prompt'] }}</p>
            </div>
        @elseif ($type === 'gate_complete')
            <div class="mt-6 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-5">
                <p class="text-sm font-bold text-amber-950">{{ $step['title'] }}</p>
                <p class="text-sm text-amber-900 mt-1">{{ $step['prompt'] }}</p>
            </div>
        @elseif ($type === 'attention')
            <div class="mt-2 space-y-4">
                @include('admin.loan-applications.guided._participant_header', [
                    'participant' => $participant,
                    'contact' => $contact,
                    'idEvidenceUrl' => $idEvidenceUrl,
                    'nationalIdMissing' => $nationalIdMissing,
                    'evidenceReturn' => $evidenceReturn,
                    'step' => $step,
                ])
                <h2 class="text-lg font-bold text-slate-900 break-words">{{ $step['title'] ?? $guided['cta'] }}</h2>
                <p class="text-sm text-slate-800">{{ $step['prompt'] }}</p>
                @include('admin.loan-applications.guided._evidence', ['step' => $step])
                @include('admin.loan-applications.guided._inline_request', [
                    'step' => $step,
                    'record' => $record,
                    'nationalIdMissing' => $nationalIdMissing,
                ])
                @include('admin.loan-applications.guided._request_history', ['step' => $step, 'record' => $record])
                @if (! empty($step['destination']['href'])
                    && ! $nationalIdMissing
                    && ! $idEvidenceUrl
                    && ! str_contains((string) ($step['destination']['cta'] ?? ''), 'not provided'))
                    <a href="{{ guided_evidence_url($step['destination']['href'], 'guided', $evidenceReturn) }}" class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-brand ring-1 ring-brand/25">
                        {{ $step['destination']['cta'] ?? 'View details' }}
                    </a>
                @endif
                <form method="POST" action="{{ route('admin.loan-applications.guided-screening.save', $record) }}"
                      id="guided-screening-form" data-no-draft>
                    @csrf
                    <input type="hidden" name="continue_past" value="{{ $step['item_key'] }}">
                    <input type="hidden" name="person" value="{{ $participant['person'] ?? 'borrower' }}">
                    @if (! empty($participant['m']))
                        <input type="hidden" name="m" value="{{ $participant['m'] }}">
                    @endif
                    @if (! empty($participant['g']))
                        <input type="hidden" name="g" value="{{ $participant['g'] }}">
                    @endif
                </form>
            </div>
        @else
            <div class="mt-2 space-y-4">
                @include('admin.loan-applications.guided._participant_header', [
                    'participant' => $participant,
                    'contact' => $contact,
                    'idEvidenceUrl' => $idEvidenceUrl,
                    'nationalIdMissing' => $nationalIdMissing,
                    'evidenceReturn' => $evidenceReturn,
                    'step' => $step,
                ])
                <h2 class="text-lg font-bold text-slate-900 break-words">{{ $step['title'] ?? $guided['cta'] }}</h2>
                @if (! empty($step['revisiting']) && filled($step['verdict'] ?? null))
                    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Already recorded</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">
                            {{ ($step['verdict'] ?? '') === 'pass' ? 'Pass' : (($step['verdict'] ?? '') === 'na' ? 'N/A' : 'Concern') }}
                            — this answer is kept. Continue does not write it again.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.loan-applications.guided-screening.save', $record) }}"
                          id="guided-screening-form" data-no-draft>
                        @csrf
                        <input type="hidden" name="continue_past" value="{{ $step['item_key'] }}">
                        <input type="hidden" name="person" value="{{ $participant['person'] ?? 'borrower' }}">
                        @if (! empty($participant['m']))
                            <input type="hidden" name="m" value="{{ $participant['m'] }}">
                        @endif
                        @if (! empty($participant['g']))
                            <input type="hidden" name="g" value="{{ $participant['g'] }}">
                        @endif
                    </form>
                @endif
                <p class="text-sm text-slate-800">{{ $step['prompt'] }}</p>
                @if (! empty($step['why']))
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Why this matters</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $step['why'] }}</p>
                    </div>
                @endif
                @include('admin.loan-applications.guided._evidence', ['step' => $step])
                @if ($nationalIdMissing && str_starts_with((string) ($step['item_key'] ?? ''), 'identity.'))
                    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                        <p class="text-sm font-bold text-amber-950">National ID not provided</p>
                        <p class="text-xs text-amber-900 mt-1">Request National ID from this member. Do not open an empty ID record.</p>
                    </div>
                @elseif (! empty($step['destination']['href'])
                    && ! $nationalIdMissing
                    && ! $idEvidenceUrl
                    && ! str_contains((string) ($step['destination']['cta'] ?? ''), 'not provided'))
                    <a href="{{ guided_evidence_url($step['destination']['href'], 'guided', $evidenceReturn) }}" class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-brand ring-1 ring-brand/25">
                        {{ $step['destination']['cta'] ?? 'Open evidence' }}
                    </a>
                @endif

                @include('admin.loan-applications.guided._inline_request', [
                    'step' => $step,
                    'record' => $record,
                    'nationalIdMissing' => $nationalIdMissing,
                ])
                @include('admin.loan-applications.guided._request_history', ['step' => $step, 'record' => $record])

                @if (empty($step['revisiting']) || blank($step['verdict'] ?? null))
                    <form method="POST" action="{{ route('admin.loan-applications.guided-screening.save', $record) }}"
                          id="guided-screening-form" class="space-y-3" data-no-draft
                          @submit="if (!verdict) { $event.preventDefault(); missing = true }">
                        @csrf
                        <input type="hidden" name="person" value="{{ $participant['person'] ?? 'borrower' }}">
                        <input type="hidden" name="gate" value="{{ $step['gate'] ?? '' }}">
                        <input type="hidden" name="open_item" value="{{ $step['item_key'] ?? '' }}">
                        @if (! empty($participant['m']))
                            <input type="hidden" name="m" value="{{ $participant['m'] }}">
                        @endif
                        @if (! empty($participant['g']))
                            <input type="hidden" name="g" value="{{ $participant['g'] }}">
                        @endif
                        @php
                            $fieldBase = 'items['.($step['group_key'] ?? 'identity').']['.($step['item_short'] ?? '').']';
                        @endphp
                        <div class="grid grid-cols-1 gap-2">
                            @foreach ($outcomes as $outcome)
                                <label class="flex items-center gap-3 rounded-xl ring-1 ring-slate-200 px-4 py-3">
                                    <input type="radio" name="{{ $fieldBase }}[verdict]" value="{{ $outcome['value'] }}"
                                           x-model="verdict"
                                           required
                                           @change="missing = false; @if (! empty($outcome['fail_reason_code'])) reason = '{{ $outcome['fail_reason_code'] }}'; @endif"
                                           class="text-brand">
                                    <span class="text-sm font-bold text-slate-900">{{ $outcome['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <input type="hidden" name="{{ $fieldBase }}[fail_reason_code]" :value="verdict === 'fail' ? reason : ''">
                        <div x-show="verdict === 'fail'" x-cloak class="space-y-2">
                            <label class="text-xs font-bold text-slate-600">What is the concern?</label>
                            <select x-model="reason" :required="verdict === 'fail'" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="">Select a reason</option>
                                @foreach ($failReasons as $code => $label)
                                    <option value="{{ $code }}">{{ is_string($label) ? $label : $code }}</option>
                                @endforeach
                            </select>
                            <textarea x-show="reason === 'custom'" x-cloak
                                      name="{{ $fieldBase }}[fail_reason_custom]" rows="2"
                                      :required="verdict === 'fail' && reason === 'custom'"
                                      placeholder="Describe the concern"
                                      class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                            <p class="text-xs text-slate-500">After you save, the Review Checklist decides whether to continue, request evidence, or pause.</p>
                        </div>
                        <textarea name="{{ $fieldBase }}[notes]" rows="2" placeholder="Optional note"
                                  class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                    </form>
                @endif
            </div>
        @endif

        <p class="mt-2">
            <a href="{{ $guided['checklist_href'] }}" class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-brand ring-1 ring-brand/25">Review Checklist</a>
        </p>

    <x-slot:footer>
    <div class="flex gap-2 items-stretch">
            @if (! empty($guided['prev_href']))
                <a href="{{ $guided['prev_href'] }}"
                   class="flex-1 min-w-0 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Back</a>
            @else
                <a href="{{ $guided['desk_href'] ?? route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'overview']) }}"
                   class="flex-1 min-w-0 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Back to Screening</a>
            @endif
            @if (($step['type'] ?? '') === 'decision')
                <a href="{{ $guided['href'] }}" class="flex-[2] min-w-0 text-center rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">
                    Continue to Decision
                </a>
            @elseif (($step['type'] ?? '') === 'gate_complete')
                <a href="{{ $guided['checklist_href'] }}" class="flex-[2] min-w-0 text-center rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">
                    {{ $step['primary'] ?? 'Review Checklist' }}
                </a>
            @elseif (($step['type'] ?? '') === 'waiting' || ! empty($step['parked']))
                <span class="flex-[2] min-w-0 text-center rounded-xl bg-slate-100 text-slate-500 font-bold text-sm py-3 px-2 leading-snug whitespace-normal cursor-not-allowed">
                    Waiting
                </span>
            @elseif (in_array($step['type'] ?? '', ['resolution', 'return_to_committee', 'collateral_secure'], true))
                <span class="flex-[2] min-w-0 text-center rounded-xl bg-slate-100 text-slate-500 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Confirm in the card</span>
            @elseif (($step['type'] ?? '') === 'gate_1' && ! empty($step['all_pass']))
                <form method="POST" action="{{ route('admin.loan-applications.guided-screening.save', $record) }}" class="flex-[2] min-w-0" data-no-draft>
                    @csrf
                    <input type="hidden" name="ack_gate" value="declared">
                    <button type="submit" class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Continue to<br class="sm:hidden"> Verified Income</button>
                </form>
            @elseif (! empty($step['revisiting']) && filled($step['verdict'] ?? null))
                <button type="submit" form="guided-screening-form"
                        class="flex-[2] min-w-0 rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">
                    Continue
                </button>
            @elseif (($step['type'] ?? '') === 'attention')
                <button type="submit" form="guided-screening-form" data-loading-label="Saving…"
                        class="flex-[2] min-w-0 rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">
                    Continue reviewing
                </button>
            @else
                <div class="flex-[2] min-w-0">
                    <p x-show="missing && !verdict" x-cloak class="text-[11px] font-semibold text-rose-700 mb-1 text-center">Select Pass, Concern, or N/A</p>
                    <button type="submit" form="guided-screening-form" data-loading-label="Saving…"
                            class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">
                        Save & Next
                    </button>
                </div>
            @endif
    </div>
    </x-slot:footer>
</x-admin.guided-review-shell>
