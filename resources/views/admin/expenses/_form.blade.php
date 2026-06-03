{{-- Expense form. Expects $record, $branches, $vendors, $statuses, $methods --}}
@php($r = $record ?? null)

<x-admin.step title="Details">
    <x-admin.select name="branch_id"      label="Branch"        :options="$branches" :value="$r?->branch_id" placeholder="— None —" />
    <x-admin.select name="vendor_id"      label="Vendor"        :options="$vendors"  :value="$r?->vendor_id" placeholder="— None —" />
    <x-admin.input  name="category"       label="Category"      :value="$r?->category" required placeholder="e.g. Fuel, Rent, Towing" />
    <x-admin.select name="status"         label="Status"        :options="$statuses" :value="$r?->status ?? 'pending'" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="2" />
    </div>
</x-admin.step>

<x-admin.step title="Amount">
    <x-admin.input  name="amount"         label="Amount"        :value="$r?->amount" money required />
    <x-admin.input  name="currency"       label="Currency"      :value="$r?->currency ?? 'TZS'" required />
    <x-admin.input  name="expense_date"   label="Expense date"  :value="optional($r?->expense_date)->format('Y-m-d')" type="date" required />
    <x-admin.select name="payment_method" label="Payment method" :options="$methods" :value="$r?->payment_method" placeholder="— Select —" />
    <x-admin.input  name="reference"      label="Reference"     :value="$r?->reference" />
</x-admin.step>
