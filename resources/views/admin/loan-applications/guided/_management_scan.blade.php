@php
    $mgmt = app(\App\Services\GuidedApprovalService::class)->managementNext($record);
    $checklist = $mgmt['checklist'] ?? [];
    $next = $mgmt['condition'] ?? null;
@endphp
<div class="rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3">
    <p class="text-[11px] font-bold uppercase tracking-widest text-brand">Post-approval scan</p>
    <p class="text-sm text-slate-700">Committee approved this facility. Management does not re-underwrite it. Complete the configured conditions through to contract and disbursement.</p>
    @if (! empty($mgmt['authority_reason']))
        <p class="text-sm font-semibold text-slate-900">{{ $mgmt['authority_reason'] }}</p>
    @endif
    <ul class="text-sm space-y-1">
        @foreach ($checklist as $row)
            <li class="flex justify-between gap-2">
                <span>{{ $row['label'] ?? '' }}</span>
                <span class="font-semibold {{ ! empty($row['complete']) ? 'text-emerald-800' : 'text-amber-800' }}">
                    {{ ! empty($row['complete']) ? 'Done' : ($row['status'] ?? 'Open') }}
                </span>
            </li>
        @endforeach
    </ul>
    <p class="text-sm font-semibold text-slate-900">{{ $mgmt['what_happens_next'] }}</p>
    @if ($next)
        <p class="text-xs text-slate-600">Continue Post-Approval at: {{ $next['label'] ?? '' }}</p>
    @endif
    <a href="{{ $mgmt['href'] }}" class="inline-flex rounded-xl bg-brand text-white text-sm font-bold px-4 py-2.5">{{ $mgmt['cta'] }}</a>
    <p>
        <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'checklist']) }}"
           class="text-xs font-semibold text-slate-600 underline">View full credit file</a>
    </p>
</div>
