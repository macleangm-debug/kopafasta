@php
    $contextHeader = match ($pipeline) {
        'under_review' => 'Product',
        'committee' => 'Recommended by',
        'approved' => 'Next step',
        'disbursement' => 'Release',
        default => 'Analyst',
    };
@endphp
<div>
<div class="mb-3 flex flex-wrap items-center gap-2">
    <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white ring-1 ring-gray-200 rounded-lg px-3 py-2">
        <input type="checkbox" wire:model.live="mine" class="rounded border-gray-300 text-brand focus:ring-brand">
        My assigned queue
    </label>
</div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" statusGroup="application_status" searchPlaceholder="Search application #, customer…">
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
                $contextValue = match ($pipeline) {
                    'under_review' => $r->product?->name ?? '—',
                    'committee' => $r->recommendedByUser?->name ?? '—',
                    'approved', 'disbursement' => $pipelineStages[$r->id] ?? '—',
                    default => $r->assignedAnalyst?->name ?? '—',
                };
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->application_number ?? '—' }}</td>
                <td class="px-5 py-3">
                    {{ trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')) ?: '—' }}
                    <div class="text-xs text-gray-500">{{ $r->customer?->phone }}</div>
                </td>
                <td class="px-5 py-3">{{ format_money( ($r->requested_amount ?? 0)) }}</td>
                <td class="px-5 py-3 text-xs text-gray-600">{{ $contextValue }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" group="application_status" :map="[
                        'approved'     => 'bg-emerald-100 text-emerald-800',
                        'pre_approved'   => 'bg-sky-100 text-sky-800',
                        'rejected'       => 'bg-red-100 text-red-800',
                        'in_progress'    => 'bg-blue-100 text-blue-800',
                        'submitted'      => 'bg-amber-100 text-amber-800',
                        'under_review'   => 'bg-blue-100 text-blue-800',
                        'awaiting_guarantor' => 'bg-purple-100 text-purple-800',
                        'expired'            => 'bg-gray-200 text-gray-700',
                    ]" />
                    @php
                        $autoReject = app(\App\Services\CapacityAutoRejectService::class);
                        $pendingCapacity = in_array($pipeline, ['under_review', 'system_sorted'], true) && $autoReject->isPending($r);
                        $hoursLeft = $pendingCapacity ? $autoReject->hoursRemaining($r) : null;
                    @endphp
                    @if ($pendingCapacity)
                        <div class="mt-1 inline-flex max-w-[14rem] text-[10px] font-semibold leading-snug rounded-md px-1.5 py-1 bg-amber-50 text-amber-900 ring-1 ring-amber-200">
                            @if ($hoursLeft === 0)
                                {{ __('borrower.loan_profile.capacity_auto_reject_pending_admin_due') }}
                            @else
                                {{ __('borrower.loan_profile.capacity_auto_reject_pending_admin', ['hours' => $hoursLeft ?? '—']) }}
                            @endif
                        </div>
                    @endif
                    @if (! in_array($pipeline, ['approved', 'disbursement'], true))
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            {{ display_label($r->current_stage, 'application_stage') }}
                        </div>
                    @endif
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.loan-applications.show', $r) }}" class="text-xs font-medium text-brand hover:text-brand-light">View →</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No applications found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
