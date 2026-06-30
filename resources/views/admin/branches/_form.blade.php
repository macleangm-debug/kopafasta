{{-- Shared Branch form. Expects $record, $statuses (optional) --}}
@php($r = $record ?? null)

<x-admin.step title="Branch">
    <x-admin.input  name="code"      label="Branch code"  :value="$r?->code"   required placeholder="e.g. BR-001" />
    <x-admin.input  name="name"      label="Name"         :value="$r?->name"   required />
    <x-admin.input  name="region"    label="Region"       :value="$r?->region" />
    <x-admin.select name="is_active" label="Status"       :options="['1' => 'Active', '0' => 'Inactive']" :value="(string) ($r?->is_active ?? '1')" required />
</x-admin.step>

<x-admin.step title="Contact">
    <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
    <x-admin.input  name="email"     label="Email"        :value="$r?->email"  type="email" />
    <div class="md:col-span-2">
        <x-admin.textarea name="address" label="Address" :value="$r?->address" rows="2" />
    </div>
</x-admin.step>
