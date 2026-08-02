<div x-data="{ expanded: null }">
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
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Notes</p>
                            <p class="mt-1 text-gray-800">{{ $r->notes ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Assigned</p>
                            <p class="mt-1 text-gray-800">{{ $r->created_at?->format('d M Y H:i') ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">SLA</p>
                            <p class="mt-1 text-gray-800">
                                {{ $r->due_at?->format('d M Y H:i') ?? '—' }}
                                @if ($overdue)
                                    <span class="text-red-600 font-semibold"> · Overdue</span>
                                @endif
                            </p>
                        </div>
                        @if ($r->payment)
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Payment</p>
                                <p class="mt-1 text-gray-800 tabular-nums">{{ format_money($r->payment->amount ?? 0) }} · {{ $r->payment->status ?? '—' }}</p>
                            </div>
                        @endif
                        @if ($r->relationLoaded('documents') ? $r->documents->isNotEmpty() : false)
                            <div class="sm:col-span-2">
                                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold mb-1">Documents</p>
                                <ul class="flex flex-wrap gap-2">
                                    @foreach ($r->documents as $doc)
                                        <li class="text-xs font-semibold text-brand bg-white ring-1 ring-brand/15 px-3 py-1.5 rounded-lg">{{ $doc->label ?? $doc->file_path ?? 'Document' }}</li>
                                    @endforeach
                                </ul>
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
