@php($r = $record ?? null)
<x-admin.step title="Disbursement method">
    <x-admin.input  name="code"     label="Code"    :value="$r?->code" required placeholder="e.g. mpesa_b2c" />
    <x-admin.input  name="name"     label="Name"    :value="$r?->name" required />
    <x-admin.select name="channel"  label="Channel" :options="$channels" :value="$r?->channel ?? 'bank_transfer'" required />
    <x-admin.input  name="fixed_fee" label="Fixed fee" type="number" step="0.01" :value="$r?->fixed_fee ?? '0'" />
    <x-admin.input  name="percentage_fee" label="Percentage fee (0.01 = 1%)" type="number" step="0.0001" :value="$r?->percentage_fee ?? '0'" />
    <x-admin.input  name="priority" label="Priority" type="number" :value="$r?->priority ?? '0'" />
    <x-admin.select name="is_active" label="Status"  :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
</x-admin.step>
