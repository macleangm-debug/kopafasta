{{-- Shared Vendor form. Expects $record, $statuses, $categories --}}
@php($r = $record ?? null)

<x-admin.step title="Basic info">
    <x-admin.input  name="vendor_number" label="Vendor number" :value="$r?->vendor_number" placeholder="Auto-generated if blank" />
    <x-admin.input  name="name"          label="Name"          :value="$r?->name"          required />
    <x-admin.select name="category"      label="Category"      :options="$categories"      :value="$r?->category" required />
    <x-admin.select name="status"        label="Status"        :options="$statuses"        :value="$r?->status ?? 'active'" required />
</x-admin.step>

<x-admin.step title="Contact">
    <x-admin.input  name="phone"         label="Phone"         :value="$r?->phone"         placeholder="+255…" />
    <x-admin.input  name="email"         label="Email"         :value="$r?->email"         type="email" />
    <div class="md:col-span-2">
        <x-admin.textarea name="address" label="Address" :value="$r?->address" rows="2" />
    </div>
</x-admin.step>
