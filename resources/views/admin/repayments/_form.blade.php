{{-- Repayment form. Expects $record, $loans, $channels, $statuses, $approvalRequired --}}
@php($r = $record ?? null)

<x-admin.step title="Payment">
    @if ($approvalRequired ?? repayment_approval_required())
        <p class="md:col-span-2 text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2 mb-2">
            Maker-checker is enabled. This repayment will stay pending until a different user approves and posts it to the ledger.
        </p>
    @else
        <p class="md:col-span-2 text-xs text-gray-500 mb-2">
            Enter the amount received. Principal, interest, and penalty are allocated automatically when posted.
        </p>
    @endif
    <x-admin.select name="loan_id"               label="Loan"             :options="$loans"   :value="$r?->loan_id" required placeholder="— Select loan —" />
    <x-admin.select name="status"                label="Status"           :options="$statuses" :value="$r?->status ?? 'pending'" required />
    <x-admin.input  name="reference"             label="Reference"        :value="$r?->reference" placeholder="Auto-generated if blank" />
    <x-admin.select name="channel"               label="Channel"          :options="$channels" :value="$r?->channel ?? 'mpesa'" required />
    <x-admin.input  name="amount"                label="Amount"           :value="$r?->amount" money required />
    <x-admin.input  name="paid_at"               label="Paid at"          :value="optional($r?->paid_at)->format('Y-m-d')" type="date" />
</x-admin.step>
