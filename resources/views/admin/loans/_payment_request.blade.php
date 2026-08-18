@php
    $suggestedPay = $suggestedPay ?? (float) $loan->outstanding_balance;
    $openPaymentRequests = $openPaymentRequests ?? ($loan
        ? \App\Models\CustomerPayment::query()
            ->where('loan_id', $loan->id)
            ->where('payment_type', 'loan_repayment')
            ->where('status', 'awaiting_payment')
            ->latest('id')
            ->get()
        : collect());
    $canAsk = in_array($loan->status, ['active', 'arrears', 'defaulted'], true) && (float) $loan->outstanding_balance > 0;
@endphp

@if ($canAsk)
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-4">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Ask for payment</p>
            <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Create a payment the borrower completes</h3>
            <p class="text-xs text-gray-500 mt-1">Enter any amount — arrears, one instalment, or a catch-up figure. The borrower sees <strong>Make payment</strong> on their loan and pays on payments.show. That amount is then applied penalty → interest → principal.</p>
        </div>

        @if ($openPaymentRequests->isNotEmpty())
            <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-3 space-y-2">
                <p class="text-xs font-semibold text-brand">Open payment requests</p>
                @foreach ($openPaymentRequests as $pay)
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="font-mono text-xs">{{ $pay->reference }}</span>
                        <span class="font-semibold tabular-nums">{{ format_money((float) $pay->amount) }}</span>
                        <span class="text-xs text-gray-500">Awaiting borrower</span>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.loans.payment-requests.store', $loan) }}" class="space-y-3">
            @csrf
            <x-admin.money-input
                name="amount"
                label="Amount (TZS)"
                :value="old('amount', $suggestedPay)"
                :decimals="2"
                required
                help="Defaults to arrears if any, otherwise the next instalment." />
            <div>
                <label for="note" class="block text-xs font-semibold text-gray-700 mb-1">Note to file (optional)</label>
                <input type="text" name="note" id="note" maxlength="500" value="{{ old('note') }}"
                       class="w-full text-sm bg-white border border-brand/15 rounded-xl px-3.5 py-2.5"
                       placeholder="e.g. Catch-up for 3 overdue instalments">
            </div>
            <button type="submit" class="inline-flex items-center text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                Ask for payment
            </button>
        </form>
    </div>
@endif
