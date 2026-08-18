<div class="space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Upcoming payments</h3>
        <p class="text-xs text-gray-500 mt-0.5">Use this to remind the borrower what is due next. Overdue rows are due now.</p>
    </div>

    @include('admin.loans._servicing', ['servicingPanel' => 'summary'])

    @php
        $rows = $servicing['upcoming_rows'] ?? collect();
    @endphp
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Next instalments</h4>
        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500">No remaining instalments on the schedule.</p>
        @else
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($rows as $row)
                    @php
                        $remaining = max(0, (float) $row->total_due - (float) $row->amount_paid);
                        $overdue = $loan && app(\App\Services\ActiveLoanServicingService::class)->isOverdue($row);
                        $days = $row->due_date ? (int) now()->startOfDay()->diffInDays($row->due_date->copy()->startOfDay(), false) : null;
                    @endphp
                    <li class="py-3 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">#{{ $row->installment_no }} · {{ optional($row->due_date)->format('d M Y') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                @if ($overdue)
                                    {{ abs((int) $days) }} day{{ abs((int) $days) === 1 ? '' : 's' }} overdue
                                @elseif ($days !== null)
                                    {{ $days === 0 ? 'Due today' : $days.' day'.($days === 1 ? '' : 's').' left' }}
                                @endif
                                @if ((float) $row->amount_paid > 0 && $remaining > 0)
                                    · Partial {{ format_money((float) $row->amount_paid) }} paid
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold tabular-nums {{ $overdue ? 'text-red-700' : 'text-gray-900' }}">{{ format_money($remaining) }}</p>
                            <p class="text-[10px] uppercase tracking-widest {{ $overdue ? 'text-red-600' : 'text-gray-500' }}">
                                {{ $overdue ? 'Due now' : ($remaining < (float) $row->total_due ? 'Remaining' : 'Upcoming') }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
