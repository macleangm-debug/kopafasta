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
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Internal notes (optional)</label>
                                    <textarea name="rejection_internal_notes" rows="3" maxlength="2000" placeholder="Notes for internal use only"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand"></textarea>
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
                        @endphp
                        <button type="button"
                                data-open-dialog="recommend-{{ $record->id }}"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm ring-1 ring-brand/20 transition">
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
                                            class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                        <option value="">Select…</option>
                                        @if ($affordPass)
                                            <option value="approve">Recommend approval at requested amount ({{ format_money((float) $record->requested_amount) }})</option>
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
                                           class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Notes for committee</label>
                                    <textarea name="remarks" rows="3" maxlength="1000" placeholder="Optional rationale"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="recommend-{{ $record->id }}"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</button>
                                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">
                                        Submit recommendation
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
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Message to borrower (optional)</label>
                                    <textarea name="remarks" rows="2" maxlength="1000"
                                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand"></textarea>
                                </div>
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
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Message to borrower</label>
                                    <textarea name="remarks" rows="3" maxlength="1000" placeholder="Explain why asset-backed may be a better fit"
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
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-dialog="approve-application-{{ $record->id }}"
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
