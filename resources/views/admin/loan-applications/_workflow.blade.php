@php
    $currentStage = $record->current_stage ?? 'submitted';
    $stages = ['submitted', 'screening', 'credit_appraisal', 'pre_approval', 'approval', 'disbursement'];
    $currentIndex = array_search($currentStage, $stages, true);
    if ($currentStage === 'rejected') {
        $currentIndex = false;
    }
@endphp

<div id="review-workflow" class="scroll-mt-24 mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Application workflow</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Current stage:
                <span class="font-semibold text-gray-800">{{ $workflow->stageLabel($currentStage) }}</span>
            </p>
        </div>
        @if ($currentStage === 'rejected')
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-red-100 text-red-800">Rejected</span>
            @if ($record->rejection_reason)
                <span class="text-xs text-red-700">Reason: {{ $record->rejection_reason }}</span>
            @endif
        @elseif ($record->status === 'pending_documents')
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-sky-100 text-sky-800">Awaiting borrower documents</span>
        @elseif ($currentStage === 'disbursement')
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-emerald-100 text-emerald-800">Ready for disbursement</span>
        @endif
    </div>

    @if ($currentStage !== 'rejected')
        <ol class="flex flex-wrap gap-2 mb-6">
            @foreach ($stages as $index => $stage)
                @php
                    $done = $currentIndex !== false && $index < $currentIndex;
                    $active = $stage === $currentStage;
                @endphp
                <li class="flex items-center gap-1.5">
                    <span @class([
                        'inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-3 py-1.5 whitespace-nowrap border',
                        'bg-emerald-50 text-emerald-800 border-emerald-200' => $done,
                        'bg-amber-50 text-amber-900 border-amber-300 ring-2 ring-amber-200' => $active,
                        'bg-gray-50 text-gray-600 border-gray-200' => ! $done && ! $active,
                    ])>
                        @if ($done)
                            <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            <span @class([
                                'size-4 shrink-0 rounded-full grid place-items-center text-[10px] font-bold',
                                'bg-amber-600 text-white' => $active,
                                'bg-gray-200 text-gray-600' => ! $active,
                            ])>{{ $index + 1 }}</span>
                        @endif
                        {{ $workflow->stageLabel($stage) }}
                    </span>
                    @if (! $loop->last)
                        <span class="text-gray-300 hidden sm:inline" aria-hidden="true">→</span>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if ($availableActions->isNotEmpty())
        <div class="border-t border-gray-100 pt-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Dual approval</p>
                    <div class="mb-4 grid sm:grid-cols-2 gap-3 text-xs">
                        <div class="rounded-lg bg-sky-50 ring-1 ring-sky-100 px-3 py-2">
                            <p class="font-semibold text-sky-800">1. Credit recommendation</p>
                            <p class="text-sky-700 mt-0.5">Analyst submits approve / counter before committee.</p>
                        </div>
                        <div class="rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
                            <p class="font-semibold text-amber-900">2. Committee approve</p>
                            <p class="text-amber-800 mt-0.5">Committee issues offer or final approval.</p>
                        </div>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Available actions</p>
            <div class="flex flex-wrap gap-3">
                @foreach ($availableActions as $action)
                    @if ($action['key'] === 'reject')
                        <button type="button"
                                data-open-dialog="reject-application-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-4 py-2.5 rounded-lg ring-1 ring-red-200 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="reject-application-{{ $record->id }}"
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <h4 class="font-semibold text-gray-900">Reject application</h4>
                                <p class="text-sm text-gray-600">Select a standardized reason. The borrower sees the reason label only — internal notes stay private.</p>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Rejection reason</label>
                                    <select name="rejection_reason_code" required
                                            class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500">
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
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Internal notes (optional)</label>
                                    <textarea name="rejection_internal_notes" rows="3" maxlength="2000" placeholder="Notes for internal use only"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500"></textarea>
                                </div>
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
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="return_for_documents">
                                <h4 class="font-semibold text-gray-900">Return for documents</h4>
                                <p class="text-sm text-gray-600">The borrower will be notified to upload or update documents. You can also create specific document requests below.</p>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Instructions for borrower</label>
                                    <textarea name="remarks" rows="4" maxlength="1000" required placeholder="e.g. Upload a bank statement covering the last 6 months."
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500"></textarea>
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
                        @endphp
                        <button type="button"
                                data-open-dialog="recommend-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-amber-700/20 transition">
                            {{ $action['label'] }}
                        </button>
                        <dialog id="recommend-{{ $record->id }}"
                                class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-lg p-0 backdrop:bg-black/40 open:flex open:flex-col">
                            <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="submit_recommendation">
                                <h4 class="font-semibold text-gray-900">Credit recommendation</h4>
                                <p class="text-sm text-gray-600">Move to committee pre-approval with your recommendation.</p>
                                @if (! $affordPass && $autoReject)
                                    <p class="text-sm text-red-700 bg-red-50 ring-1 ring-red-100 rounded-lg px-3 py-2">
                                        Affordability failed — reject the application or return for documents.
                                    </p>
                                @elseif (! $affordPass && ! $counterEnabled)
                                    <p class="text-sm text-amber-800 bg-amber-50 ring-1 ring-amber-100 rounded-lg px-3 py-2">
                                        Counter-offers are disabled. Reject or return for documents.
                                    </p>
                                @endif
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Recommendation</label>
                                    <select name="recommendation_type" required
                                            class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500">
                                        <option value="">Select…</option>
                                        @if ($affordPass)
                                            <option value="approve">Approve at requested amount ({{ format_money((float) $record->requested_amount) }})</option>
                                        @endif
                                        @if ($counterEnabled && $maxCounter > 0)
                                            <option value="counter">Counter-offer (max {{ format_money($maxCounter) }})</option>
                                        @endif
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Recommended amount (counter only)</label>
                                    <input type="number" name="recommended_amount" min="0" step="1000"
                                           value="{{ $maxCounter > 0 ? (int) $maxCounter : '' }}"
                                           placeholder="{{ $maxCounter > 0 ? (int) $maxCounter : 'Amount' }}"
                                           class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Notes for committee</label>
                                    <textarea name="remarks" rows="3" maxlength="1000" placeholder="Optional rationale"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="recommend-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">
                                        Submit recommendation
                                    </button>
                                </div>
                            </form>
                        </dialog>
                    @elseif ($action['key'] === 'issue_offer')
                        <button type="button"
                                data-open-dialog="issue-offer-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-amber-700/20 transition">
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
                                               class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tenure (months)</label>
                                        <input type="number" name="offered_tenure_months" required min="1" max="120"
                                               value="{{ $record->requested_tenure_months }}"
                                               class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Message to borrower (optional)</label>
                                    <textarea name="remarks" rows="2" maxlength="1000"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="issue-offer-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">
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
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Message to borrower</label>
                                    <textarea name="remarks" rows="3" maxlength="1000" placeholder="Explain why asset-backed may be a better fit"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500"></textarea>
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
                                class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-amber-700/20 transition">
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
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="approve-application-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">
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
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-amber-700/20 transition">
                                {{ $action['label'] }}
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
    @elseif ($currentStage !== 'rejected' && $currentStage !== 'disbursement')
        <p class="text-sm text-gray-500 border-t border-gray-100 pt-4">No workflow actions available for your role at this stage.</p>
    @endif
