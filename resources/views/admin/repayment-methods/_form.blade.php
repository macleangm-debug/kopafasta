@php($r = $record ?? null)
<x-admin.step title="Repayment method">
    <x-admin.input  name="code"     label="Code"    :value="$r?->code" required />
    <x-admin.input  name="name"     label="Name"    :value="$r?->name" required />
    <x-admin.select name="channel"  label="Channel" :options="$channels" :value="$r?->channel ?? 'mobile_money'" required />
    <x-admin.input  name="fixed_fee" label="Fixed fee" money :decimals="2" :value="$r?->fixed_fee ?? '0'" />
    <x-admin.input  name="percentage_fee" label="Percentage fee (0.01 = 1%)" type="number" step="0.0001" :value="$r?->percentage_fee ?? '0'" />
    <x-admin.select name="auto_reconcile" label="Auto reconcile" :options="['1'=>'Yes','0'=>'No']" :value="(string)($r?->auto_reconcile ?? '0')" />
    <x-admin.select name="is_active" label="Status"  :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
</x-admin.step>
