@php($r = $record ?? null)
<x-admin.step title="Asset details">
    <x-admin.select name="vendor_id" label="Supplier" :options="$suppliers" :value="$r?->vendor_id" placeholder="— optional —" />
    <x-admin.select name="category" label="Category" :options="$categories" :value="$r?->category" required />
    <x-admin.input name="title" label="Title" :value="$r?->title" required />
    <x-admin.input name="slug" label="Slug" :value="$r?->slug" placeholder="Auto-generated if blank" />
    <div class="md:col-span-2"><x-admin.textarea name="description" label="Description" :value="$r?->description" rows="3" /></div>
    <x-admin.input name="supplier_name" label="Supplier display name" :value="$r?->supplier_name" />
</x-admin.step>
<x-admin.step title="Pricing">
    <x-admin.input name="asset_value" label="Asset value" type="number" step="0.01" :value="$r?->asset_value ?? 0" required />
    <x-admin.input name="supplier_deposit" label="Supplier deposit" type="number" step="0.01" :value="$r?->supplier_deposit ?? 0" required />
    <x-admin.input name="deposit_markup_percent" label="Deposit markup (%)" type="number" step="0.01" :value="$r?->deposit_markup_percent ?? 10" />
    <x-admin.input name="weekly_installment" label="Weekly installment" type="number" step="0.01" :value="$r?->weekly_installment ?? 0" required />
    <x-admin.input name="max_tenure_months" label="Max tenure (months)" type="number" :value="$r?->max_tenure_months ?? 12" required />
    <x-admin.select name="is_active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" :value="($r?->is_active ?? true) ? '1' : '0'" />
    @if ($r)
        <div class="md:col-span-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Customer deposit preview: <strong>TZS {{ number_format($r->customer_deposit ?: $r->computeCustomerDeposit()) }}</strong>
        </div>
    @endif
</x-admin.step>
