{{-- Shared Loan Product form. Expects $record --}}
@php($r = $record ?? null)

<x-admin.step title="Basics">
    <x-admin.input  name="code"                label="Product code"        :value="$r?->code"     required placeholder="e.g. BIZ-30" />
    <x-admin.input  name="name"                label="Name"                :value="$r?->name"     required />
    <x-admin.select name="category"            label="Category"
                    :options="[
                        'business_loan' => 'Business loan',
                        'salary_loan'   => 'Salary loan',
                        'agriculture'   => 'Agriculture',
                        'asset_finance' => 'Asset finance',
                        'emergency'     => 'Emergency',
                    ]"
                    :value="$r?->category" />
    <x-admin.select name="status"              label="Visibility"          :options="['active' => 'Active', 'coming_soon' => 'Coming soon', 'inactive' => 'Inactive']" :value="$r?->status ?? 'active'" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="3" />
    </div>
</x-admin.step>

<x-admin.step title="Pricing & tenure">
    <x-admin.input  name="interest_rate"       label="Interest rate (decimal, e.g. 0.05 = 5%)" type="number" step="0.0001" :value="$r?->interest_rate" required />
    <x-admin.input  name="tenure_min_months"   label="Min tenure (months)" type="number" :value="$r?->tenure_min_months" required />
    <x-admin.input  name="tenure_max_months"   label="Max tenure (months)" type="number" :value="$r?->tenure_max_months" required />
    <x-admin.input  name="min_amount"          label="Min amount (TZS)"    type="number" step="0.01" :value="$r?->min_amount" required />
    <x-admin.input  name="max_amount"          label="Max amount (TZS)"    type="number" step="0.01" :value="$r?->max_amount" required />
</x-admin.step>

<x-admin.step title="Requirements">
    <x-admin.select name="requires_collateral" label="Requires collateral" :options="['1' => 'Yes', '0' => 'No']" :value="(string) ($r?->requires_collateral ?? '0')" required />
    <x-admin.select name="requires_guarantor"  label="Requires guarantor"  :options="['1' => 'Yes', '0' => 'No']" :value="(string) ($r?->requires_guarantor ?? '0')" required />
</x-admin.step>
