{{-- Shared Vendor form. Expects $record, $statuses, $categories --}}
@php($r = $record ?? null)

<x-admin.step title="Partner roles">
    <p class="md:col-span-2 text-xs text-gray-500 mb-2">Select all roles this partner performs. The first selected role is used as the primary category for legacy flows.</p>
    @php($selectedRoles = old('roles', $r?->partnerRoles() ?? []))
    <div class="md:col-span-2 grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
        @foreach ($roleOptions ?? [] as $key => $label)
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                <input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, $selectedRoles, true))>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</x-admin.step>

<x-admin.step title="Basic info">
    <x-admin.input  name="vendor_number" label="Vendor number" :value="$r?->vendor_number" placeholder="Auto-generated if blank" />
    <x-admin.input  name="name"          label="Name"          :value="$r?->name"          required />
    <x-admin.select name="category"      label="Category"      :options="$categories"      :value="$r?->category ?? ($defaultCategory ?? null)" required />
    <x-admin.select name="status"        label="Status"        :options="$statuses"        :value="$r?->status ?? 'active'" required />
</x-admin.step>

<x-admin.step title="Contact">
    <x-admin.input  name="phone"         label="Phone"         :value="$r?->phone"         placeholder="+255…" />
    <x-admin.input  name="email"         label="Email"         :value="$r?->email"         type="email" />
    <div class="md:col-span-2">
        <x-admin.textarea name="address" label="Address" :value="$r?->address" rows="2" />
    </div>
</x-admin.step>

<x-admin.step title="Supplier deposit markup">
    <p class="md:col-span-2 text-xs text-gray-500 mb-2">Example: supplier deposit 6M + 10% markup → customer deposit 6.6M. Markup base is configured under Settings → Asset lending.</p>
    <x-admin.select name="supplier_type" label="Supplier type" :options="config('asset_lending.supplier_types')" :value="$r?->supplier_type ?? config('asset_lending.default_supplier_type')" />
    <x-admin.input name="deposit_markup_percent" label="Deposit markup (%)" type="number" step="0.01" :value="$r?->deposit_markup_percent ?? $r?->markup_percent" />
    <x-admin.input name="partner_cost" label="Default partner cost (optional)" type="number" step="0.01" :value="$r?->partner_cost" />
    <x-admin.input name="markup_percent" label="General markup (%)" type="number" step="0.01" :value="$r?->markup_percent" />
</x-admin.step>

<x-admin.step title="Affiliate program">
    <x-admin.input name="affiliate_code" label="Affiliate code" :value="$r?->affiliate_code" placeholder="Auto-generated for affiliates" />
    <x-admin.input name="registration_discount_percent" label="Registration discount (%)" type="number" step="0.01" :value="$r?->registration_discount_percent ?? config('affiliates.default_registration_discount_percent')" />
    <x-admin.input name="application_discount_percent" label="Application discount (%)" type="number" step="0.01" :value="$r?->application_discount_percent ?? config('affiliates.default_application_discount_percent')" />
    <x-admin.input name="affiliate_commission_percent" label="Commission (%)" type="number" step="0.01" :value="$r?->affiliate_commission_percent ?? config('affiliates.default_commission_percent')" />
</x-admin.step>

<x-admin.step title="Recovery partner rates">
    <p class="md:col-span-2 text-xs text-gray-500 mb-2">Optional overrides for call center, debt collector, towing, auctioneer, legal, and GPS recovery partners. Leave blank to use recovery policy defaults.</p>
    <x-admin.input name="recovery_commission_percent" label="Recovery commission (%)" type="number" step="0.01" :value="$r?->recovery_commission_percent" />
    <x-admin.input name="recovery_markup_percent" label="Recovery company markup (%)" type="number" step="0.01" :value="$r?->recovery_markup_percent" />
</x-admin.step>