</div>

<div id="review-history" class="scroll-mt-24 mt-6 grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Stage history</h3>
        @if ($stageHistory->isEmpty())
            <p class="text-sm text-gray-500">No stage changes recorded yet.</p>
        @else
            <ul class="space-y-4">
                @foreach ($stageHistory as $entry)
                    <li class="flex gap-3">
                        <div class="mt-1 size-2 rounded-full bg-amber-500 shrink-0"></div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $workflow->stageLabel($entry->from_stage ?? 'start') }}
                                →
                                {{ $workflow->stageLabel($entry->to_stage) }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $entry->created_at?->format('d M Y, H:i') }}
                                @if ($entry->changedByUser)
                                    · {{ $entry->changedByUser->name }}
                                @endif
                            </p>
                            @if ($entry->remarks)
                                <p class="text-xs text-gray-600 mt-1 bg-gray-50 rounded-lg px-3 py-2">{{ $entry->remarks }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Audit trail</h3>
        @if ($auditLogs->isEmpty())
            <p class="text-sm text-gray-500">No audit entries for this application yet.</p>
        @else
            <ul class="space-y-3">
                @foreach ($auditLogs as $log)
                    <li class="text-sm border-b border-gray-50 pb-3 last:border-0">
                        <p class="font-medium text-gray-800">{{ str_replace('.', ' · ', $log->event) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $log->created_at?->format('d M Y, H:i') }}
                            @if ($log->user) · {{ $log->user->name }} @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
