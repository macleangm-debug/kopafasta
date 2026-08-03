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
                        @endphp
                        <button type="button"
                                data-open-dialog="reject-application-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-4 py-2.5 rounded-lg ring-1 ring-red-200 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="reject-application-{{ $record->id }}"
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col"
                                x-data="{
                                    reason: '{{ old('rejection_reason_code', $screeningReasonCode ?? '') }}',
                                    advice: '{{ old('rejection_advice_code', '') }}',
                                    useScreening() { this.reason = '{{ $screeningReasonCode }}'; }
                                }">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <h4 class="font-semibold text-gray-900">Reject application</h4>
                                <p class="text-sm text-gray-600">The borrower sees the reason and optional advice in their language. Internal notes stay private.</p>

                                @if ($screeningReasonCode && $screeningReasonLabel)
                                    <div class="rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-2.5 flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Screening reason</p>
                                            <p class="text-sm text-amber-950 font-medium mt-0.5">{{ $screeningReasonLabel }}</p>
                                        </div>
                                        <button type="button" @click="useScreening()"
                                                class="shrink-0 text-xs font-semibold text-amber-900 bg-white hover:bg-amber-100 ring-1 ring-amber-200 px-3 py-1.5 rounded-lg">
                                            Use this reason
                                        </button>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Rejection reason</label>
                                    <select name="rejection_reason_code" required x-model="reason"
                                            class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                        <option value="">Select reason…</option>
                                        @foreach (($rejectionReasons ?? []) as $category => $reasons)
                                            <optgroup label="{{ $category }}">
                                                @foreach ($reasons as $reason)
                                                    <option value="{{ $reason['code'] }}">{{ $reason['label'] }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Advice for borrower (optional)</label>
                                    <select name="rejection_advice_code" x-model="advice"
                                            class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                        <option value="">No advice</option>
                                        @foreach ($adviceOptions as $code => $label)
                                            @if ($code !== 'custom')
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endif
                                        @endforeach
                                        <option value="custom">Custom advice (write below)</option>
                                    </select>
                                    <p class="mt-1 text-[11px] text-gray-500">Shown on the borrower’s loan profile in their selected language.</p>
                                </div>
                                <div x-show="advice === 'custom'" x-cloak>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Custom advice</label>
                                    <textarea name="rejection_advice" rows="3" maxlength="2000"
                                              placeholder="Practical guidance for a stronger application next time"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">{{ old('rejection_advice') }}</textarea>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Internal notes (optional)</label>
                                    <textarea name="rejection_internal_notes" rows="3" maxlength="2000" placeholder="Notes for internal use only"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand"></textarea>
                                </div>
                                @include('admin.loan-applications.review._committee_divergence_fields', ['committeeDiffers' => true])
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button"
                                            data-close-dialog="reject-application-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">
                                        Confirm reject
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'return_for_documents')
                        <button type="button"
                                data-open-dialog="return-docs-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-sky-800 bg-sky-100 hover:bg-sky-200 px-4 py-2.5 rounded-lg ring-1 ring-sky-200 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="return-docs-{{ $record->id }}"
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" value="return_for_documents">
                                <h4 class="font-semibold text-gray-900">Return for documents</h4>
                                <p class="text-sm text-gray-600">Select the same document requests used on the Documents tab — the borrower is notified and can re-upload only those items.</p>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-2">Request documents</label>
                                    <div class="grid sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto">
                                        @foreach (\App\Services\ApplicationDocumentRequestService::PRESET_LABELS as $preset)
                                            <label class="flex items-start gap-2 text-xs text-gray-700 bg-gray-50 rounded-lg px-2.5 py-2 ring-1 ring-gray-100">
                                                <input type="checkbox" name="document_presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                                <span>{{ $preset }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Instructions for borrower</label>
                                    <textarea name="remarks" rows="3" maxlength="1000" required placeholder="e.g. Upload a clearer salary slip for the latest month."
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="return-docs-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-sky-700 hover:bg-sky-800 text-white font-semibold text-sm px-4 py-2 rounded-lg">
                                        Return to borrower
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
                            $oldDecision = old('recommendation_type', '');
                            if (old('action') === 'reject') {
                                $oldDecision = 'reject';
                            }
                        @endphp
                        <button type="button"
                                data-open-dialog="recommend-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
                            Record decision
                        </button>
                        <dialog id="recommend-{{ $record->id }}"
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col"
                                x-data="{
                                    decision: '{{ $oldDecision }}',
                                    counterEnabled: {{ $counterEnabled ? 'true' : 'false' }},
                                    affordPass: {{ $affordPass ? 'true' : 'false' }},
                                    get action() { return this.decision === 'reject' ? 'reject' : 'submit_recommendation' },
                                    get canSubmit() {
                                        if (!this.decision) return false;
                                        if (this.decision === 'approve' && !this.affordPass && {{ $autoReject ? 'true' : 'false' }}) return false;
                                        if (this.decision === 'counter' && !this.counterEnabled) return false;
                                        return true;
                                    }
                                }">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                                @csrf
                                <input type="hidden" name="action" :value="action">
                                <input type="hidden" name="recommendation_type" :value="decision === 'reject' ? '' : decision">

                                <h4 class="font-semibold text-gray-900">Screening decision</h4>
                                <p class="text-sm text-gray-600">
                                    Choose Approve, Reject, or Counter-offer
                                    @if ($counterEnabled)
                                        (counter enabled in settings)
                                    @else
                                        (counter-offers are disabled in settings)
                                    @endif
                                    — Approve / Counter push the file to committee; Reject closes the application.
                                </p>

                                @if (! $affordPass && $autoReject)
                                    <p class="text-sm text-red-700 bg-red-50 ring-1 ring-red-100 rounded-lg px-3 py-2">
                                        Affordability failed — Reject or return for documents. Approval at the requested amount is blocked.
                                    </p>
                                @elseif (! $affordPass && $counterEnabled)
                                    <p class="text-sm text-amber-800 bg-amber-50 ring-1 ring-amber-100 rounded-lg px-3 py-2">
                                        Affordability failed at the requested amount — use Counter-offer or Reject.
                                    </p>
                                @elseif (! $affordPass && ! $counterEnabled)
                                    <p class="text-sm text-amber-800 bg-amber-50 ring-1 ring-amber-100 rounded-lg px-3 py-2">
                                        Affordability failed and counter-offers are disabled — Reject or return for documents.
                                    </p>
                                @endif

                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Decision <span class="text-red-500">*</span></label>
                                    <select x-model="decision" required
                                            class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                        <option value="">Select…</option>
                                        <option value="approve" @disabled(! $affordPass && $autoReject)>
                                            Approve at requested amount ({{ format_money((float) $record->requested_amount) }})
                                        </option>
                                        @if ($counterEnabled)
                                            <option value="counter">
                                                Counter-offer
                                                @if ($maxCounter > 0)
                                                    (max {{ format_money($maxCounter) }})
                                                @endif
                                            </option>
                                        @endif
                                        <option value="reject">Reject application</option>
                                    </select>
                                </div>

                                <div x-show="decision === 'counter'" x-cloak>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Counter amount <span class="text-red-500">*</span></label>
                                    <input type="number" name="recommended_amount" min="0" step="1000"
                                           value="{{ old('recommended_amount', $maxCounter > 0 ? (int) $maxCounter : '') }}"
                                           placeholder="{{ $maxCounter > 0 ? (int) $maxCounter : 'Amount' }}"
                                           :disabled="decision !== 'counter'"
                                           :required="decision === 'counter'"
                                           class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                </div>

                                <div x-show="decision === 'approve' || decision === 'counter'" x-cloak class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Why this decision <span class="text-red-500">*</span></label>
                                        <select name="recommendation_rationale"
                                                :disabled="decision !== 'approve' && decision !== 'counter'"
                                                :required="decision === 'approve' || decision === 'counter'"
                                                class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                            <option value="">Select…</option>
                                            @foreach (config('credit_recommendation.rationales', []) as $code => $label)
                                                <option value="{{ $code }}" @selected(old('recommendation_rationale') === $code)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-[11px] text-gray-500">Committee sees this next to the CRB suggestion.</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Notes for committee <span class="text-red-500">*</span></label>
                                        <textarea name="remarks" rows="3" maxlength="1000"
                                                  :disabled="decision !== 'approve' && decision !== 'counter'"
                                                  :required="decision === 'approve' || decision === 'counter'"
                                                  placeholder="Explain your judgment — especially if it differs from CRB."
                                                  class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">{{ old('remarks') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Preferred reject reason if committee declines (optional)</label>
                                        <select name="screening_rejection_reason_code"
                                                :disabled="decision !== 'approve' && decision !== 'counter'"
                                                class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                            <option value="">None — committee chooses if they decline</option>
                                            @foreach (($rejectionReasons ?? []) as $category => $reasons)
                                                <optgroup label="{{ $category }}">
                                                    @foreach ($reasons as $reason)
                                                        <option value="{{ $reason['code'] }}">{{ $reason['label'] }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div x-show="decision === 'reject'" x-cloak class="space-y-4">
                                    @php
                                        $adviceOptions = $rejectionAdviceOptions
                                            ?? app(\App\Services\LoanRejectionReasonService::class)->adviceOptions();
                                    @endphp
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Rejection reason <span class="text-red-500">*</span></label>
                                        <select name="rejection_reason_code"
                                                :disabled="decision !== 'reject'"
                                                :required="decision === 'reject'"
                                                class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                            <option value="">Select reason…</option>
                                            @foreach (($rejectionReasons ?? []) as $category => $reasons)
                                                <optgroup label="{{ $category }}">
                                                    @foreach ($reasons as $reason)
                                                        <option value="{{ $reason['code'] }}">{{ $reason['label'] }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Advice for borrower (optional)</label>
                                        <select name="rejection_advice_code"
                                                :disabled="decision !== 'reject'"
                                                class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                            <option value="">No advice</option>
                                            @foreach ($adviceOptions as $code => $label)
                                                @if ($code !== 'custom')
                                                    <option value="{{ $code }}">{{ $label }}</option>
                                                @endif
                                            @endforeach
                                            <option value="custom">Custom advice (write below)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Internal notes (optional)</label>
                                        <textarea name="rejection_internal_notes" rows="2" maxlength="2000"
                                                  :disabled="decision !== 'reject'"
                                                  placeholder="Notes for internal use only"
                                                  class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand"></textarea>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="recommend-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit"
                                            :disabled="!canSubmit"
                                            class="bg-brand-gold hover:brightness-95 disabled:opacity-50 text-brand font-semibold text-sm px-4 py-2 rounded-lg"
                                            x-text="decision === 'reject' ? 'Reject application' : (decision === 'counter' ? 'Push counter to committee' : 'Push approval to committee')">
                                        Submit decision
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
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <h4 class="font-semibold text-gray-900">Final approve — funding source</h4>
                                <p class="text-sm text-gray-600">Choose whether this loan is funded from company balance or an external capital partner pool.</p>
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
                                @include('admin.loan-applications.review._committee_divergence_fields', [
                                    'committeeDiffers' => ($record->recommendation_type ?? null) !== 'approve',
                                ])
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="approve-application-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">
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
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <h4 class="font-semibold text-gray-900">Final approve</h4>
                                <p class="text-sm text-gray-600">Confirm final approval for this application.</p>
                                @include('admin.loan-applications.review._committee_divergence_fields', [
                                    'committeeDiffers' => ($record->recommendation_type ?? null) !== 'approve',
                                ])
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="approve-plain-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">
                                        Approve application
                                    </button>
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
