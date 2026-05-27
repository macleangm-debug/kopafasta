{{-- Loan application form. Expects $record, $customers, $products, $branches, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Applicant">
    <x-admin.select name="customer_id"              label="Customer"          :options="$customers" :value="$r?->customer_id" required placeholder="— Select customer —" />
    <x-admin.select name="loan_product_id"          label="Loan product"      :options="$products"  :value="$r?->loan_product_id" required placeholder="— Select product —" />
    <x-admin.select name="branch_id"                label="Branch"            :options="$branches"  :value="$r?->branch_id" placeholder="— None —" />
    <x-admin.input  name="application_number"       label="Application #"     :value="$r?->application_number" placeholder="Auto-generated if blank" />
</x-admin.step>

<x-admin.step title="Loan request">
    <x-admin.input  name="requested_amount"         label="Requested amount"  :value="$r?->requested_amount" type="number" required />
    <x-admin.input  name="requested_tenure_months"  label="Requested tenure (months)" :value="$r?->requested_tenure_months" type="number" required />
    <x-admin.input  name="recommended_amount"       label="Recommended amount" :value="$r?->recommended_amount" type="number" />
    <div class="md:col-span-2">
        <x-admin.textarea name="purpose"          label="Purpose"          :value="$r?->purpose"          rows="2" />
    </div>
</x-admin.step>

<x-admin.step title="Workflow">
    <x-admin.select name="status"                   label="Status"            :options="$statuses"  :value="$r?->status ?? 'draft'" required />
    <x-admin.input  name="current_stage"            label="Current stage"     :value="$r?->current_stage" />
    <div class="md:col-span-2">
        <x-admin.textarea name="rejection_reason" label="Rejection reason" :value="$r?->rejection_reason" rows="2" />
    </div>
</x-admin.step>
