{{-- Settlement form. Expects $record, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Settlement">
    <x-admin.input  name="reference"           label="Reference"          :value="$r?->reference"          placeholder="Auto-generated if blank" />
    <x-admin.input  name="partner"             label="Partner"            :value="$r?->partner"            required placeholder="e.g. M-Pesa, NMB" />
    <x-admin.input  name="settlement_date"     label="Settlement date"    :value="optional($r?->settlement_date)->format('Y-m-d')" type="date" required />
    <x-admin.select name="status"              label="Status"             :options="$statuses" :value="$r?->status ?? 'pending'" required />
</x-admin.step>

<x-admin.step title="Amounts">
    <x-admin.input  name="gross_amount"        label="Gross amount"       :value="$r?->gross_amount" type="number" required />
    <x-admin.input  name="fees"                label="Fees"               :value="$r?->fees" type="number" />
    <x-admin.input  name="net_amount"          label="Net amount"         :value="$r?->net_amount" type="number" help="Auto-calculated if blank" />
    <x-admin.input  name="transactions_count"  label="Transactions count" :value="$r?->transactions_count" type="number" />
    <div class="md:col-span-2">
        <x-admin.textarea name="notes" label="Notes" :value="$r?->notes" rows="2" />
    </div>
</x-admin.step>
