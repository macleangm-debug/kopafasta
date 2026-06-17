{{-- Shared Lender form. Expects $record, $types, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Lender">
    <x-admin.input  name="code"           :label="__('admin.capital_partner.reference')" :value="$r?->code" required placeholder="e.g. MACLEANS" />
    <x-admin.input  name="name"           label="Name"            :value="$r?->name"           required />
    <x-admin.select name="type"           label="Type"            :options="$types"            :value="$r?->type ?? 'bank'" required />
    <x-admin.select name="status"         label="Status"          :options="$statuses"         :value="$r?->status ?? 'active'" required />
    <x-admin.input  name="credit_limit"   label="Credit limit (TZS)" :value="$r?->credit_limit" money />
    <x-admin.input  name="allocation_priority" label="Allocation priority" type="number" min="1" max="9999"
                    :value="$r?->allocation_priority" placeholder="1 = highest (priority strategy)" />
</x-admin.step>

<x-admin.step title="Contact">
    <x-admin.input  name="contact_person" label="Contact person"  :value="$r?->contact_person" />
    <x-admin.input  name="phone"          label="Phone"           :value="$r?->phone"          placeholder="+255…" />
    <x-admin.input  name="email"          label="Email"           :value="$r?->email"          type="email" />
    <div class="md:col-span-2">
        <x-admin.textarea name="address" label="Address" :value="$r?->address" rows="2" />
    </div>
</x-admin.step>
