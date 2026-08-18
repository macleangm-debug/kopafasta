@php
    $suggestedPay = (float) (($servicing['amount_in_arrears'] ?? 0) > 0
        ? $servicing['amount_in_arrears']
        : ($servicing['next_due_amount'] ?? $loan->outstanding_balance));
@endphp

<div class="space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">What they owe today</h3>
        <p class="text-xs text-gray-500 mt-0.5">Principal, interest, penalty and recovery charges. Staff never records cash here — ask the borrower to pay through the payment gate.</p>
    </div>

    <x-loan-balance-breakdown
        :breakdown="$servicing['balance_breakdown'] ?? app(\App\Services\LoanBalanceService::class)->breakdown($loan)"
        :recovery-charges="app(\App\Services\RecoveryChargesService::class)->breakdownForLoan($loan)"
        :expanded="true"
    />

    @if ($penaltyPolicy)
        <p class="text-xs text-gray-600 rounded-xl bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
            Missed instalments accrue penalty after a {{ $penaltyPolicy->graceDaysAfterDefault }}-day grace,
            at {{ format_number($penaltyPolicy->penaltyRatePercent, 2) }}%
            {{ $penaltyPolicy->penaltyBasis === 'per_day' ? 'per day' : str_replace('_', ' ', $penaltyPolicy->penaltyBasis) }}
            on the overdue balance, capped at {{ format_number($penaltyPolicy->penaltyCapPercent, 0) }}% of overdue.
            A smaller payment is applied penalty → interest → principal, oldest instalment first. Anything still unpaid stays overdue and keeps attracting penalty.
        </p>
    @endif

    <dl class="grid sm:grid-cols-2 gap-3 text-sm">
        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Loan number</dt>
            <dd class="font-mono font-semibold text-gray-900 mt-1">{{ $loan->loan_number }}</dd>
        </div>
        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Disbursed</dt>
            <dd class="font-semibold text-gray-900 mt-1">{{ optional($loan->disbursement_date ?? $record->disbursed_at)->format('d M Y') ?? '—' }}</dd>
        </div>
        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Principal</dt>
            <dd class="font-semibold text-gray-900 mt-1">{{ format_money((float) $loan->principal_amount) }}</dd>
        </div>
        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Tenure</dt>
            <dd class="font-semibold text-gray-900 mt-1">{{ $loan->tenure_months }} months</dd>
        </div>
    </dl>

    @include('admin.loans._payment_request', ['loan' => $loan, 'suggestedPay' => $suggestedPay, 'openPaymentRequests' => $openPaymentRequests])
</div>
