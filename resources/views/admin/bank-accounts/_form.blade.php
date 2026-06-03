@php($r = $record ?? null)
<x-admin.step title="Bank account">
    <x-admin.input  name="name"           label="Display name" :value="$r?->name" required placeholder="e.g. CRDB Main Operating" />
    <x-admin.input  name="bank_name"      label="Bank name"    :value="$r?->bank_name" required />
    <x-admin.input  name="account_number" label="Account #"    :value="$r?->account_number" required />
    <x-admin.input  name="branch"         label="Bank branch"  :value="$r?->branch" />
    <x-admin.input  name="swift_code"     label="SWIFT/BIC"    :value="$r?->swift_code" />
    <x-admin.input  name="currency"       label="Currency"     :value="$r?->currency ?? 'TZS'" required />
    <x-admin.input  name="opening_balance" label="Opening balance" money :decimals="2" :value="$r?->opening_balance ?? '0'" required />
    <x-admin.select name="gl_account_id"  label="GL account"   :options="$glAccounts" :value="$r?->gl_account_id" placeholder="—" />
    <x-admin.select name="purpose"        label="Purpose"      :options="$purposes" :value="$r?->purpose ?? 'operating'" required />
    <x-admin.select name="is_active"      label="Status"       :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="notes" label="Notes" :value="$r?->notes" rows="2" />
    </div>
</x-admin.step>
