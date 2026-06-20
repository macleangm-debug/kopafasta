@php
    $effectiveAmount = app(\App\Services\ApplicationOfferService::class)->effectiveAmount($record);
    $feesPaid = $disbursementReadiness->feesPaid($record);
    $hasFees = $disbursementReadiness->hasPostApprovalFees($record);
    $blocking = $disbursementReadiness->blockingMessages($record);
    $needsGuarantor = $disbursementReadiness->requiresGuarantorSignature($record);
    $guarantorSigned = $disbursementReadiness->guarantorSigned($record);
    $contractSigned = $disbursementReadiness->contractSigned($record);
    $checklist = $disbursementReadiness->disbursementChecklist($record);
    $canDisburse = $disbursementReadiness->canMarkDisbursement($record);
@endphp

<x-admin.review-section id="review-contract" title="Loan contract & disbursement readiness" subtitle="Post-approval fees, contract acceptance, and disbursement gates">
    @if (in_array($record->current_stage, ['approval', 'disbursement'], true))
        <div class="mb-5 rounded-lg ring-1 ring-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-600">{{ $record->application_number }} — Disbursement prerequisites</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($checklist as $key => $item)
                    @php
                        $statusText = match ($item['status']) {
                            'paid', 'accepted', 'complete', 'not_required', 'available' => '✓ '.ucfirst($item['status'] === 'not_required' ? 'N/A' : ($item['status'] === 'paid' ? 'Paid' : ($item['status'] === 'accepted' ? 'Accepted' : ($item['status'] === 'available' ? 'Available' : 'Complete')))),
                            'pending' => 'Pending',
                            'insufficient' => 'Insufficient',
                            'locked' => 'Locked',
                            'not_generated' => 'Not generated',
                            default => ucfirst($item['status']),
                        };
                        $tone = ($item['complete'] ?? false) ? 'text-emerald-700' : (($item['status'] ?? '') === 'locked' ? 'text-gray-500' : 'text-amber-700');
                    @endphp
                    <li class="px-4 py-3 flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-900">{{ $item['label'] }}</span>
                        <span class="font-semibold {{ $tone }}">{{ $statusText }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($blocking !== [])
            <div class="mb-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Before disbursement</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5">
                    @foreach ($blocking as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @elseif ($canDisburse)
            <p class="mb-4 text-sm text-emerald-700 font-semibold">Ready for disbursement — all prerequisites complete.</p>
        @endif
    @endif

    @php
        $disbursementDestination = $disbursementReadiness->disbursementDestination($record);
        $detailsService = app(\App\Services\CustomerDisbursementDetailsService::class);
        $destinationConfirmed = $disbursementReadiness->disbursementDetailsConfirmed($record);
    @endphp

    @if (! empty($disbursementDestination['method'] ?? null))
        <div class="mb-5 rounded-lg ring-1 {{ $destinationConfirmed ? 'ring-emerald-200 bg-emerald-50/40' : 'ring-gray-200' }} overflow-hidden">
            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-600">Disbursement destination</p>
                @if ($destinationConfirmed)
                    <span class="text-[10px] font-semibold uppercase tracking-widest text-emerald-700">Locked · Selected by borrower</span>
                @endif
            </div>
            <dl class="grid sm:grid-cols-2 gap-4 px-4 py-4 text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Method</dt>
                    <dd class="font-semibold text-gray-900 mt-1">{{ $detailsService->methodLabel($disbursementDestination['method'] ?? null) }}</dd>
                </div>
                @foreach ($detailsService->displayLines($disbursementDestination) as $label => $value)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ $label }}</dt>
                        <dd class="font-semibold text-gray-900 mt-1">{{ $value }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Selected by borrower</dt>
                    <dd class="font-semibold {{ $destinationConfirmed ? 'text-emerald-700' : 'text-amber-700' }} mt-1">
                        {{ ($disbursementDestination['selected_by_borrower'] ?? false) ? 'Yes' : 'No' }}
                    </dd>
                </div>
            </dl>
        </div>
    @endif

    <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Offer summary</h4>
    @php
        $offerDeclined = app(\App\Services\ApplicationOfferService::class)->offerDeclinedByBorrower($record);
    @endphp

    @if ($offerDeclined)
        <div class="mb-5 rounded-lg ring-1 ring-red-200 bg-red-50/50 overflow-hidden">
            <div class="px-4 py-3 border-b border-red-100">
                <p class="text-sm font-semibold text-red-900">Offer declined by borrower</p>
                <p class="text-xs text-red-700 mt-0.5">This is the borrower&apos;s decision — not an application rejection.</p>
            </div>
            <dl class="grid sm:grid-cols-2 gap-4 px-4 py-4 text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Declined on</dt>
                    <dd class="font-semibold text-gray-900 mt-1">{{ optional($record->offer_responded_at)->format('d M Y, H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Previous amount</dt>
                    <dd class="font-semibold text-gray-900 mt-1">{{ format_money($effectiveAmount) }}</dd>
                </div>
                @if (filled($record->offer_decline_reason))
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Reason</dt>
                        <dd class="text-gray-800 mt-1">{{ $record->offer_decline_reason }}</dd>
                    </div>
                @endif
            </dl>
            <div class="px-4 pb-4 flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('admin.loan-applications.offer.resend', $record) }}"
                      onsubmit="return confirm('Resend the same offer to the borrower?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                        Resend offer
                    </button>
                </form>
                <button type="button"
                        data-open-dialog="reissue-offer-{{ $record->id }}"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-900 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                    Create new offer
                </button>
            </div>
        </div>

        <dialog id="reissue-offer-{{ $record->id }}"
                class="rounded-xl shadow-xl ring-1 ring-gray-200 p-0 w-full max-w-md backdrop:bg-black/40">
            <form method="POST" action="{{ route('admin.loan-applications.offer.reissue', $record) }}" class="p-6">
                @csrf
                <h4 class="font-semibold text-gray-900">Create new offer</h4>
                <p class="text-xs text-gray-500 mt-1">Issue revised terms after the borrower declined the previous offer.</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Loan amount (TZS)</label>
                        <input type="number" name="offered_amount" required min="0" step="1000"
                               value="{{ (int) $effectiveAmount }}"
                               class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Duration (months)</label>
                        <input type="number" name="offered_tenure_months" required min="1" max="120"
                               value="{{ $record->offered_tenure_months ?? $record->requested_tenure_months }}"
                               class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Notes (optional)</label>
                        <textarea name="remarks" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" data-close-dialog="reissue-offer-{{ $record->id }}"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">Send new offer</button>
                </div>
            </form>
        </dialog>
    @endif

    @if ($offer)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4">
            <div><div class="text-xs uppercase text-gray-500">Reference</div><div class="font-mono font-semibold">{{ $offer->reference }}</div></div>
            <div><div class="text-xs uppercase text-gray-500">Status</div>
                <span @class([
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase mt-1',
                    'bg-emerald-100 text-emerald-800' => $offer->status === 'signed',
                    'bg-red-100 text-red-800'         => $offer->isCancelled(),
                    'bg-amber-100 text-amber-800'     => $offer->status === 'sent',
                    'bg-gray-100 text-gray-700'       => in_array($offer->status, ['draft','expired','cancelled']) && ! $offer->isCancelled(),
                ])>{{ $offer->status === 'signed' ? 'Accepted' : ($offer->isCancelled() ? 'Declined by borrower' : ucfirst($offer->status)) }}</span>
            </div>
            <div><div class="text-xs uppercase text-gray-500">Accepted at</div><div>{{ optional($offer->signed_at)->format('d M Y, H:i') ?? '—' }}</div></div>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-5">
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                <p class="text-[10px] uppercase text-gray-500">Loan amount</p>
                <p class="font-semibold mt-1">{{ format_money($effectiveAmount) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                <p class="text-[10px] uppercase text-gray-500">Tenure</p>
                <p class="font-semibold mt-1">{{ $record->offered_tenure_months ?? $record->requested_tenure_months }} months</p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                <p class="text-[10px] uppercase text-gray-500">Product rate</p>
                <p class="font-semibold mt-1">{{ format_number((float) ($review['product']?->interest_rate ?? 0) * 100, 2) }}% / month</p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                <p class="text-[10px] uppercase text-gray-500">Borrower acceptance</p>
                <p class="font-semibold mt-1">{{ $offer->isSigned() ? 'Accepted' : 'Pending' }}</p>
            </div>
            @if ($needsGuarantor)
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3 sm:col-span-2 lg:col-span-4">
                    <p class="text-[10px] uppercase text-gray-500">Guarantor signature</p>
                    <p class="font-semibold mt-1 {{ $guarantorSigned ? 'text-emerald-700' : 'text-amber-700' }}">{{ $guarantorSigned ? 'Signed' : 'Pending — blocks disbursement' }}</p>
                </div>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2 mb-6">
            @if ($offer->file_path)
                <x-admin.document-preview
                    :url="route('admin.loan-agreements.download', $offer)"
                    label="View offer summary" />
            @endif
            <form method="POST" action="{{ route('admin.loan-applications.agreement.generate', $record) }}"
                  onsubmit="return confirm('{{ $offerDeclined ? 'Resend this offer to the borrower with the same terms?' : 'Regenerate the offer letter? The borrower will need to sign the new version.' }}');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                    {{ $offerDeclined ? 'Resend offer' : 'Regenerate offer' }}
                </button>
            </form>
        </div>
    @else
        <p class="text-sm text-gray-500 mb-4">No offer summary yet. One is generated automatically on approval, or generate manually below.</p>
        <form method="POST" action="{{ route('admin.loan-applications.agreement.generate', $record) }}" class="mb-6">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                Generate offer summary
            </button>
        </form>
    @endif

    @php
        $finalContract = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $record->id)
            ->where('document_type', 'final_loan_contract')
            ->latest('id')
            ->first();
    @endphp
    @if ($finalContract?->file_path)
        <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3 mt-6">Final loan contract (post-disbursement)</h4>
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <x-admin.document-preview
                :url="route('admin.loan-agreements.download', $finalContract)"
                label="Final contract + schedule" />
        </div>
    @endif

    <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Loan contract</h4>
    @if ($contract ?? null)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4">
            <div><div class="text-xs uppercase text-gray-500">Reference</div><div class="font-mono font-semibold">{{ $contract->reference }}</div></div>
            <div><div class="text-xs uppercase text-gray-500">Generated</div><div>{{ optional($contract->sent_at)->format('d M Y, H:i') ?? '—' }}</div></div>
            <div><div class="text-xs uppercase text-gray-500">Status</div>
                <span @class([
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase mt-1',
                    'bg-emerald-100 text-emerald-800' => $contractSigned,
                    'bg-amber-100 text-amber-800'     => ! $contractSigned && $contract->status === 'sent',
                    'bg-gray-100 text-gray-700'       => in_array($contract->status, ['draft','expired','cancelled']),
                ])>{{ $contractSigned ? 'Accepted' : ucfirst($contract->status) }}</span>
            </div>
        </div>
        @if ($contract->file_path)
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <x-admin.document-preview
                    :url="route('admin.loan-agreements.download', $contract)"
                    label="Preview contract PDF" />
                <form method="POST" action="{{ route('admin.loan-applications.contract.generate', $record) }}"
                      onsubmit="return confirm('Regenerate the loan contract? The borrower will need to sign the new version.');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                        Regenerate contract
                    </button>
                </form>
            </div>
        @endif
    @else
        <p class="text-sm text-gray-500 mb-6">Loan contract is generated after post-approval fees are paid.</p>
    @endif

    <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Post-approval fees</h4>
    @if ($hasFees)
        @php $record->loadMissing('postApprovalFees'); @endphp
        <ul class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-200 overflow-hidden mb-4">
            @foreach ($record->postApprovalFees as $fee)
                <li class="px-4 py-3 text-sm space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <span>{{ $fee->name }}</span>
                        <span class="font-semibold {{ $fee->isPaid() ? 'text-emerald-700' : ($fee->isWaived() ? 'text-gray-500 line-through' : 'text-amber-700') }}">
                            {{ format_money($fee->calculated_amount) }} · {{ ucfirst($fee->status) }}
                        </span>
                    </div>
                    @if ($fee->override_reason)
                        <p class="text-xs text-gray-500">Note: {{ $fee->override_reason }}</p>
                    @endif
                    @if (! $fee->isPaid() && ! $fee->isWaived() && in_array($record->current_stage, ['approval', 'disbursement'], true))
                        <form method="POST" action="{{ route('admin.loan-applications.post-approval-fees.update', [$record, $fee]) }}" class="flex flex-wrap items-end gap-2 pt-1">
                            @csrf
                            <input type="hidden" name="action" value="update">
                            <div>
                                <label class="block text-[10px] uppercase text-gray-500">Override amount</label>
                                <input type="number" name="amount" step="0.01" min="0" value="{{ $fee->calculated_amount }}" class="w-32 rounded border-gray-300 text-xs">
                            </div>
                            <div class="flex-1 min-w-[140px]">
                                <label class="block text-[10px] uppercase text-gray-500">Reason</label>
                                <input type="text" name="reason" maxlength="500" placeholder="Optional" class="w-full rounded border-gray-300 text-xs">
                            </div>
                            <button type="submit" class="text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg">Update</button>
                        </form>
                        <form method="POST" action="{{ route('admin.loan-applications.post-approval-fees.update', [$record, $fee]) }}" class="inline"
                              onsubmit="return confirm('Waive this fee for the borrower?');">
                            @csrf
                            <input type="hidden" name="action" value="waive">
                            <input type="hidden" name="reason" value="Waived by loan officer">
                            <button type="submit" class="text-xs font-semibold text-gray-600 hover:text-red-700 underline">Waive fee</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="text-sm {{ $feesPaid ? 'text-emerald-700 font-semibold' : 'text-amber-800' }}">
            {{ $feesPaid ? 'All post-approval fees recorded as paid.' : 'Awaiting borrower payment confirmation.' }}
        </p>
    @else
        <p class="text-sm text-gray-500">No post-approval fees configured for this product.</p>
    @endif
</x-admin.review-section>
