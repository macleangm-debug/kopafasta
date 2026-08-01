{{-- Shared Loan Product form. Expects $record --}}
@php
    $r = $record ?? null;
    $cloneOptions = ['' => '— Start blank —'];
    foreach (($cloneSources ?? collect()) as $p) {
        $cloneOptions[(string) $p->id] = $p->name.' ('.$p->code.')';
    }
@endphp

<x-admin.step title="Basics">
    @unless ($r)
        <div class="md:col-span-2 rounded-xl bg-amber-50 ring-1 ring-amber-100 p-4">
            <x-admin.select
                name="clone_from_id"
                label="Same as (copy settings from an existing product)"
                :options="$cloneOptions"
                :value="old('clone_from_id')"
                help="Copies pricing, documents, rates, fees and flags. Change the code/name for the new product."
            />
        </div>
    @endunless
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
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Product image</label>
        <p class="text-xs text-gray-500 mb-2">Shown on borrower cards. If empty, the themed illustration for this product code is used.</p>
        @if ($r?->image_path)
            <img src="{{ asset('storage/'.$r->image_path) }}" alt="" class="mb-3 h-28 w-44 object-cover rounded-xl ring-1 ring-gray-200">
        @endif
        <input type="file" name="image" accept="image/*"
               class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-amber-800 hover:file:bg-amber-100">
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
