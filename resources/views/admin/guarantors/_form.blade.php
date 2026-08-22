{{-- Guarantor form. Expects $record, $relationships --}}
@php($r = $record ?? null)

<x-admin.step title="Identity">
    <x-admin.input  name="first_name"   label="First name"   :value="$r?->first_name"  required />
    <x-admin.input  name="last_name"    label="Last name"    :value="$r?->last_name"   required />
    <x-admin.national-id-input name="national_id" :value="$r?->national_id" />
    <x-admin.select name="relationship" label="Relationship" :options="$relationships" :value="$r?->relationship" placeholder="— Select —" />
</x-admin.step>

<x-admin.step title="Contact">
    <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" required />
    <x-admin.input  name="email"        label="Email"        :value="$r?->email"       type="email" />
    <div class="md:col-span-2">
        <x-admin.textarea name="address" label="Address" :value="$r?->address" rows="2" />
    </div>
</x-admin.step>
