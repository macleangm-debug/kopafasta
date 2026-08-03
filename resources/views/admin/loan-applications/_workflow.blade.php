@php
    $currentStage = $record->current_stage ?? 'submitted';
    $stages = ['submitted', 'screening', 'credit_appraisal', 'pre_approval', 'approval', 'disbursement'];
    $currentIndex = array_search($currentStage, $stages, true);
    if ($currentStage === 'rejected') {
        $currentIndex = false;
    }
    $showHistory = $showHistory ?? false;
    $showStepper = $showStepper ?? true;
    $isOpsStage = in_array($currentStage, ['approval', 'post_approval_fees', 'awaiting_disbursement_details', 'contract_generation', 'disbursement'], true);
@endphp

@if ($showStepper)
<div id="review-workflow" class="scroll-mt-24 mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">
                {{ $isOpsStage ? 'Post-approval progress' : 'Application workflow' }}
            </h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Current stage:
                <span class="font-semibold text-gray-800">{{ $workflow->stageLabel($currentStage) }}</span>
            </p>
        </div>
        @if ($currentStage === 'rejected')
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-red-100 text-red-800">Rejected</span>
            @php
                $rejectLabel = app(\App\Services\LoanRejectionReasonService::class)->formatReasonsForBorrower(
                    $record->rejection_reason_codes,
                    $record->rejection_reason_code,
                    $record->rejection_reason,
                );
                $rejectAdvice = app(\App\Services\LoanRejectionReasonService::class)->resolveBorrowerAdvice(
                    $record->rejection_advice_code,
                    $record->rejection_advice,
                );
            @endphp
            @if ($rejectLabel)
                <span class="text-xs text-red-700">Reason: {{ $rejectLabel }}</span>
            @endif
            @if ($rejectAdvice)
                <span class="text-xs text-gray-600">Advice: {{ $rejectAdvice }}</span>
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
                        'bg-amber-50 text-amber-900 border-brand/40 ring-2 ring-amber-200' => $active,
                        'bg-gray-50 text-gray-600 border-gray-200' => ! $done && ! $active,
                    ])>
                        @if ($done)
                            <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            <span @class([
                                'size-4 shrink-0 rounded-full grid place-items-center text-[10px] font-bold',
                                'bg-brand-gold text-brand' => $active,
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

    @php
        $actionsEmbeddedInRecommendation = in_array($currentStage, ['screening', 'credit_appraisal', 'pre_approval', 'approval'], true);
    @endphp

    @if ($availableActions->isNotEmpty() && ! $actionsEmbeddedInRecommendation)
        <div class="border-t border-gray-100 pt-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Available actions</p>
            @include('admin.loan-applications._workflow_actions')
        </div>
    @elseif ($availableActions->isNotEmpty() && $actionsEmbeddedInRecommendation)
        <p class="text-sm text-gray-500 border-t border-gray-100 pt-4">
            Workflow actions for this stage are in the <a href="#review-recommendation" class="font-semibold text-brand hover:underline">decision panel</a> above.
        </p>
    @elseif ($currentStage !== 'rejected' && $currentStage !== 'disbursement')
        <p class="text-sm text-gray-500 border-t border-gray-100 pt-4">No workflow actions available for your role at this stage.</p>
    @endif
</div>
@endif

@if ($showHistory)
<div id="review-history" class="scroll-mt-24 mt-6 grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Stage history</h3>
        @if ($stageHistory->isEmpty())
            <p class="text-sm text-gray-500">No stage changes recorded yet.</p>
        @else
            <ul class="space-y-4">
                @foreach ($stageHistory as $entry)
                    <li class="flex gap-3">
                        <div class="mt-1 size-2 rounded-full bg-brand-gold shrink-0"></div>
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
@endif
