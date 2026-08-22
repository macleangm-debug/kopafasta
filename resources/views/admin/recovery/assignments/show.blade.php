@php
    $loan = $assignment->arrearCase?->loan;
    $customer = $loan?->customer;
    $borrowerName = trim((string) ($customer?->full_name ?? ''));
    $slaLabel = $assignment->sla_due_at?->format('d M Y H:i') ?? '—';
    if ($assignment->sla_due_at && $assignment->isOpen()) {
        $slaLabel .= $assignment->slaBreached()
            ? ' · overdue'
            : ' · '.$assignment->sla_due_at->diffForHumans();
    }
@endphp

<x-admin.layout
    :title="'Recovery assignment #'.$assignment->id"
    heading=""
    subheading="">

    <x-admin.letterhead
        kicker="Recovery"
        :title="'Recovery assignment #'.$assignment->id"
        :subtitle="($assignment->vendor?->name ?? '—').' · '.display_label($assignment->partner_type, 'recovery_partner_type')">
        <x-slot:actions>
            <a href="{{ route('admin.recovery.assignments.index') }}" class="inline-flex items-center text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">All assignments</a>
        </x-slot:actions>
    </x-admin.letterhead>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Borrower</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Name</dt>
                        <dd class="font-semibold">{{ $borrowerName !== '' ? $borrowerName : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Phone</dt>
                        <dd class="font-semibold">{{ $customer?->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Loan</dt>
                        <dd class="font-mono">{{ $loan?->loan_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Product</dt>
                        <dd>{{ $loan?->product?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Collection case</dt>
                        <dd>
                            @if ($assignment->arrearCase)
                                <a href="{{ route('admin.arrear-cases.show', $assignment->arrearCase) }}" class="text-amber-700 font-semibold">
                                    #{{ $assignment->arrear_case_id }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Original outstanding</dt>
                        <dd class="font-semibold">{{ format_money($assignment->original_outstanding) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Assignment</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Status</dt>
                        <dd>{{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">SLA due</dt>
                        <dd class="{{ $assignment->slaBreached() ? 'text-red-700 font-semibold' : '' }}">{{ $slaLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Assigned</dt>
                        <dd>{{ $assignment->assigned_at?->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Assigned by</dt>
                        <dd>{{ $assignment->assigner?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Partner commission</dt>
                        <dd>{{ format_number($assignment->commission_percent, 1) }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Company markup</dt>
                        <dd>{{ format_number($assignment->company_markup_percent, 1) }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Recovery charge (borrower)</dt>
                        <dd class="font-semibold text-red-700">{{ format_money($assignment->recovery_charge) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Commission earned</dt>
                        <dd class="font-semibold">{{ format_money($assignment->commission_earned) }}</dd>
                    </div>
                </dl>
                @if ($assignment->notes)
                    <p class="mt-4 text-sm text-gray-600 border-t border-gray-100 pt-4">{{ $assignment->notes }}</p>
                @endif
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Activity</h2>
                @forelse ($activity ?? [] as $row)
                    <div class="py-3 border-b border-gray-50 last:border-0">
                        <p class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $row->action_type)) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $row->performer?->name ?? 'System' }}
                            · {{ $row->performed_at?->format('d M Y H:i') }}
                        </p>
                        @if ($row->notes)
                            <p class="text-xs text-gray-600 mt-1">{{ $row->notes }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No reminders or field notes yet.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Partner</h2>
                <p class="font-semibold">{{ $assignment->vendor?->name }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $assignment->vendor?->phone }} · {{ $assignment->vendor?->email }}</p>
                @if ($assignment->vendor?->user_id)
                    <p class="mt-2 text-xs text-emerald-700 font-semibold">Portal login active</p>
                @endif
                <p class="mt-3 text-xs text-gray-500">Last partner reminder: {{ $lastPartnerReminder?->performed_at?->format('d M Y H:i') ?? '—' }}</p>
                <p class="text-xs text-gray-500">Last borrower reminder: {{ $lastBorrowerReminder?->performed_at?->format('d M Y H:i') ?? '—' }}</p>
            </div>

            @if ($assignment->isOpen())
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
                    <h2 class="text-sm font-semibold text-gray-900">Actions</h2>
                    <form method="POST" action="{{ route('admin.recovery.assignments.remind-partner', $assignment) }}">
                        @csrf
                        <button type="submit" class="w-full text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-2 rounded-lg">Remind partner</button>
                    </form>
                    <form method="POST" action="{{ route('admin.recovery.assignments.remind-borrower', $assignment) }}">
                        @csrf
                        <button type="submit" class="w-full text-sm font-semibold text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40 px-3 py-2 rounded-lg">Remind borrower</button>
                    </form>
                    @if ($assignment->status === 'assigned')
                        <form method="POST" action="{{ route('admin.recovery.assignments.start', $assignment) }}">
                            @csrf
                            <button type="submit" class="w-full text-sm font-semibold text-white bg-brand hover:bg-brand-light px-3 py-2 rounded-lg">Mark in progress</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.recovery.assignments.complete', $assignment) }}" class="space-y-2">
                        @csrf
                        <select name="outcome" required class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="resolved">Resolved</option>
                            <option value="promise_to_pay">Promise to pay</option>
                            <option value="unreachable">Unreachable</option>
                            <option value="partial_payment">Partial payment</option>
                        </select>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Notes"></textarea>
                        <button type="submit" class="w-full text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-3 py-2 rounded-lg">Complete case</button>
                    </form>
                    <form method="POST" action="{{ route('admin.recovery.assignments.escalate', $assignment) }}">
                        @csrf
                        <button type="submit" class="w-full text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50 px-3 py-2 rounded-lg">Escalate</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-admin.layout>
