{{-- Shared Loan Product form. Expects $record --}}
@php($r = $record ?? null)

<x-admin.step title="Basics">
    <x-admin.input  name="code"                label="Product code"        :value="$r?->code"     required placeholder="e.g. BIZ-30" />
    <x-admin.input  name="name"                label="Name (English)"    :value="$r?->name"     required />
    <x-admin.input  name="name_sw"             label="Name (Swahili)"    :value="$r?->name_sw"  placeholder="Optional — shown when locale is Swahili" />
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

@include('admin.loan-products._pricing-fields')
@include('admin.loan-products._rate-tiers-fields', ['record' => $r, 'rateTiers' => $rateTiers ?? null])
@include('admin.loan-products._post-approval-fees-fields')
<x-admin.number-format-script />
@include('admin.loan-products._document-templates-fields')

<x-admin.step title="Requirements">
    <x-admin.select name="requires_collateral" label="Requires collateral" :options="['1' => 'Yes', '0' => 'No']" :value="(string) ($r?->requires_collateral ?? '0')" required />
    <x-admin.select name="requires_guarantor"  label="Requires guarantor"  :options="['1' => 'Yes', '0' => 'No']" :value="(string) ($r?->requires_guarantor ?? '0')" required />
</x-admin.step>

<x-admin.step title="Documents" id="documents">
    @include('admin.loan-products._requirements-fields')
</x-admin.step>
