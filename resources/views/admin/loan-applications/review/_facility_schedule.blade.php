@php
    $today = $today ?? \Carbon\Carbon::today();
    $penaltyOutstanding = (float) ($servicing['balance_breakdown']['penalty_outstanding'] ?? 0);
@endphp

<div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-700">Repayment schedule</h3>
            <p class="text-xs text-gray-500 mt-0.5">Each row shows remaining, how late it is, and whether a partial payment already landed.</p>
        </div>
        <div class="text-xs text-gray-500 text-right">
            {{ $schedule->count() }} installments · Paid {{ format_money($sumPaid) }} / {{ format_money($sumTotal) }}
            @if ($penaltyOutstanding > 0)
                <div class="text-red-700 font-semibold mt-0.5">Penalty on file {{ format_money($penaltyOutstanding) }}</div>
            @endif
        </div>
    </div>
    @if ($schedule->isEmpty())
        <p class="text-sm text-gray-500">No schedule on file yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-2 pr-4">#</th>
                        <th class="text-left py-2 pr-4">Due date</th>
                        <th class="text-right py-2 pr-4">Principal</th>
                        <th class="text-right py-2 pr-4">Interest</th>
                        <th class="text-right py-2 pr-4">Total due</th>
                        <th class="text-right py-2 pr-4">Paid</th>
                        <th class="text-right py-2 pr-4">Remaining</th>
                        <th class="text-left py-2 pr-4">Timing</th>
                        <th class="text-left py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($schedule as $row)
                        @php
                            $remaining = max(0, (float) $row->total_due - (float) $row->amount_paid);
                            $effectiveStatus = $row->status;
                            $isPaid = in_array($row->status, ['paid'], true) || $remaining <= 0;
                            if ($isPaid) {
                                $effectiveStatus = 'paid';
                            } elseif ($row->due_date && \Carbon\Carbon::parse($row->due_date)->lt($today) && $remaining > 0) {
                                $effectiveStatus = 'overdue';
                            } elseif ((float) $row->amount_paid > 0) {
                                $effectiveStatus = 'partial';
                            }
                            $days = $row->due_date ? (int) $today->diffInDays(\Carbon\Carbon::parse($row->due_date)->startOfDay(), false) : null;
                            $timing = '—';
                            if ($isPaid) {
                                $timing = 'Settled';
                            } elseif ($days === null) {
                                $timing = '—';
                            } elseif ($days < 0) {
                                $timing = abs($days).'d overdue';
                            } elseif ($days === 0) {
                                $timing = 'Due today';
                            } else {
                                $timing = $days.'d left';
                            }
                        @endphp
                        <tr>
                            <td class="py-2 pr-4 font-mono text-xs">{{ $row->installment_no }}</td>
                            <td class="py-2 pr-4">{{ optional($row->due_date)->format('d M Y') }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ format_money((float) $row->principal_due) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ format_money((float) $row->interest_due) }}</td>
                            <td class="py-2 pr-4 text-right font-semibold tabular-nums">{{ format_money((float) $row->total_due) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ format_money((float) $row->amount_paid) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums {{ $remaining > 0 && $effectiveStatus === 'overdue' ? 'text-red-700 font-semibold' : '' }}">{{ format_money($remaining) }}</td>
                            <td class="py-2 pr-4 text-xs {{ $effectiveStatus === 'overdue' ? 'text-red-700 font-semibold' : 'text-gray-600' }}">{{ $timing }}</td>
                            <td class="py-2">
                                <span @class([
                                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase',
                                    'bg-emerald-100 text-emerald-800' => $effectiveStatus === 'paid',
                                    'bg-amber-100 text-amber-800'     => $effectiveStatus === 'partial',
                                    'bg-red-100 text-red-800'         => $effectiveStatus === 'overdue',
                                    'bg-gray-100 text-gray-700'       => $effectiveStatus === 'pending',
                                ])>{{ $effectiveStatus }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200">
                        <td colspan="2" class="py-2 pr-4 text-right text-xs uppercase text-gray-500">Totals</td>
                        <td class="py-2 pr-4 text-right font-bold tabular-nums">{{ format_money($sumPrin) }}</td>
                        <td class="py-2 pr-4 text-right font-bold tabular-nums">{{ format_money($sumInt) }}</td>
                        <td class="py-2 pr-4 text-right font-bold tabular-nums">{{ format_money($sumTotal) }}</td>
                        <td class="py-2 pr-4 text-right font-bold tabular-nums">{{ format_money($sumPaid) }}</td>
                        <td class="py-2 pr-4 text-right font-bold tabular-nums">{{ format_money(max(0, $sumTotal - $sumPaid)) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
