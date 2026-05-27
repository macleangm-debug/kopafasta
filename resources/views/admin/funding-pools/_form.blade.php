{{-- Funding pool form. Expects $record, $lenders, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Pool">
    <x-admin.select name="lender_id"        label="Lender"            :options="$lenders" :value="$r?->lender_id" required placeholder="— Select lender —" />
    <x-admin.input  name="name"             label="Pool name"         :value="$r?->name" required />
    <x-admin.input  name="currency"         label="Currency"          :value="$r?->currency ?? 'TZS'" required placeholder="TZS" />
    <x-admin.select name="status"           label="Status"            :options="$statuses" :value="$r?->status ?? 'draft'" required />
</x-admin.step>

<x-admin.step title="Capital">
    <x-admin.input  name="amount_committed" label="Amount committed"  :value="$r?->amount_committed" type="number" required />
    <x-admin.input  name="amount_deployed"  label="Amount deployed"   :value="$r?->amount_deployed"  type="number" />
    <x-admin.input  name="expected_yield"   label="Expected yield (0-1)" :value="$r?->expected_yield" type="number" help="e.g. 0.18 = 18%" />
    <x-admin.input  name="start_date"       label="Start date"        :value="optional($r?->start_date)->format('Y-m-d')" type="date" />
    <x-admin.input  name="end_date"         label="End date"          :value="optional($r?->end_date)->format('Y-m-d')"   type="date" />
</x-admin.step>
