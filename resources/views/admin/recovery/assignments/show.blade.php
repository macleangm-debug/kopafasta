@php
    $loan = $assignment->arrearCase?->loan;
    $customer = $loan?->customer;
@endphp

<x-admin.layout
    :title="'Recovery assignment #'.$assignment->id"
    :heading="'Recovery assignment #'.$assignment->id"
    :subheading="($assignment->vendor?->name ?? '—').' · '.display_label($assignment->partner_type, 'recovery_partner_type')">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-4">
        <a href="{{ route('admin.recovery.assignments.index') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← Recovery assignments</a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Case details</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Collection case</dt>
                        <dd>
                            <a href="{{ route('admin.arrear-cases.show', $assignment->arrearCase) }}" class="text-amber-700 font-semibold">
                                #{{ $assignment->arrear_case_id }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Loan</dt>
                        <dd class="font-mono">{{ $loan?->loan_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Borrower</dt>
                        <dd>{{ trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Original outstanding</dt>
                        <dd class="font-semibold">{{ format_money($assignment->original_outstanding) }}</dd>
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
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">SLA due</dt>
                        <dd class="{{ $assignment->slaBreached() ? 'text-red-700 font-semibold' : '' }}">
                            {{ $assignment->sla_due_at?->format('d M Y H:i') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Status</dt>
                        <dd>{{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</dd>
                    </div>
                </dl>
                @if ($assignment->notes)
                    <p class="mt-4 text-sm text-gray-600 border-t border-gray-100 pt-4">{{ $assignment->notes }}</p>
                @endif
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
            </div>

            @if (in_array($assignment->status, ['assigned', 'in_progress']))
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
                    <h2 class="text-sm font-semibold text-gray-900">Actions</h2>
                    @if ($assignment->status === 'assigned')
                        <form method="POST" action="{{ route('admin.recovery.assignments.start', $assignment) }}">
                            @csrf
                            <button type="submit" class="w-full text-sm font-semibold text-white bg-slate-800 hover:bg-slate-900 px-3 py-2 rounded-lg">Mark in progress</button>
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
