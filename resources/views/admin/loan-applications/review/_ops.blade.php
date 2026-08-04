@php
    $stage = $record->current_stage ?? 'submitted';
    $isScreeningStage = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
    $isCommitteeStage = in_array($stage, ['pre_approval'], true);
    $isManagementStage = in_array($stage, [
        'approval',
        'post_approval_fees',
        'awaiting_disbursement_details',
        'contract_generation',
    ], true);
    $isDisbursementStage = in_array($stage, ['disbursement'], true) || $record->status === 'disbursed';
    $isOpsStage = $isManagementStage || $isDisbursementStage;
    $checklist = app(\App\Services\ApplicationDisbursementReadinessService::class)
        ->borrowerDisbursementChecklist($record);
@endphp

{{-- Management / disbursement ops: focused checklist, not full credit screening dossier --}}
<div id="review-ops" class="scroll-mt-24 mb-6 space-y-4">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-brand/15 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand">
                    {{ $isDisbursementStage ? 'Disbursement desk' : 'Credit management' }}
                </p>
                <h3 class="text-sm font-semibold text-gray-900 mt-0.5">
                    {{ $isDisbursementStage ? 'Release checklist' : 'Management checklist' }}
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Screening and CRB review are complete. This desk handles offer, fees, destination confirmation, contract, and release.
                </p>
            </div>
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-brand-gold/30 text-brand">
                {{ $workflow->stageLabel($stage) }}
            </span>
        </div>

        <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-5">
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Approved / offered</dt>
                <dd class="font-bold text-gray-900 mt-1">{{ format_money((float) ($record->offered_amount ?: $record->recommended_amount ?: $record->requested_amount)) }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Tenure</dt>
                <dd class="font-bold text-gray-900 mt-1">{{ $record->offered_tenure_months ?? $record->requested_tenure_months }} months</dd>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Offer status</dt>
                <dd class="font-bold text-gray-900 mt-1 capitalize">{{ str_replace('_', ' ', (string) ($record->offer_status ?: '—')) }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Borrower</dt>
                <dd class="font-bold text-gray-900 mt-1 truncate">{{ $review['customer']->full_name }}</dd>
            </div>
        </dl>

        <div class="grid sm:grid-cols-2 gap-3">
            @foreach ($checklist as $item)
                @php
                    $done = (bool) ($item['complete'] ?? false);
                    $label = $item['label'] ?? 'Item';
                    $status = (string) ($item['status'] ?? ($done ? 'complete' : 'pending'));
                @endphp
                <div @class([
                    'rounded-xl px-4 py-3 ring-1 text-sm',
                    'bg-emerald-50 ring-emerald-200 text-emerald-900' => $done,
                    'bg-gray-50 ring-gray-200 text-gray-600' => $status === 'locked' || $status === 'not_required',
                    'bg-amber-50 ring-amber-200 text-amber-950' => ! $done && ! in_array($status, ['locked', 'not_required'], true),
                ])>
                    <p class="font-semibold">{{ $label }}</p>
                    <p class="text-xs mt-1 opacity-80 capitalize">{{ str_replace('_', ' ', $status) }}</p>
                </div>
            @endforeach
        </div>

        @if (($availableActions ?? collect())->isNotEmpty())
            <div class="mt-5 border-t border-brand/10 pt-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-brand mb-3">
                    {{ $isDisbursementStage ? 'Disbursement actions' : 'Management actions' }}
                </p>
                @include('admin.loan-applications._workflow_actions')
            </div>
        @endif
    </div>

    @include('admin.loan-applications._loan-link')
    @include('admin.loan-applications.review._contract')
</div>
