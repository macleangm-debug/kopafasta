<x-site.vendor-layout title="Recovery case #{{ $assignment->id }}" active="recovery">
    @php
        $customer = $customer ?? $loan?->customer;
        $borrowerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        $statusBadge = match ($assignment->status) {
            'assigned'    => 'bg-amber-100 text-amber-700',
            'in_progress' => 'bg-indigo-100 text-indigo-700',
            'completed'   => 'bg-emerald-100 text-emerald-700',
            'escalated'   => 'bg-red-100 text-red-700',
            default       => 'bg-gray-100 text-gray-600',
        };
        $slaBreached = $assignment->slaBreached();
        $isOpen = $assignment->isOpen();
    @endphp

    <div class="mb-5">
        <a href="{{ route('site.vendor.recovery-cases') }}" class="text-sm text-indigo-600 hover:underline">← Back to recovery cases</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-6">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Case #{{ $assignment->arrear_case_id }}</p>
            <h1 class="text-2xl font-extrabold">{{ $borrowerName ?: 'Borrower' }}</h1>
            <p class="text-sm text-gray-600 mt-1">
                {{ display_label($assignment->partner_type, 'recovery_partner_type') }}
                @if ($loan?->loan_number) · {{ $loan->loan_number }} @endif
            </p>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
            {{ display_label($assignment->status, 'record_status') }}
        </span>
    </div>

    @if ($slaBreached && $isOpen)
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">SLA breached</p>
            <p class="mt-1">This case was due {{ $assignment->sla_due_at?->format('d M Y') }}. Please update status or contact collections.</p>
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="font-bold mb-3">Case summary</h2>
                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500 text-xs">Borrower</dt>
                        <dd class="font-medium">{{ $borrowerName ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Borrower phone</dt>
                        <dd class="font-medium">{{ $customer?->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Guarantor</dt>
                        <dd class="font-medium">{{ $guarantor_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Guarantor phone</dt>
                        <dd class="font-medium">{{ $guarantor_phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Outstanding at assign</dt>
                        <dd class="font-semibold text-red-700">{{ format_money($assignment->original_outstanding) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Your commission</dt>
                        <dd class="font-semibold">{{ format_money($assignment->commission_earned) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Assigned</dt>
                        <dd class="font-medium">{{ $assignment->assigned_at?->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">SLA due</dt>
                        <dd class="font-medium {{ $slaBreached ? 'text-red-700' : '' }}">
                            {{ $assignment->sla_due_at?->format('d M Y') ?? '—' }}
                            @if ($sla_days_remaining !== null && $isOpen)
                                <span class="text-xs text-gray-500">({{ $sla_days_remaining }} days left)</span>
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 text-xs">Loan / contract ref</dt>
                        <dd class="font-mono font-medium">{{ $loan?->loan_number ?? '—' }}</dd>
                    </div>
                </dl>
                @if ($assignment->notes)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Case notes</p>
                        <p class="text-sm whitespace-pre-line text-gray-700">{{ $assignment->notes }}</p>
                    </div>
                @endif
            </div>

            @if ($isOpen)
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 class="font-bold">Record action</h2>
                        @if ($assignment->status === 'assigned')
                            <form method="POST" action="{{ route('site.vendor.recovery-case.start', $assignment) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-slate-800 text-white text-xs font-semibold px-3 py-2 hover:bg-slate-900">
                                    Start case
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="space-y-4">
                        @foreach ($portal_actions as $actionKey => $action)
                            @php
                                $needsFile = ! empty($action['accepts_file']);
                                $needsNotes = ($action['notes'] ?? null) === 'required';
                                $needsProceeds = ! empty($action['requires_auction_proceeds']);
                                $isResolve = ! empty($action['completes']);
                            @endphp
                            <form method="POST"
                                  action="{{ route('site.vendor.recovery-case.action', $assignment) }}"
                                  enctype="multipart/form-data"
                                  class="rounded-xl border border-gray-200 p-4 {{ $isResolve ? 'border-emerald-200 bg-emerald-50/40' : '' }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $actionKey }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $action['label'] }}</p>
                                        @if ($needsNotes)
                                            <p class="text-[11px] text-gray-500 mt-0.5">Notes required</p>
                                        @endif
                                    </div>
                                    <button type="submit"
                                            class="rounded-lg px-3 py-2 text-xs font-semibold {{ $isResolve ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white' }}">
                                        Submit
                                    </button>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @if ($needsProceeds)
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Auction proceeds (TZS)</label>
                                            <input type="number" name="auction_proceeds" step="0.01" min="0.01" required
                                                   placeholder="Amount received at auction"
                                                   class="w-full rounded-lg border-gray-300 text-sm font-mono">
                                            <p class="text-[11px] text-gray-500 mt-1">Loan balance and recovery costs are settled automatically. Surplus is returned to the borrower.</p>
                                        </div>
                                    @endif
                                    <textarea name="notes" rows="2" maxlength="2000"
                                              @if ($needsNotes && ! $needsFile) required @endif
                                              placeholder="{{ $needsNotes ? 'Enter details…' : 'Optional notes…' }}"
                                              class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                                    @if ($needsFile)
                                        <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf"
                                               class="w-full text-sm rounded-lg border-gray-300">
                                    @endif
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="font-bold mb-3">Activity log</h2>
                @if (($activity ?? collect())->isEmpty())
                    <p class="text-sm text-gray-500">No actions logged yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($activity as $entry)
                            <li class="py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold">{{ ucfirst(str_replace('_', ' ', $entry->action_type)) }}</p>
                                    <p class="text-xs text-gray-500">{{ $entry->performed_at?->format('d M Y H:i') }}</p>
                                </div>
                                @if ($entry->result)
                                    <p class="text-xs text-gray-500 mt-0.5">Result: {{ ucfirst(str_replace('_', ' ', $entry->result)) }}</p>
                                @endif
                                @if ($entry->notes)
                                    <p class="text-xs text-gray-600 mt-1">{{ $entry->notes }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            @if ($assignment->vendorTask)
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="font-bold mb-3">Linked task</h2>
                    <p class="text-sm text-gray-600 mb-3">{{ ucfirst(str_replace('_', ' ', $assignment->vendorTask->task_type)) }}</p>
                    <a href="{{ route('site.vendor.task', $assignment->vendorTask) }}"
                       class="inline-flex rounded-lg bg-indigo-600 text-white text-xs font-semibold px-3 py-2 hover:bg-indigo-700">
                        Open task
                    </a>

                    @if ($assignment->vendorTask->documents->isNotEmpty())
                        <ul class="mt-4 divide-y divide-gray-100 text-sm">
                            @foreach ($assignment->vendorTask->documents as $document)
                                <li class="py-2 flex items-center justify-between gap-2">
                                    <span class="truncate">{{ $document->label }}</span>
                                    <a href="{{ asset('storage/'.$document->file_path) }}" target="_blank" class="text-indigo-600 text-xs hover:underline">View</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-600">
                <p class="font-semibold text-gray-900 mb-2">Reminder</p>
                <p>Commission is calculated from the original outstanding at assignment — not compounded across partners.</p>
            </div>
        </div>
    </div>
</x-site.vendor-layout>
