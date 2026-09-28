@php
    $contextHeader = match ($pipeline) {
        'under_review', 'system_sorted' => 'Product',
        'committee' => 'Recommended by',
        'approved' => 'Next step',
        'disbursement' => 'Release',
        default => 'Analyst',
    };
    $intake = app(\App\Services\ApplicationIntakeReadinessService::class);
    $autoReject = app(\App\Services\CapacityAutoRejectService::class);
@endphp
<div>
<div class="mb-3 flex flex-wrap items-center gap-2">
    <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white ring-1 ring-gray-200 rounded-lg px-3 py-2">
        <input type="checkbox" wire:model.live="mine" class="rounded border-gray-300 text-brand focus:ring-brand">
        My assigned queue
    </label>
</div>

@if ($pipeline === 'system_sorted')
    <div class="md:hidden space-y-3 mb-4">
        @forelse ($rows as $r)
            @php
                $copy = $intake->operationalCopy($r);
                $pendingCapacity = $autoReject->isPending($r);
                $hoursLeft = $pendingCapacity ? $autoReject->hoursRemaining($r) : null;
                $cta = ($copy['state'] ?? '') === 'ready_for_screening'
                    ? __('admin.intake.initiate_screening')
                    : __('admin.intake.view_file');
            @endphp
            <a href="{{ route('admin.loan-applications.show', $r) }}" class="block rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4">
                <p class="font-mono text-xs font-bold text-slate-900">{{ $r->application_number ?? '—' }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-1">{{ $r->partyLabel() }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ $r->product?->name ?? '—' }}</p>
                <p class="text-xs font-semibold text-slate-800 mt-2">{{ display_label($copy['state'] ?? $r->status, 'application_status') ?: ($copy['state'] ?? $r->status) }}</p>
                <p class="text-[11px] text-slate-600 mt-1">{{ $copy['next_label'] }}</p>
                @if (! empty($copy['reason']))
                    <p class="text-[11px] text-amber-950 mt-1">{{ $copy['reason'] }}</p>
                @endif
                @if ($pendingCapacity)
                    <p class="text-[11px] font-semibold text-amber-900 mt-1">
                        @if ($hoursLeft === 0)
                            {{ __('borrower.loan_profile.capacity_auto_reject_pending_admin_due') }}
                        @else
                            {{ __('borrower.loan_profile.capacity_auto_reject_pending_admin', ['hours' => $hoursLeft ?? '—']) }}
                        @endif
                    </p>
                @endif
                <p class="text-xs font-bold text-brand mt-3">{{ $cta }} →</p>
            </a>
        @empty
            <p class="text-sm text-slate-600">{{ __('admin.intake.system_sorted_empty') }}</p>
        @endforelse
    </div>
    <div class="hidden md:block">
@endif

<x-admin.table-shell :records="$rows" :statuses="$statuses" statusGroup="application_status" searchPlaceholder="Search application #, customer, phone, NIDA, product…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="application_number" label="App #" />
        <x-admin.th :sort="$sort" :direction="$direction" col="customer_id"        label="Customer" />
        <x-admin.th :sort="$sort" :direction="$direction" col="requested_amount"   label="Amount" />
        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $contextHeader }}</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="status"             label="Status" />
        <x-admin.th :sort="$sort" :direction="$direction" col="created_at"         label="Submitted" />
        <th class="px-5 py-2.5 text-right">Actions</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            @php
                $copy = $intake->operationalCopy($r);
                $contextValue = match ($pipeline) {
                    'under_review', 'system_sorted' => $r->product?->name ?? '—',
                    'committee' => $r->recommendedByUser?->name ?? '—',
                    'approved', 'disbursement' => $pipelineStages[$r->id] ?? '—',
                    default => $r->assignedAnalyst?->name ?? '—',
                };
                $displayStatus = $intake->displayStatus($r);
                $displayStage = $intake->displayStage($r);
                $pendingCapacity = in_array($pipeline, ['under_review', 'system_sorted'], true) && $autoReject->isPending($r);
                $hoursLeft = $pendingCapacity ? $autoReject->hoursRemaining($r) : null;
                $g = ($copy['state'] ?? '') === 'awaiting_guarantor' ? $intake->guarantorNomination($r) : null;
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->application_number ?? '—' }}</td>
                <td class="px-5 py-3">
                    {{ $r->partyLabel() }}
                    <div class="text-xs text-gray-500">{{ $r->customer?->phone }}</div>
                    @if ($g)
                        <div class="text-[10px] text-gray-500 mt-1">
                            {{ $g['name'] ?? '—' }} · {{ $copy['next_label'] }}
                            @if (! empty($g['created_at'])) · {{ $g['created_at']->diffForHumans() }} @endif
                        </div>
                    @endif
                </td>
                <td class="px-5 py-3">{{ format_money( ($r->requested_amount ?? 0)) }}</td>
                <td class="px-5 py-3 text-xs text-gray-600">{{ $contextValue }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$displayStatus" group="application_status" :map="[
                        'approved'     => 'bg-emerald-100 text-emerald-800',
                        'pre_approved'   => 'bg-sky-100 text-sky-800',
                        'rejected'       => 'bg-red-100 text-red-800',
                        'rejected_initial_gate' => 'bg-red-100 text-red-800',
                        'in_progress'    => 'bg-blue-100 text-blue-800',
                        'submitted'      => 'bg-amber-100 text-amber-800',
                        'submitted_initial_check' => 'bg-amber-100 text-amber-800',
                        'initial_decision_hold' => 'bg-amber-100 text-amber-800',
                        'ready_for_screening' => 'bg-sky-100 text-sky-800',
                        'under_review'   => 'bg-blue-100 text-blue-800',
                        'awaiting_guarantor' => 'bg-purple-100 text-purple-800',
                        'withdrawn' => 'bg-gray-200 text-gray-700',
                        'expired'            => 'bg-gray-200 text-gray-700',
                    ]" />
                    @if ($pendingCapacity)
                        <div class="mt-1 inline-flex max-w-[14rem] text-[10px] font-semibold leading-snug rounded-md px-1.5 py-1 bg-amber-50 text-amber-900 ring-1 ring-amber-200">
                            @if ($hoursLeft === 0)
                                {{ __('borrower.loan_profile.capacity_auto_reject_pending_admin_due') }}
                            @else
                                {{ __('borrower.loan_profile.capacity_auto_reject_pending_admin', ['hours' => $hoursLeft ?? '—']) }}
                            @endif
                        </div>
                        @if (! empty($copy['reason']))
                            <div class="mt-1 text-[10px] text-amber-950 max-w-[16rem] leading-snug">{{ $copy['reason'] }}</div>
                        @endif
                    @elseif ($pipeline === 'system_sorted' && ! empty($copy['next_label']))
                        <div class="mt-1 text-[10px] text-gray-500 max-w-[16rem] leading-snug">{{ $copy['next_label'] }}</div>
                    @endif
                    @if (! in_array($pipeline, ['approved', 'disbursement'], true)
                        && $displayStage
                        && $displayStage !== $displayStatus
                        && ! in_array($displayStatus, ['withdrawn', 'rejected', 'expired', 'cancelled'], true))
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            {{ display_label($displayStage, 'application_stage') ?: app(\App\Services\LoanApplicationWorkflowService::class)->stageLabel($displayStage) }}
                        </div>
                    @endif
                    @if ($r->status === 'draft')
                        <div class="text-[10px] text-amber-800 mt-0.5">{{ $intake->draftReasonLabel($intake->resolve($r)['draft_reason'] ?? null) }}</div>
                    @endif
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
                <td class="px-5 py-3 text-right">
                    @if ($pipeline === 'under_review')
                        @php $guidedCta = app(\App\Services\ScreeningNextActionService::class)->forApplication($r, auth()->user()); @endphp
                        <a href="{{ $guidedCta['href'] }}" class="text-xs font-medium text-brand hover:text-brand-light">{{ $guidedCta['cta'] }} →</a>
                    @elseif ($pipeline === 'system_sorted')
                        @php
                            $cta = ($copy['state'] ?? '') === 'ready_for_screening'
                                ? __('admin.intake.initiate_screening')
                                : __('admin.intake.view_file');
                        @endphp
                        <a href="{{ route('admin.loan-applications.show', $r) }}" class="text-xs font-medium text-brand hover:text-brand-light">{{ $cta }} →</a>
                    @else
                        <a href="{{ route('admin.loan-applications.show', $r) }}" class="text-xs font-medium text-brand hover:text-brand-light">View →</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No applications found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
@if ($pipeline === 'system_sorted')
    </div>
@endif
</div>
