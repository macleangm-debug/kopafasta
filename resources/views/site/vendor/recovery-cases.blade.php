<x-site.vendor-layout title="Recovery cases" active="recovery">
    @include('site.vendor._recovery-kpi', ['kpi' => $recoveryKpi, 'wallet' => $recoveryWallet, 'compact' => true])

    @php
        $tabs = [
            'all'         => 'All',
            'assigned'    => 'Assigned',
            'in_progress' => 'In progress',
            'completed'   => 'Completed',
            'escalated'   => 'Escalated',
        ];
        $current = $status ?: 'all';
        $badge = fn ($s) => match ($s) {
            'assigned'    => 'bg-amber-100 text-amber-700',
            'in_progress' => 'bg-indigo-100 text-indigo-700',
            'completed'   => 'bg-emerald-100 text-emerald-700',
            'escalated'   => 'bg-red-100 text-red-700',
            default       => 'bg-gray-100 text-gray-600',
        };
    @endphp

    <h1 class="text-2xl font-extrabold mb-1">Recovery cases</h1>
    <p class="text-sm text-gray-500 mb-5">Collection cases assigned to your recovery partner account.</p>

    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($tabs as $k => $label)
            <a href="{{ route('site.partner.recovery-cases', $k === 'all' ? [] : ['status' => $k]) }}"
               class="px-3 py-1.5 rounded-full text-xs font-semibold border
                      {{ $current === $k ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($assignments->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">No recovery cases assigned yet.</div>
    @else
        <div class="space-y-3">
            @foreach ($assignments as $assignment)
                @php
                    $loan = $assignment->arrearCase?->loan;
                    $customer = $loan?->customer;
                    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                    $slaBreached = $assignment->sla_due_at && $assignment->sla_due_at->isPast() && in_array($assignment->status, ['assigned', 'in_progress'], true);
                @endphp
                <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Case #{{ $assignment->arrear_case_id }}</p>
                            <p class="text-lg font-bold text-gray-900">{{ $name ?: 'Borrower' }}</p>
                            <p class="text-sm text-gray-600">
                                {{ display_label($assignment->partner_type, 'recovery_partner_type') }}
                                @if ($loan?->loan_number)
                                    · {{ $loan->loan_number }}
                                @endif
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $badge($assignment->status) }}">
                            {{ display_label($assignment->status, 'record_status') }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                        <div>
                            <dt class="text-[11px] uppercase text-gray-500">Assigned</dt>
                            <dd class="font-semibold">{{ $assignment->assigned_at?->format('d M Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase text-gray-500">SLA due</dt>
                            <dd class="font-semibold {{ $slaBreached ? 'text-red-700' : '' }}">
                                {{ $assignment->sla_due_at?->format('d M Y') ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase text-gray-500">Outstanding at assign</dt>
                            <dd class="font-semibold">{{ format_money($assignment->original_outstanding) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase text-gray-500">Your commission</dt>
                            <dd class="font-semibold">{{ format_money($assignment->commission_earned) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-2">
                        <a href="{{ route('site.partner.recovery-case', $assignment) }}"
                           class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                            Open case
                        </a>
                        @if ($assignment->vendorTask)
                            <a href="{{ route('site.partner.task', $assignment->vendorTask) }}"
                               class="inline-flex items-center rounded-lg bg-white border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                Linked task
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $assignments->links() }}</div>
    @endif
</x-site.vendor-layout>
