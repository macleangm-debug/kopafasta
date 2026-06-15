{{-- Shared partner form. Expects $record, $statuses, $categories --}}
@php
    $r = $record ?? null;
    $category = old('category', $r?->category ?? ($defaultCategory ?? 'supplier'));
@endphp

<div x-data="{ category: @js($category) }">
    <x-admin.step title="Partner type">
        <p class="md:col-span-2 text-sm text-gray-600">
            Selected type: <span class="font-semibold text-gray-900 capitalize" x-text="category.replace(/_/g, ' ')"></span>
            @if (! $r)
                · <a href="{{ route('admin.vendors.create') }}" class="text-amber-700 hover:underline">Change type</a>
            @endif
        </p>
    </x-admin.step>

    <x-admin.step title="Basic info">
        <x-admin.input  name="vendor_number" label="Partner code" :value="$r?->vendor_number" placeholder="Auto-generated if blank" />
        <x-admin.input  name="name"          label="Name"          :value="$r?->name"          required />
        <x-admin.select name="category"      label="Category"      :options="$categories"      :value="$category" required x-model="category" />
        <x-admin.select name="status"        label="Status"        :options="$statuses"        :value="$r?->status ?? 'active'" required />
    </x-admin.step>

    <x-admin.step title="Contact">
        <x-admin.input  name="phone"         label="Phone"         :value="$r?->phone"         placeholder="+255…" />
        <x-admin.input  name="email"         label="Email"         :value="$r?->email"         type="email" />
        <div class="md:col-span-2">
            <x-admin.textarea name="address" label="Address / coverage area" :value="$r?->address" rows="2"
                              placeholder="For valuers: list regions and vehicle types covered" />
        </div>
    </x-admin.step>

    <div x-show="category === 'supplier'" x-cloak>
        <x-admin.step title="Supplier settings">
            <p class="md:col-span-2 text-xs text-gray-500 mb-2">Deposit markup base is configured under Settings → Asset lending.</p>
            <x-admin.select name="supplier_type" label="Supplier type" :options="config('asset_lending.supplier_types')" :value="$r?->supplier_type ?? config('asset_lending.default_supplier_type')" />
            <x-admin.input name="deposit_markup_percent" label="Deposit markup (%)" type="number" step="0.01" :value="$r?->deposit_markup_percent ?? $r?->markup_percent" />
        </x-admin.step>
    </div>

    <div x-show="category === 'affiliate'" x-cloak>
        <x-admin.step title="Affiliate program">
            <x-admin.input name="affiliate_code" label="Promo / affiliate code" :value="$r?->affiliate_code" placeholder="Auto-generated for affiliates" />
            <x-admin.input name="registration_discount_percent" label="Registration discount (%)" type="number" step="0.01" :value="$r?->registration_discount_percent ?? config('affiliates.default_registration_discount_percent')" />
            <x-admin.input name="application_discount_percent" label="Application discount (%)" type="number" step="0.01" :value="$r?->application_discount_percent ?? config('affiliates.default_application_discount_percent')" />
            <x-admin.input name="affiliate_commission_percent" label="Commission (%)" type="number" step="0.01" :value="$r?->affiliate_commission_percent ?? config('affiliates.default_commission_percent')" />
        </x-admin.step>
    </div>

    <div x-show="['debt_collector','call_center','towing','auctioneer','legal_partner','gps_installer','insurance'].includes(category)" x-cloak>
        <x-admin.step title="Service rates">
            <x-admin.input name="partner_cost" label="Default partner cost (optional)" type="number" step="0.01" :value="$r?->partner_cost" />
            <x-admin.input name="markup_percent" label="Markup (%)" type="number" step="0.01" :value="$r?->markup_percent" />
            <x-admin.input name="recovery_commission_percent" label="Recovery commission (%)" type="number" step="0.01" :value="$r?->recovery_commission_percent" />
            <x-admin.input name="recovery_markup_percent" label="Company markup (%)" type="number" step="0.01" :value="$r?->recovery_markup_percent" />
        </x-admin.step>
    </div>
</div>
