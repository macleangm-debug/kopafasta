@php($r = $record ?? null)
<x-admin.step title="Mobile money account">
    <x-admin.input  name="name"     label="Display name" :value="$r?->name" required />
    <x-admin.select name="provider" label="Provider"     :options="$providers" :value="$r?->provider" required placeholder="—" />
    <x-admin.input  name="msisdn"   label="MSISDN / Number" :value="$r?->msisdn" required placeholder="+255…" />
    <x-admin.input  name="paybill_number" label="Paybill" :value="$r?->paybill_number" />
    <x-admin.input  name="till_number"    label="Till"    :value="$r?->till_number" />
    <x-admin.input  name="api_username"   label="API username" :value="$r?->api_username" />
    <x-admin.input  name="api_secret"     label="API secret" type="password" :value="$r?->api_secret" placeholder="•••" />
    <x-admin.input  name="environment"    label="Environment" :value="$r?->environment ?? 'production'" />
    <x-admin.input  name="opening_balance" label="Opening balance" money :decimals="2" :value="$r?->opening_balance ?? '0'" />
    <x-admin.select name="gl_account_id" label="GL account" :options="$glAccounts" :value="$r?->gl_account_id" placeholder="—" />
    <x-admin.select name="purpose"   label="Purpose"      :options="$purposes" :value="$r?->purpose ?? 'both'" required />
    <x-admin.select name="is_active" label="Status"       :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
</x-admin.step>
