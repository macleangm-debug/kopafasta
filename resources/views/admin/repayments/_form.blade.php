{{-- Repayment form. Expects $record, $loans, $channels, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Payment">
    <x-admin.select name="loan_id"               label="Loan"             :options="$loans"   :value="$r?->loan_id" required placeholder="— Select loan —" />
    <x-admin.select name="status"                label="Status"           :options="$statuses" :value="$r?->status ?? 'pending'" required />
    <x-admin.input  name="reference"             label="Reference"        :value="$r?->reference" placeholder="Auto-generated if blank" />
    <x-admin.select name="channel"               label="Channel"          :options="$channels" :value="$r?->channel ?? 'mpesa'" required />
    <x-admin.input  name="amount"                label="Amount"           :value="$r?->amount" money required />
    <x-admin.input  name="paid_at"               label="Paid at"          :value="optional($r?->paid_at)->format('Y-m-d')" type="date" />
</x-admin.step>

<x-admin.step title="Allocation">
    <x-admin.input  name="principal_component"   label="Principal"        :value="$r?->principal_component" money />
    <x-admin.input  name="interest_component"    label="Interest"         :value="$r?->interest_component" money />
    <x-admin.input  name="penalty_component"     label="Penalty"          :value="$r?->penalty_component" money />
    <x-admin.input  name="repayment_schedule_id" label="Schedule ID"      :value="$r?->repayment_schedule_id" type="number" help="Optional installment link" />
</x-admin.step>
