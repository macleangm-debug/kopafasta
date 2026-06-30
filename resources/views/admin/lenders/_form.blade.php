{{-- Shared Lender form. Expects $record, $types, $statuses, $fundingSources, $kycStatuses --}}
@php($r = $record ?? null)
@php($defaultShare = $defaultRevenueSharePercent ?? 60)

<x-admin.step title="Lender">
    <x-admin.input  name="code"           :label="__('admin.capital_partner.reference')" :value="$r?->code" required placeholder="e.g. MACLEANS" />
    <x-admin.input  name="name"           label="Name"            :value="$r?->name"           required />
    <x-admin.select name="type"           label="Type"            :options="$types"            :value="$r?->type ?? 'bank'" required />
    <x-admin.select name="funding_source"   label="Funding source"  :options="$fundingSources ?? []" :value="$r?->funding_source ?? 'external'" required />
    <x-admin.select name="status"         label="Status"          :options="$statuses"         :value="$r?->status ?? 'active'" required />
    <x-admin.input  name="credit_limit"   label="Credit limit (TZS)" :value="$r?->credit_limit" money />
    <x-admin.input  name="allocation_priority" label="Allocation priority" type="number" min="1" max="9999"
                    :value="$r?->allocation_priority" placeholder="1 = highest (priority strategy)" />
</x-admin.step>

<x-admin.step title="Contact">
    <x-admin.input  name="contact_person" label="Contact person"  :value="$r?->contact_person" />
    <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
    <x-admin.input  name="email"          label="Email"           :value="$r?->email"          type="email" />
    <div class="md:col-span-2">
        <x-admin.textarea name="address" label="Address" :value="$r?->address" rows="2" />
    </div>
</x-admin.step>

<x-admin.step title="Revenue share">
    <x-admin.input name="revenue_share_percent" label="Partner interest share (%)" type="number" step="0.01" min="0" max="100"
                   :value="$r?->revenue_share_percent"
                   placeholder="{{ number_format($defaultShare, 2) }}"
                   help="Leave blank to use the global default ({{ number_format($defaultShare, 2) }}% from Finance settings). Company share is the remainder." />
</x-admin.step>

<x-admin.step title="External partner KYC">
    <p class="md:col-span-2 text-sm text-gray-600 -mt-2 mb-2">Required for external capital partners. Internal balance-sheet lenders can ignore this section.</p>
    <x-admin.input name="registration_number" label="Company registration no." :value="$r?->registration_number" placeholder="BRELA / business registration" />
    <x-admin.input name="tax_id" label="TIN / tax ID" :value="$r?->tax_id" />
    <x-admin.input name="license_number" label="Financial services license" :value="$r?->license_number" placeholder="BoT or relevant regulator" />
    <x-admin.select name="kyc_status" label="KYC status" :options="$kycStatuses ?? []" :value="$r?->kyc_status ?? 'pending'" />
    <x-admin.input name="kyc_verified_at" label="KYC verified on" type="date" :value="optional($r?->kyc_verified_at)->format('Y-m-d')" />
    <div class="md:col-span-2">
        <x-admin.textarea name="kyc_notes" label="KYC notes" :value="$r?->kyc_notes" rows="3" help="Compliance notes, document references, or rejection reasons." />
    </div>
</x-admin.step>
