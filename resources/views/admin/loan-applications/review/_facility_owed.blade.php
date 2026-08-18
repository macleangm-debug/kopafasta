@php
    $breakdown = $servicing['balance_breakdown'] ?? app(\App\Services\LoanBalanceService::class)->breakdown($loan);
    $totalOutstanding = (float) ($breakdown['total_outstanding'] ?? $loan->outstanding_balance ?? 0);
    $missedCount = (int) ($servicing['overdue_installments'] ?? 0);
    $missedAmount = (float) ($servicing['amount_in_arrears'] ?? 0);
    $penalty = (float) ($breakdown['penalty_outstanding'] ?? 0);
    $recovery = (float) ($breakdown['recovery_costs'] ?? 0);
    $suggestedPay = $missedAmount > 0
        ? $missedAmount
        : (float) ($servicing['next_due_amount'] ?? $totalOutstanding);
@endphp

<div class="space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">What they owe</h3>
        <p class="text-xs text-gray-500 mt-0.5">Due now is missed instalments. Total outstanding also includes penalty and recovery once posted.</p>
    </div>

    <div class="grid sm:grid-cols-3 gap-3">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Total outstanding</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ format_money($totalOutstanding) }}</p>
            <p class="text-xs text-gray-500 mt-1">Principal + interest + penalty + recovery</p>
        </div>
        <div class="rounded-2xl px-4 py-4 ring-1 {{ $missedCount > 0 ? 'bg-red-50 ring-red-100' : 'bg-white ring-brand/10' }}">
            <p class="text-[10px] uppercase tracking-widest {{ $missedCount > 0 ? 'text-red-600' : 'text-gray-500' }}">Missed instalments</p>
            <p class="text-2xl font-bold mt-1 tabular-nums {{ $missedCount > 0 ? 'text-red-800' : 'text-gray-900' }}">{{ format_money($missedAmount) }}</p>
            <p class="text-xs mt-1 {{ $missedCount > 0 ? 'text-red-700' : 'text-gray-500' }}">
                {{ $missedCount === 0 ? 'None overdue' : $missedCount.' missed · unpaid scheduled amount' }}
            </p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Next instalment</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">
                {{ ! empty($servicing['next_due_amount']) ? format_money((float) $servicing['next_due_amount']) : '—' }}
            </p>
            <p class="text-xs text-gray-500 mt-1">
                @if (! empty($servicing['next_due_date']))
                    {{ \Illuminate\Support\Carbon::parse($servicing['next_due_date'])->format('d M Y') }}
                @else
                    No upcoming instalment
                @endif
            </p>
        </div>
    </div>

    @if ($missedCount > 0 && ($servicing['overdue_rows'] ?? collect())->isNotEmpty())
        <ul class="rounded-2xl bg-white ring-1 ring-red-100 divide-y divide-red-50 text-sm">
            @foreach ($servicing['overdue_rows'] as $row)
                @php $remaining = max(0, (float) $row->total_due - (float) $row->amount_paid); @endphp
                <li class="px-4 py-2.5 flex items-center justify-between gap-3">
                    <span class="text-gray-700">#{{ $row->installment_no }} · {{ optional($row->due_date)->format('d M Y') }}</span>
                    <span class="font-semibold tabular-nums text-red-800">{{ format_money($remaining) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <x-loan-balance-breakdown
        :breakdown="$breakdown"
        :recovery-charges="app(\App\Services\RecoveryChargesService::class)->breakdownForLoan($loan)"
        :expanded="$missedCount > 0 || $penalty > 0 || $recovery > 0"
    />

    @if ($penaltyPolicy)
        <p class="text-xs text-gray-500">
            Penalty starts after {{ $penaltyPolicy->graceDaysAfterDefault }} days,
            {{ format_number($penaltyPolicy->penaltyRatePercent, 2) }}%
            {{ $penaltyPolicy->penaltyBasis === 'per_day' ? 'per day' : str_replace('_', ' ', $penaltyPolicy->penaltyBasis) }},
            capped at {{ format_number($penaltyPolicy->penaltyCapPercent, 0) }}%.
        </p>
    @endif

    @include('admin.loans._payment_request', [
        'loan' => $loan,
        'suggestedPay' => $suggestedPay,
        'openPaymentRequests' => $openPaymentRequests,
    ])
</div>
