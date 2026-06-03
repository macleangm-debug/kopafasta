{{-- Reconciliation form. Expects $record, $settlements, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Period">
    <x-admin.select name="settlement_id"  label="Settlement"    :options="$settlements" :value="$r?->settlement_id" placeholder="— None —" />
    <x-admin.select name="status"         label="Status"        :options="$statuses"    :value="$r?->status ?? 'pending'" required />
    <x-admin.input  name="period_start"   label="Period start"  :value="optional($r?->period_start)->format('Y-m-d')" type="date" required />
    <x-admin.input  name="period_end"     label="Period end"    :value="optional($r?->period_end)->format('Y-m-d')"   type="date" required />
</x-admin.step>

<x-admin.step title="Totals">
    <x-admin.input  name="system_total"   label="System total"  :value="$r?->system_total" money required />
    <x-admin.input  name="bank_total"     label="Bank total"    :value="$r?->bank_total" money required />
    <x-admin.input  name="variance"       label="Variance"      :value="$r?->variance" money help="Auto-calculated if blank" />
    <x-admin.input  name="reconciled_at"  label="Reconciled at" :value="optional($r?->reconciled_at)->format('Y-m-d')" type="date" />
    <div class="md:col-span-2">
        <x-admin.textarea name="notes" label="Notes" :value="$r?->notes" rows="2" />
    </div>
</x-admin.step>
