<div x-data="{ expanded: null }">
@if ($notice)
    <p class="mb-3 text-sm font-medium text-emerald-800 bg-emerald-50 ring-1 ring-emerald-200 rounded-lg px-4 py-2">{{ $notice }}</p>
@endif
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search partner or task type…">
    <x-slot:headers>
        @if ($rows->isNotEmpty())
            <th class="px-5 py-3 w-10"></th>
            <x-admin.th :sort="$sort" :direction="$direction" col="task_type"    label="Task" />
            <th class="px-5 py-3">Partner</th>
            <th class="px-5 py-3">Related to</th>
            <x-admin.th :sort="$sort" :direction="$direction" col="status"       label="Status" />
            <x-admin.th :sort="$sort" :direction="$direction" col="due_at"       label="SLA due" />
            <x-admin.th :sort="$sort" :direction="$direction" col="completed_at" label="Completed" />
        @endif
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            @php
                $overdue = $r->due_at && $r->due_at->isPast() && ! in_array($r->status, ['completed', 'cancelled'], true);
            @endphp
            <tr class="hover:bg-gray-50 cursor-pointer" @click="expanded = expanded === {{ $r->id }} ? null : {{ $r->id }}">
                <td class="px-5 py-3 text-gray-400">
                    <svg class="w-4 h-4 transition" :class="expanded === {{ $r->id }} && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5V5z"/></svg>
                </td>
                <td class="px-5 py-3 font-medium">{{ display_label((string) $r->task_type, 'vendor_task_type') }}</td>
                <td class="px-5 py-3 text-gray-600">
                    @if ($r->vendor)
                        <a href="{{ route('admin.partners.show', $r->vendor) }}" class="text-brand hover:underline" @click.stop>{{ $r->vendor->name }}</a>
                    @else
                        —
                    @endif
                </td>
                <td class="px-5 py-3 text-xs text-gray-600">
                    @if ($r->loanApplication)
                        <a href="{{ route('admin.loan-applications.show', $r->loanApplication) }}" class="text-brand hover:underline" @click.stop>
                            App #{{ $r->loanApplication->application_number ?? $r->loanApplication->id }}
                        </a>
                    @elseif ($r->loan)
                        <a href="{{ route('admin.loans.show', $r->loan) }}" class="text-brand hover:underline" @click.stop>
                            Loan {{ $r->loan->loan_number ?? $r->loan->id }}
                        </a>
                    @else
                        —
                    @endif
                </td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'assigned'    => 'bg-blue-100 text-blue-800',
                        'in_progress' => 'bg-amber-100 text-amber-800',
                        'completed'   => 'bg-emerald-100 text-emerald-800',
                        'failed'      => 'bg-red-100 text-red-800',
                        'cancelled'   => 'bg-gray-100 text-gray-700',
                    ]" />
                </td>
                <td class="px-5 py-3 text-gray-500">
                    {{ $r->due_at?->format('Y-m-d') ?? '—' }}
                    @if ($overdue)
                        <span class="block text-[10px] font-semibold text-red-600 uppercase tracking-wide">Overdue</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->completed_at?->format('Y-m-d') ?? '—' }}</td>
            </tr>
            <tr x-show="expanded === {{ $r->id }}" x-cloak class="bg-brand-muted/20">
                <td colspan="7" class="px-5 py-4">
                    @php
                        $priority = $r->priorityMeta();
                        $priorityClass = match ($priority['tone']) {
                            'red' => 'bg-red-100 text-red-800',
                            'amber' => 'bg-amber-100 text-amber-800',
                            'indigo' => 'bg-indigo-100 text-indigo-800',
                            default => 'bg-gray-100 text-gray-700',
                        };
                        $application = $r->loanApplication;
                        $loan = $r->loan;
                        $product = $application?->product ?? $loan?->product;
                        $coverage = '—';
                        if ($r->vendor) {
                            $coverage = ($r->vendor->coverage_type ?? 'regions') === 'nationwide'
                                ? 'Nationwide'
                                : (implode(', ', array_values(array_filter($r->vendor->regions ?? []))) ?: 'No regions set');
                        }
                        $fileAmount = $loan?->approved_amount ?? $loan?->principal_amount ?? $application?->requested_amount;
                        $borrowerRegion = $application?->customer?->region ?? $loan?->customer?->region;
                    @endphp
                    <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5 space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ display_label((string) $r->task_type, 'vendor_task_type') }}
                                    · {{ $r->borrowerName() }}
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Task #{{ $r->id }}
                                    · What the partner must do, who to visit, and when it is due.
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide {{ $priorityClass }}">
                                {{ $priority['label'] }} priority
                            </span>
                        </div>

                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Borrower</p>
                                <p class="mt-1 text-gray-800">{{ $r->borrowerName() }}</p>
                                <p class="text-xs text-gray-500">{{ $r->borrowerPhone() }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Visit location</p>
                                <p class="mt-1 text-gray-800">{{ $r->location ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Asset / vehicle</p>
                                <p class="mt-1 text-gray-800">{{ $r->vehicle_details ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Related file</p>
                                <p class="mt-1 text-gray-800">
                                    @if ($application)
                                        <a href="{{ route('admin.loan-applications.show', $application) }}" class="text-brand hover:underline" @click.stop>
                                            App #{{ $application->application_number ?? $application->id }}
                                        </a>
                                        <span class="block text-xs text-gray-500">
                                            {{ display_label((string) $application->status, 'application_status') }}
                                            @if ($application->current_stage)
                                                · {{ display_label((string) $application->current_stage, 'application_stage') }}
                                            @endif
                                        </span>
                                    @elseif ($loan)
                                        <a href="{{ route('admin.loans.show', $loan) }}" class="text-brand hover:underline" @click.stop>
                                            Loan {{ $loan->loan_number ?? $loan->id }}
                                        </a>
                                        <span class="block text-xs text-gray-500">{{ display_label((string) $loan->status, 'loan_status') }}</span>
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Product / amount</p>
                                <p class="mt-1 text-gray-800">{{ $product?->name ?? $product?->code ?? '—' }}</p>
                                <p class="text-xs text-gray-500 tabular-nums">
                                    @if ($fileAmount)
                                        {{ format_money($fileAmount) }}
                                        @if ($loan && $loan->outstanding_balance !== null)
                                            · outstanding {{ format_money($loan->outstanding_balance) }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Partner</p>
                                <p class="mt-1 text-gray-800">
                                    @if ($r->vendor)
                                        <a href="{{ route('admin.partners.show', $r->vendor) }}" class="text-brand hover:underline" @click.stop>{{ $r->vendor->name }}</a>
                                    @else
                                        —
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ display_label((string) ($r->vendor?->category ?? ''), 'vendor_category') }}
                                    · {{ $coverage }}
                                    @if ($r->vendor?->phone)
                                        · {{ $r->vendor->phone }}
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Assigned</p>
                                <p class="mt-1 text-gray-800">{{ $r->created_at?->format('d M Y H:i') ?? '—' }}</p>
                                <p class="text-xs text-gray-500">{{ $r->assigner?->name ? 'by '.$r->assigner->name : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">SLA due</p>
                                <p class="mt-1 text-gray-800">
                                    {{ $r->due_at?->format('d M Y H:i') ?? '—' }}
                                    @if ($overdue)
                                        <span class="text-red-600 font-semibold"> · Overdue</span>
                                    @endif
                                </p>
                                @if ($r->due_at && ! in_array($r->status, ['completed', 'cancelled'], true))
                                    <p class="text-xs {{ $overdue ? 'text-red-600' : 'text-gray-500' }}">{{ $r->due_at->diffForHumans() }}</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Progress</p>
                                <p class="mt-1 text-gray-800">{{ display_label((string) $r->status, 'vendor_task_status') }}</p>
                                <p class="text-xs text-gray-500">
                                    @if ($r->accepted_at) Accepted {{ $r->accepted_at->format('d M Y H:i') }} · @endif
                                    @if ($r->started_at) Started {{ $r->started_at->format('d M Y H:i') }} · @endif
                                    {{ $r->completed_at ? 'Completed '.$r->completed_at->format('d M Y H:i') : 'Not completed' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Partner fee</p>
                                <p class="mt-1 text-gray-800 tabular-nums">{{ format_money($r->fee_amount ?? 0) }}</p>
                                @if ($r->payment)
                                    <p class="text-xs text-gray-500 tabular-nums">{{ format_money($r->payment->amount ?? 0) }} · {{ display_label((string) ($r->payment->status ?? ''), 'vendor_task_status') ?: ($r->payment->status ?? '—') }}</p>
                                @elseif ($r->payment_status)
                                    <p class="text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', (string) $r->payment_status)) }}</p>
                                @endif
                            </div>
                            @if ($r->valuationAssignment)
                                <div>
                                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Valuation</p>
                                    <p class="mt-1 text-gray-800">{{ display_label((string) $r->valuationAssignment->status, 'vendor_task_status') }}</p>
                                    <p class="text-xs text-gray-500 tabular-nums">
                                        Market {{ $r->valuationAssignment->market_value !== null ? format_money($r->valuationAssignment->market_value) : '—' }}
                                        · FSV {{ $r->valuationAssignment->forced_sale_value !== null ? format_money($r->valuationAssignment->forced_sale_value) : '—' }}
                                    </p>
                                </div>
                            @endif
                            @if ($r->recoveryAssignment)
                                <div>
                                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Recovery case</p>
                                    <p class="mt-1 text-gray-800">{{ display_label((string) $r->recoveryAssignment->partner_type, 'recovery_partner_type') }}</p>
                                    <p class="text-xs text-gray-500 tabular-nums">
                                        Outstanding at assign {{ format_money($r->recoveryAssignment->original_outstanding ?? 0) }}
                                        · {{ display_label((string) $r->recoveryAssignment->status, 'vendor_task_status') }}
                                    </p>
                                </div>
                            @endif
                            @if ($r->gps_serial || $r->gps_provider || $r->gps_tracking_url)
                                <div>
                                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">GPS device</p>
                                    <p class="mt-1 text-gray-800">{{ $r->gps_serial ?: '—' }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $r->gps_provider ?: '—' }}
                                        @if ($r->gps_tracking_url)
                                            · <a href="{{ $r->gps_tracking_url }}" class="text-brand hover:underline" @click.stop target="_blank" rel="noopener">Tracking link</a>
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Instructions</p>
                                <p class="mt-1 text-gray-800 whitespace-pre-line">{{ $r->instructions ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Notes</p>
                                <p class="mt-1 text-gray-800 whitespace-pre-line">{{ $r->notes ?: '—' }}</p>
                            </div>
                        </div>

                        @if ($r->relationLoaded('documents') ? $r->documents->isNotEmpty() : false)
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold mb-1">Documents</p>
                                <ul class="flex flex-wrap gap-2">
                                    @foreach ($r->documents as $doc)
                                        <li class="text-xs font-semibold text-brand bg-brand-muted/40 ring-1 ring-brand/15 px-3 py-1.5 rounded-lg">{{ $doc->label ?? $doc->file_path ?? 'Document' }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($canClose[$r->id] ?? false)
                            <div class="pt-1 border-t border-gray-100" @click.stop>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">File closed</p>
                                <p class="mt-1 text-sm text-gray-700">This application is no longer active, so the job should not stay ongoing.</p>
                                <button type="button"
                                        wire:click="close({{ $r->id }})"
                                        class="mt-2 inline-flex items-center rounded-lg bg-gray-800 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-700">
                                    Close job
                                </button>
                            </div>
                        @elseif ($canReassign[$r->id] ?? false)
                            <div class="pt-1 border-t border-gray-100" @click.stop>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Assign another</p>
                                @if (($candidates[$r->id] ?? collect())->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap items-end gap-2">
                                        <select wire:model="reassignTo.{{ $r->id }}"
                                                class="min-w-[16rem] rounded-lg border-gray-300 text-sm">
                                            <option value="">Next eligible partner (auto)</option>
                                            @foreach ($candidates[$r->id] as $vendor)
                                                @php
                                                    $covers = $regionCoverage->covers($vendor, $borrowerRegion ?? null);
                                                @endphp
                                                <option value="{{ $vendor->id }}">
                                                    {{ $vendor->name }}
                                                    @if ($covers)
                                                        · covers region
                                                    @else
                                                        · outside region
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="button"
                                                wire:click="reassign({{ $r->id }})"
                                                class="inline-flex items-center rounded-lg bg-brand px-3 py-2 text-xs font-semibold text-white hover:bg-brand/90">
                                            Assign another
                                        </button>
                                    </div>
                                    @error('reassignTo.'.$r->id)
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                @else
                                    <p class="mt-1 text-sm text-gray-600">No other active partner of this type exists yet. Add one, then assign.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-5 py-2">
                    <x-site.empty-state
                        icon="🧰"
                        title="No partner tasks found"
                        description="No field tasks match your search or filters right now." />
                </td>
            </tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
