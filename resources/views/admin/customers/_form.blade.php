{{-- Shared Customer form fields. Expects $record (Customer|null), $branches, $types, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Identity">
    <x-admin.input name="first_name"      label="First name"        :value="$r?->first_name" required />
    <x-admin.input name="last_name"       label="Last name"         :value="$r?->last_name"  required />
    <x-admin.input name="national_id"     label="National ID"       :value="$r?->national_id" />
    <x-admin.input name="date_of_birth"   label="Date of birth"     :value="optional($r?->date_of_birth)->format('Y-m-d')" type="date" />
    <x-admin.input name="customer_number" label="Customer number"   :value="$r?->customer_number" placeholder="Auto-generated if blank" />
    <x-admin.select name="type"           label="Customer type"     :options="$types"        :value="$r?->type ?? 'individual'" required />
</x-admin.step>

<x-admin.step title="Contact">
    <x-admin.input name="phone"           label="Phone"             :value="$r?->phone"      required placeholder="+255…" />
    <x-admin.input name="email"           label="Email"             :value="$r?->email"      type="email" />
    <div class="md:col-span-2">
        <x-admin.textarea name="address" label="Address" :value="$r?->address" rows="2" />
    </div>
</x-admin.step>

<x-admin.step title="Employment & Branch">
    <x-admin.select name="branch_id"      label="Branch"            :options="$branches"     :value="$r?->branch_id" placeholder="— None —" />
    <x-admin.select name="status"         label="Status"            :options="$statuses"     :value="$r?->status ?? 'active'" required />
    <x-admin.input name="employment_type" label="Employment type"   :value="$r?->employment_type" />
    <x-admin.input name="business_name"   label="Business name"     :value="$r?->business_name" />
    <x-admin.input name="monthly_income"  label="Monthly income (TZS)" :value="$r?->monthly_income" money />
</x-admin.step>
