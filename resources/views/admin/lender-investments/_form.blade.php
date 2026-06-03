{{-- Investment form. Expects $record, $lenders, $pools, $loans, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Allocation">
    <x-admin.select name="lender_id"       label="Lender"        :options="$lenders" :value="$r?->lender_id" required placeholder="— Select lender —" />
    <x-admin.select name="funding_pool_id" label="Funding pool"  :options="$pools"   :value="$r?->funding_pool_id" placeholder="— Optional —" />
    <x-admin.select name="loan_id"         label="Loan"          :options="$loans"   :value="$r?->loan_id"   placeholder="— Optional —" />
    <x-admin.select name="status"          label="Status"        :options="$statuses" :value="$r?->status ?? 'pending'" required />
</x-admin.step>

<x-admin.step title="Amounts">
    <x-admin.input  name="reference"       label="Reference"     :value="$r?->reference" placeholder="Auto-generated if blank" />
    <x-admin.input  name="principal"       label="Principal (TZS)" :value="$r?->principal" money required />
    <x-admin.input  name="return_amount"   label="Return amount" :value="$r?->return_amount" money />
    <x-admin.input  name="return_rate"     label="Return rate (0-1)" :value="$r?->return_rate" type="number" help="e.g. 0.18 = 18%" />
    <x-admin.input  name="invested_at"     label="Invested on"   :value="optional($r?->invested_at)->format('Y-m-d')" type="date" />
    <x-admin.input  name="matures_at"      label="Matures on"    :value="optional($r?->matures_at)->format('Y-m-d')"  type="date" />
</x-admin.step>
