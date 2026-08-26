            <div class="flex flex-wrap gap-3">
                @foreach ($availableActions as $action)
                    @if ($action['key'] === 'reject')
                        @php
                            $screeningReasonCode = $record->screening_rejection_reason_code
                                ?: data_get($record->screening_payload, 'recommendation_meta.preferred_rejection_reason_code');
                            $screeningReasonLabel = $screeningReasonCode
                                ? app(\App\Services\LoanRejectionReasonService::class)->labelForCode($screeningReasonCode)
                                : null;
                            $adviceOptions = $rejectionAdviceOptions
                                ?? app(\App\Services\LoanRejectionReasonService::class)->adviceOptions();
                            $screeningReasonCodes = old('rejection_reason_codes', filled($screeningReasonCode) ? [$screeningReasonCode] : []);
                        @endphp
                        <button type="button"
                                data-open-dialog="reject-application-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-4 py-2.5 rounded-lg ring-1 ring-red-200 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="reject-application-{{ $record->id }}"
                                class="rounded-2xl shadow-2xl ring-1 ring-brand/15 w-full max-w-xl p-0 backdrop:bg-brand/40 open:flex open:flex-col"
                                x-data="{
                                    advice: '{{ old('rejection_advice_code', '') }}',
                                    useScreening() {
                                        const code = '{{ $screeningReasonCode }}';
                                        if (!code) return;
                                        const box = document.querySelector('#reject-application-{{ $record->id }} input[name=\"rejection_reason_codes[]\"][value=\"'+code+'\"]');
                                        if (box) box.checked = true;
                                    }
                                }">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="max-h-[90vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" value="reject">

                                <div class="px-6 pt-6 pb-5 bg-gradient-to-br from-rose-700 via-rose-600 to-brand text-white">
                                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-rose-100">{{ ($record->current_stage ?? '') === 'awaiting_management' ? 'Credit management' : 'Credit committee' }}</p>
                                    <h4 class="text-xl font-bold mt-1">Reject application</h4>
                                    <p class="text-sm text-white/75 mt-1.5">
                                        Select every reason that applies. The borrower sees reasons and advice in their language.
                                    </p>
                                </div>

                                <div class="p-6 space-y-5">
                                    @if ($screeningReasonCode && $screeningReasonLabel)
                                        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Screening reason</p>
                                                <p class="text-sm text-amber-950 font-medium mt-0.5">{{ $screeningReasonLabel }}</p>
                                            </div>
                                            <button type="button" @click="useScreening()"
                                                    class="shrink-0 text-xs font-semibold text-amber-900 bg-white hover:bg-amber-100 ring-1 ring-amber-200 px-3 py-1.5 rounded-lg">
                                                Include this reason
                                            </button>
                                        </div>
                                    @endif

                                    @include('admin.loan-applications.review._rejection_fields', [
                                        'selectedRejectionCodes' => $screeningReasonCodes,
                                        'fallbackRejectionCode' => $screeningReasonCode,
                                        'adviceOptions' => $adviceOptions,
                                    ])

                                    @include('admin.loan-applications.review._committee_divergence_fields', ['committeeDiffers' => true])
                                </div>

                                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/80 flex flex-wrap items-center justify-end gap-2">
                                    <button type="button"
                                            data-close-dialog="reject-application-{{ $record->id }}"
                                            class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-xl">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="inline-flex items-center justify-center min-w-[10rem] bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm">
                                        Confirm reject
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'submit_recommendation')
                        @php
                            $affordPass = (bool) ($affordability['pass'] ?? false);
                            $maxCounter = (float) ($counterOffer['amount'] ?? 0);
                            $uwSettings = app(\App\Services\UnderwritingSettingsService::class);
                            $counterEnabled = $uwSettings->counterOffersEnabled();
                            $autoReject = $uwSettings->automaticRejectionEnabled();
                            $checklistReject = app(\App\Services\ScreeningChecklistService::class)->suggestedRejection($record);
                            $openReject = request()->boolean('open_reject');
                            $checklistRejectCodes = old(
                                'rejection_reason_codes',
                                session('checklist_reject_codes', $checklistReject['codes'] ?? [])
                            );
                            if (! is_array($checklistRejectCodes)) {
                                $checklistRejectCodes = filled($checklistRejectCodes) ? [(string) $checklistRejectCodes] : [];
                            }
                            $checklistRejectNotes = old(
                                'remarks',
                                session('checklist_reject_notes', $checklistReject['summary'] ?? '')
                            );
                            $oldDecision = old('recommendation_type', '');
                            if (old('action') === 'reject' || $openReject) {
                                $oldDecision = 'reject';
                            }
                            $crbRecLabel = strtoupper((string) ($review['crb']['recommendation'] ?? data_get($review, 'crb.recommendation', '—')));
                            $adviceOptions = $rejectionAdviceOptions
                                ?? app(\App\Services\LoanRejectionReasonService::class)->adviceOptions();
                            $docBlockers = app(\App\Services\LoanApplicationWorkflowService::class)
                                ->screeningDocumentBlockers($record);
                            $docsUrl = route('admin.loan-applications.show', array_filter([
                                'loan_application' => $record,
                                'workspace' => 'checklist',
                                'desk_phase' => 'capacity',
                                'capacity_tab' => 'documents',
                                'docs_panel' => $docBlockers === [] ? null : 'requests',
                            ])).'#review-documents';
                        @endphp
                        <button type="button"
                                data-open-dialog="recommend-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
                            Record decision
                        </button>
                        <dialog id="recommend-{{ $record->id }}"
                                class="rounded-2xl shadow-2xl ring-1 ring-brand/15 w-full max-w-md p-0 backdrop:bg-brand/40 open:flex open:flex-col"
                                x-data="{
                                    decision: '{{ $oldDecision }}',
                                    advice: '{{ old('rejection_advice_code', '') }}',
                                    counterEnabled: {{ $counterEnabled ? 'true' : 'false' }},
                                    affordPass: {{ $affordPass ? 'true' : 'false' }},
                                    docsReady: {{ $docBlockers === [] ? 'true' : 'false' }},
                                    get action() { return this.decision === 'reject' ? 'reject' : 'submit_recommendation' },
                                    get canSubmit() {
                                        if (!this.decision) return false;
                                        if (this.decision === 'reject') return true;
                                        if (!this.docsReady) return false;
                                        if (this.decision === 'approve' && !this.affordPass && {{ $autoReject ? 'true' : 'false' }}) return false;
                                        if (this.decision === 'counter' && !this.counterEnabled) return false;
                                        return true;
                                    }
                                }"
                                @if ($openReject)
                                    x-init="$nextTick(() => { $el.showModal() })"
                                @endif
                                >
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="max-h-[85vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" :value="action">
                                <input type="hidden" name="recommendation_type" :value="decision === 'reject' ? '' : decision">

                                <div class="px-4 pt-4 pb-3 bg-gradient-to-br from-brand via-brand to-brand-light text-white">
                                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Screening desk</p>
                                    <h4 class="text-lg font-bold mt-0.5">Record your decision</h4>
                                    <p class="text-xs text-white/75 mt-1">
                                        Approve or counter moves this file to committee. Reject closes it.
                                    </p>
                                    <div class="mt-2.5 flex flex-wrap gap-1.5 text-[11px]">
                                        <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 ring-1 ring-white/20">
                                            Requested {{ format_money((float) $record->requested_amount) }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 ring-1 ring-white/20">
                                            CRB {{ $crbRecLabel }}
                                        </span>
                                    </div>
                                </div>

                                <div class="p-4 space-y-3">
                                    @if ($docBlockers !== [])
                                        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-2.5">
                                            <p class="text-xs font-semibold text-amber-950">Cannot approve until these documents are in</p>
                                            <ul class="mt-1.5 space-y-0.5 text-[11px] text-amber-900">
                                                @foreach (array_slice($docBlockers, 0, 6) as $blocker)
                                                    <li>{{ $blocker }}</li>
                                                @endforeach
                                            </ul>
                                            <a href="{{ $docsUrl }}"
                                               class="mt-2 inline-flex text-[11px] font-bold text-brand underline underline-offset-2">
                                                Open Review checklist → Docs
                                            </a>
                                        </div>
                                    @endif

                                    @if (! $affordPass && $autoReject)
                                        <p class="text-xs text-red-800 bg-red-50 ring-1 ring-red-100 rounded-xl px-3 py-2">
                                            Affordability failed — approval at the requested amount is blocked. Reject, or request files on Review checklist → Docs.
                                        </p>
                                    @elseif (! $affordPass && $counterEnabled)
                                        <p class="text-xs text-amber-950 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-3 py-2">
                                            Affordability failed at the requested amount — use Counter-offer or Reject.
                                        </p>
                                    @elseif (! $affordPass)
                                        <p class="text-xs text-amber-950 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-3 py-2">
                                            Affordability failed and counter-offers are disabled — Reject, or request files on Review checklist → Docs.
                                        </p>
                                    @endif

                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-1.5">Decision</p>
                                        <div @class([
                                            'grid grid-cols-1 gap-1.5',
                                            'sm:grid-cols-3' => $counterEnabled,
                                            'sm:grid-cols-2' => ! $counterEnabled,
                                        ])>
                                            <button type="button" @click="decision = 'approve'"
                                                    :disabled="(!affordPass && {{ $autoReject ? 'true' : 'false' }}) || !docsReady"
                                                    :class="decision === 'approve'
                                                        ? 'bg-emerald-600 text-white ring-emerald-600 shadow-sm'
                                                        : 'bg-white text-gray-800 ring-gray-200 hover:bg-emerald-50'"
                                                    class="rounded-lg px-3 py-2 text-left ring-1 transition disabled:opacity-40">
                                                <span class="block text-sm font-bold">Approve</span>
                                                <span class="block text-[11px] mt-0.5 opacity-80">At {{ format_money((float) $record->requested_amount) }}</span>
                                            </button>
                                            @if ($counterEnabled)
                                                <button type="button" @click="decision = 'counter'"
                                                        :disabled="!docsReady"
                                                        :class="decision === 'counter'
                                                            ? 'bg-amber-500 text-white ring-amber-500 shadow-sm'
                                                            : 'bg-white text-gray-800 ring-gray-200 hover:bg-amber-50'"
                                                        class="rounded-lg px-3 py-2 text-left ring-1 transition disabled:opacity-40">
                                                    <span class="block text-sm font-bold">Counter</span>
                                                    <span class="block text-[11px] mt-0.5 opacity-80">
                                                        @if ($maxCounter > 0)
                                                            Max {{ format_money($maxCounter) }}
                                                        @else
                                                            Lower amount
                                                        @endif
                                                    </span>
                                                </button>
                                            @endif
                                            <button type="button" @click="decision = 'reject'"
                                                    :class="decision === 'reject'
                                                        ? 'bg-rose-600 text-white ring-rose-600 shadow-sm'
                                                        : 'bg-white text-gray-800 ring-gray-200 hover:bg-rose-50'"
                                                    class="rounded-lg px-3 py-2 text-left ring-1 transition">
                                                <span class="block text-sm font-bold">Reject</span>
                                                <span class="block text-[11px] mt-0.5 opacity-80">Close the application</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div x-show="decision === 'counter'" x-cloak>
                                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-1.5">System counter amount</p>
                                        <input type="hidden" name="recommended_amount" value="{{ $maxCounter > 0 ? (int) $maxCounter : 0 }}"
                                               :disabled="decision !== 'counter'">
                                        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                                            <p class="text-lg font-bold text-amber-950 tabular-nums">
                                                @if ($maxCounter > 0)
                                                    {{ format_money($maxCounter) }}
                                                @else
                                                    Not available yet
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-amber-900 mt-1">
                                                Calculated from statement-proven income and the one-third capacity rule. Screening cannot type a different amount.
                                            </p>
                                        </div>
                                    </div>

                                    <div x-show="decision === 'approve' || decision === 'counter'" x-cloak class="space-y-2.5">
                                        <p class="text-[11px] text-brand bg-brand-muted/40 ring-1 ring-brand/10 rounded-lg px-3 py-2">
                                            CRB is {{ $crbRecLabel }} — write why you are taking this decision.
                                        </p>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                                <span x-text="decision === 'counter' ? 'Why this counter-offer?' : 'Why are you approving?'"></span>
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <textarea name="remarks" rows="2" maxlength="1000"
                                                      :disabled="decision !== 'approve' && decision !== 'counter'"
                                                      :required="decision === 'approve' || decision === 'counter'"
                                                      placeholder="Reason for committee…"
                                                      class="w-full rounded-lg border-0 text-sm ring-1 ring-brand/15 px-3 py-2 focus:ring-2 focus:ring-brand/30">{{ old('remarks') }}</textarea>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Notes <span class="font-normal text-gray-400">(optional)</span></label>
                                            <textarea name="recommendation_notes" rows="1" maxlength="1000"
                                                      :disabled="decision !== 'approve' && decision !== 'counter'"
                                                      placeholder="Anything else for committee…"
                                                      class="w-full rounded-lg border-0 text-sm ring-1 ring-brand/15 px-3 py-2 focus:ring-2 focus:ring-brand/30">{{ old('recommendation_notes') }}</textarea>
                                        </div>
                                    </div>

                                    <div x-show="decision === 'reject'" x-cloak class="space-y-4">
                                        @if (! empty($checklistReject['fails']))
                                            <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 space-y-1.5">
                                                <p class="text-[10px] uppercase tracking-widest font-bold text-rose-900">From review checklist</p>
                                                @foreach (array_slice($checklistReject['fails'], 0, 6) as $failRow)
                                                    <p class="text-xs text-rose-950">
                                                        <span class="font-semibold">{{ $failRow['subject'] }}</span>
                                                        · {{ $failRow['label'] }} — {{ $failRow['fail_label'] }}
                                                    </p>
                                                @endforeach
                                                <p class="text-[11px] text-rose-800/80 pt-1">Reasons below are pre-ticked for the rejection letter. Adjust only if needed.</p>
                                            </div>
                                        @endif
                                        <p class="text-xs text-gray-500">
                                            Tick every reason that applies. The borrower sees these — and any advice — in their language.
                                        </p>
                                        @include('admin.loan-applications.review._rejection_fields', [
                                            'selectedRejectionCodes' => $checklistRejectCodes,
                                            'adviceOptions' => $adviceOptions,
                                            'disabledWhen' => "decision !== 'reject'",
                                        ])
                                    </div>
                                </div>

                                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/80 flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" data-close-dialog="recommend-{{ $record->id }}"
                                            class="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            :disabled="!canSubmit || !decision"
                                            class="inline-flex items-center justify-center min-w-[10rem] bg-brand-gold hover:brightness-95 disabled:opacity-40 text-brand font-bold text-sm px-4 py-2 rounded-lg shadow-sm">
                                        <span x-text="decision === 'reject' ? 'Confirm reject' : 'Push to Committee'"></span>
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'validate_screening')
                        @php
                            $screenType = $record->recommendation_type;
                            $needsFunding = $screenType === 'approve' && application_needs_funding_choice($record->product);
                        @endphp
                        <button type="button"
                                data-open-dialog="validate-screening-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
                            Validate screening decision
                        </button>
                        <dialog id="validate-screening-{{ $record->id }}"
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="validate_screening">
                                <h4 class="font-semibold text-gray-900">Validate screening decision</h4>
                                <p class="text-sm text-gray-600">
                                    Screening recommended
                                    <span class="font-semibold capitalize">{{ str_replace('_', ' ', (string) $screenType) }}</span>
                                    @if ($record->recommended_amount)
                                        at {{ format_money((float) $record->recommended_amount) }}
                                    @endif
                                    .
                                    Confirming applies the same decision without re-entering reasons.
                                </p>
                                @if (! empty($review['recommendation']['rationale_label'] ?? null) || ! empty($review['recommendation']['remarks'] ?? null))
                                    <div class="rounded-lg bg-brand-muted/50 ring-1 ring-brand/10 px-3 py-2 text-sm text-brand">
                                        @if (! empty($review['recommendation']['rationale_label']))
                                            <p class="font-semibold">{{ $review['recommendation']['rationale_label'] }}</p>
                                        @endif
                                        @if (! empty($review['recommendation']['remarks']))
                                            <p class="mt-1 text-brand/80">{{ $review['recommendation']['remarks'] }}</p>
                                        @endif
                                    </div>
                                @endif
                                @if ($needsFunding)
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Funding source</label>
                                        <select name="funding_source" required class="w-full rounded-lg border-gray-300 text-sm">
                                            <option value="">Select…</option>
                                            <option value="internal">Internal (company funds)</option>
                                            <option value="external">External capital partner</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Preferred capital partner (optional)</label>
                                        <select name="preferred_lender_id" class="w-full rounded-lg border-gray-300 text-sm">
                                            <option value="">Auto-allocate at disbursement</option>
                                            @foreach (($externalLenders ?? collect()) as $lender)
                                                <option value="{{ $lender->id }}">{{ $lender->name }} ({{ $lender->code }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="validate-screening-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">
                                        Confirm — same as screening
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'issue_offer')
                        <button type="button"
                                data-open-dialog="issue-offer-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="issue-offer-{{ $record->id }}"
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="issue_offer">
                                <h4 class="font-semibold text-gray-900">Issue offer to borrower</h4>
                                <p class="text-sm text-gray-600">
                                    Requested {{ format_money((float) $record->requested_amount) }} ·
                                    Recommended {{ format_money((float) ($record->recommended_amount ?? 0)) }}
                                </p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Offer amount</label>
                                        <input type="number" name="offered_amount" required min="0" step="1000"
                                               value="{{ (int) ($record->recommended_amount ?? $counterOffer['amount'] ?? 0) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tenure (months)</label>
                                        <input type="number" name="offered_tenure_months" required min="1" max="120"
                                               value="{{ $record->requested_tenure_months }}"
                                               class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                    </div>
                                </div>
                                @php $issueOfferDiffers = ($record->recommendation_type ?? null) !== 'counter'; @endphp
                                @if (! $issueOfferDiffers)
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Message to borrower (optional)</label>
                                        <textarea name="remarks" rows="2" maxlength="1000"
                                                  class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand"></textarea>
                                    </div>
                                @endif
                                @include('admin.loan-applications.review._committee_divergence_fields', [
                                    'committeeDiffers' => $issueOfferDiffers,
                                ])
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="issue-offer-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">
                                        Send offer
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'suggest_asset_alternative')
                        <button type="button"
                                data-open-dialog="asset-alt-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-sky-800 bg-sky-100 hover:bg-sky-200 px-4 py-2.5 rounded-lg ring-1 ring-sky-200 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="asset-alt-{{ $record->id }}"
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="suggest_asset_alternative">
                                <h4 class="font-semibold text-gray-900">Suggest asset-backed alternative</h4>
                                <p class="text-sm text-gray-600">Notify the borrower to apply for an asset-backed product instead.</p>
                                @if (! empty($assetAlternativeProduct))
                                    <input type="hidden" name="alternative_product_id" value="{{ $assetAlternativeProduct->id }}">
                                    <p class="text-sm font-medium text-gray-800">{{ $assetAlternativeProduct->name }}</p>
                                @else
                                    <p class="text-sm text-red-700">No active asset-backed product (code AB) configured.</p>
                                @endif
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Why suggest asset-backed <span class="text-red-500">*</span></label>
                                    <select name="recommendation_rationale" required
                                            class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                        <option value="">Select…</option>
                                        @foreach (config('credit_recommendation.rationales', []) as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Message to borrower <span class="text-red-500">*</span></label>
                                    <textarea name="remarks" rows="3" maxlength="1000" required placeholder="Explain why asset-backed may be a better fit"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="asset-alt-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" @disabled(empty($assetAlternativeProduct))
                                            class="bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white font-semibold text-sm px-4 py-2 rounded-lg">
                                        Notify borrower
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'approve' && application_needs_funding_choice($record->product))
                        <button type="button"
                                data-open-dialog="approve-application-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="approve-application-{{ $record->id }}"
                                class="rounded-2xl shadow-2xl ring-1 ring-brand/15 w-full max-w-xl p-0 backdrop:bg-brand/40 open:flex open:flex-col"
                                x-data="{ approvalReason: '{{ old('approval_reason_code', ($record->recommendation_type ?? null) === 'approve' ? 'aligns_with_screening' : '') }}' }">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="max-h-[90vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" value="approve">

                                <div class="px-6 pt-6 pb-5 bg-gradient-to-br from-brand via-brand to-brand-light text-white">
                                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit committee</p>
                                    <h4 class="text-xl font-bold mt-1">Final approve</h4>
                                    <p class="text-sm text-white/75 mt-1.5">
                                        Record why you are approving, then choose the funding source.
                                    </p>
                                </div>

                                <div class="p-6 space-y-5">
                                    @include('admin.loan-applications.review._approval_reason_fields')

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Funding source <span class="text-red-500">*</span></label>
                                        <select name="funding_source" required class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3 focus:ring-2 focus:ring-brand/30">
                                            <option value="">Select…</option>
                                            <option value="internal">Internal (company funds)</option>
                                            <option value="external">External capital partner</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Preferred capital partner <span class="font-normal text-gray-400">(optional)</span></label>
                                        <select name="preferred_lender_id" class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3 focus:ring-2 focus:ring-brand/30">
                                            <option value="">Auto-allocate at disbursement</option>
                                            @foreach (($externalLenders ?? collect()) as $lender)
                                                <option value="{{ $lender->id }}">{{ $lender->name }} ({{ $lender->code }})</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    @include('admin.loan-applications.review._committee_divergence_fields', [
                                        'committeeDiffers' => ($record->recommendation_type ?? null) !== 'approve',
                                    ])
                                </div>

                                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/80 flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" data-close-dialog="approve-application-{{ $record->id }}"
                                            class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-xl">Cancel</button>
                                    <button type="submit" class="inline-flex items-center justify-center min-w-[12rem] bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm">
                                        Approve application
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'approve')
                        <button type="button"
                                data-open-dialog="approve-plain-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="approve-plain-{{ $record->id }}"
                                class="rounded-2xl shadow-2xl ring-1 ring-brand/15 w-full max-w-xl p-0 backdrop:bg-brand/40 open:flex open:flex-col"
                                x-data="{ approvalReason: '{{ old('approval_reason_code', ($record->recommendation_type ?? null) === 'approve' ? 'aligns_with_screening' : '') }}' }">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="max-h-[90vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" value="approve">

                                <div class="px-6 pt-6 pb-5 bg-gradient-to-br from-brand via-brand to-brand-light text-white">
                                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit committee</p>
                                    <h4 class="text-xl font-bold mt-1">Final approve</h4>
                                    <p class="text-sm text-white/75 mt-1.5">
                                        Confirm final approval and record why the committee is approving.
                                    </p>
                                </div>

                                <div class="p-6 space-y-5">
                                    @include('admin.loan-applications.review._approval_reason_fields')

                                    @include('admin.loan-applications.review._committee_divergence_fields', [
                                        'committeeDiffers' => ($record->recommendation_type ?? null) !== 'approve',
                                    ])
                                </div>

                                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/80 flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" data-close-dialog="approve-plain-{{ $record->id }}"
                                            class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-xl">Cancel</button>
                                    <button type="submit" class="inline-flex items-center justify-center min-w-[12rem] bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm">
                                        Approve application
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'approve_with_conditions')
                        <button type="button"
                                data-open-dialog="approve-conditions-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-white hover:bg-brand-muted/40 px-5 py-2.5 rounded-lg ring-1 ring-brand/20 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="approve-conditions-{{ $record->id }}"
                                class="rounded-2xl shadow-2xl ring-1 ring-brand/15 w-full max-w-xl p-0 backdrop:bg-brand/40 open:flex open:flex-col"
                                x-data="{ approvalReason: '{{ old('approval_reason_code', 'custom') }}' }">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="max-h-[90vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" value="approve_with_conditions">
                                <div class="px-6 pt-6 pb-5 bg-gradient-to-br from-brand via-brand to-brand-light text-white">
                                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit committee</p>
                                    <h4 class="text-xl font-bold mt-1">Approve with conditions</h4>
                                </div>
                                <div class="p-6 space-y-5">
                                    @include('admin.loan-applications.review._approval_reason_fields')
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Conditions <span class="text-red-500">*</span></label>
                                        <textarea name="approval_reason_notes" required rows="3" class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3">{{ old('approval_reason_notes') }}</textarea>
                                    </div>
                                </div>
                                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/80 flex justify-end gap-2">
                                    <button type="button" data-close-dialog="approve-conditions-{{ $record->id }}" class="px-4 py-2.5 text-sm font-medium text-gray-600 rounded-xl">Cancel</button>
                                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl">Approve with conditions</button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'refer_back')
                        @php $fromManagement = ($record->current_stage ?? '') === 'awaiting_management'; @endphp
                        <button type="button"
                                data-open-dialog="refer-back-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-amber-950 bg-amber-100 hover:bg-amber-200 px-5 py-2.5 rounded-lg ring-1 ring-amber-200 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="refer-back-{{ $record->id }}" class="rounded-2xl shadow-2xl ring-1 ring-brand/15 w-full max-w-xl p-0 backdrop:bg-brand/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}">
                                @csrf
                                <input type="hidden" name="action" value="refer_back">
                                <div class="px-6 pt-6 pb-5 bg-gradient-to-br from-amber-700 to-brand text-white">
                                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-amber-100">{{ $fromManagement ? 'Credit management' : 'Credit committee' }}</p>
                                    <h4 class="text-xl font-bold mt-1">{{ $fromManagement ? 'Refer back' : 'Refer back to screening' }}</h4>
                                </div>
                                <div class="p-6 space-y-4">
                                    @if ($fromManagement)
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Send to <span class="text-red-500">*</span></label>
                                            <select name="refer_back_to" required class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3">
                                                <option value="committee" @selected(old('refer_back_to', 'committee') === 'committee')>Credit Committee</option>
                                                <option value="screening" @selected(old('refer_back_to') === 'screening')>Credit Screening</option>
                                            </select>
                                        </div>
                                    @endif
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Reason <span class="text-red-500">*</span></label>
                                        <textarea name="remarks" required rows="3" class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3">{{ old('remarks') }}</textarea>
                                    </div>
                                </div>
                                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/80 flex justify-end gap-2">
                                    <button type="button" data-close-dialog="refer-back-{{ $record->id }}" class="px-4 py-2.5 text-sm font-medium text-gray-600 rounded-xl">Cancel</button>
                                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm px-5 py-2.5 rounded-xl">Refer back</button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'management_approve')
                        <button type="button"
                                data-open-dialog="management-approve-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="management-approve-{{ $record->id }}"
                                class="rounded-2xl shadow-2xl ring-1 ring-brand/15 w-full max-w-xl p-0 backdrop:bg-brand/40 open:flex open:flex-col"
                                x-data="{ approvalReason: '{{ old('approval_reason_code', data_get($record->credit_appraisal_payload, 'committee_approval.reason_code', 'aligns_with_screening')) }}' }">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="max-h-[90vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" value="management_approve">
                                <div class="px-6 pt-6 pb-5 bg-gradient-to-br from-brand via-brand to-brand-light text-white">
                                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit management</p>
                                    <h4 class="text-xl font-bold mt-1">Approve for offer</h4>
                                </div>
                                <div class="p-6 space-y-5">
                                    @include('admin.loan-applications.review._approval_reason_fields')
                                </div>
                                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/80 flex justify-end gap-2">
                                    <button type="button" data-close-dialog="management-approve-{{ $record->id }}" class="px-4 py-2.5 text-sm font-medium text-gray-600 rounded-xl">Cancel</button>
                                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl">Approve for offer</button>
                                </div>
                            </form>
                        </dialog>
                    @else
                        <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $action['key'] }}">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
                                {{ $action['label'] }}
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
