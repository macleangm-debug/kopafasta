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

    <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Offer letter</h4>
    @if ($offer)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4">
            <div><div class="text-xs uppercase text-gray-500">Reference</div><div class="font-mono font-semibold">{{ $offer->reference }}</div></div>
            <div><div class="text-xs uppercase text-gray-500">Status</div>
                <span @class([
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase mt-1',
                    'bg-emerald-100 text-emerald-800' => $offer->status === 'signed',
                    'bg-amber-100 text-amber-800'     => $offer->status === 'sent',
                    'bg-gray-100 text-gray-700'       => in_array($offer->status, ['draft','expired','cancelled']),
                ])>{{ $offer->status }}</span>
            </div>
            <div><div class="text-xs uppercase text-gray-500">Signed at</div><div>{{ optional($offer->signed_at)->format('d M Y, H:i') ?? '—' }}</div></div>
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
                <p class="text-[10px] uppercase text-gray-500">Borrower signature</p>
                <p class="font-semibold mt-1">{{ $offer->isSigned() ? 'Signed' : 'Pending' }}</p>
            </div>
            @if ($needsGuarantor)
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3 sm:col-span-2 lg:col-span-4">
                    <p class="text-[10px] uppercase text-gray-500">Guarantor signature</p>
                    <p class="font-semibold mt-1 {{ $guarantorSigned ? 'text-emerald-700' : 'text-amber-700' }}">{{ $guarantorSigned ? 'Signed' : 'Pending — blocks disbursement' }}</p>
                </div>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('admin.loan-agreements.download', $offer) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-4 py-2 rounded-lg">
                View PDF
            </a>
            <form method="POST" action="{{ route('admin.loan-applications.agreement.generate', $record) }}"
                  onsubmit="return confirm('Regenerate the offer letter? The borrower will need to sign the new version.');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                    Regenerate offer
                </button>
            </form>
        </div>
    @else
        <p class="text-sm text-gray-500 mb-4">No offer letter yet. One is generated automatically on final approval, or generate manually below.</p>
        <form method="POST" action="{{ route('admin.loan-applications.agreement.generate', $record) }}" class="mb-6">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                Generate offer letter
            </button>
        </form>
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
            <a href="{{ route('admin.loan-agreements.download', $contract) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-4 py-2 rounded-lg mb-6">
                View contract PDF
            </a>
        @endif
    @else
        <p class="text-sm text-gray-500 mb-6">Loan contract is generated after post-approval fees are paid.</p>
    @endif

    <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Post-approval fees</h4>
    @if ($hasFees)
        @php $record->loadMissing('postApprovalFees'); @endphp
        <ul class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-200 overflow-hidden mb-4">
            @foreach ($record->postApprovalFees as $fee)
                <li class="px-4 py-3 flex items-center justify-between text-sm">
                    <span>{{ $fee->name }}</span>
                    <span class="font-semibold {{ $fee->isPaid() ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ format_money($fee->calculated_amount) }} · {{ ucfirst($fee->status) }}
                    </span>
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
