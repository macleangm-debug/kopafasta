@php($r = $record ?? null)
<x-admin.step title="Approval limit">
    <x-admin.select name="role_code" label="Role"   :options="$roles"   :value="$r?->role_code" required placeholder="—" />
    <x-admin.select name="action"    label="Action" :options="$actions" :value="$r?->action"    required placeholder="—" />
    <x-admin.input  name="min_amount" label="Min amount" money :decimals="2" :value="$r?->min_amount ?? '0'" required />
    <x-admin.input  name="max_amount" label="Max amount" money :decimals="2" :value="$r?->max_amount" required />
    <x-admin.input  name="currency"   label="Currency" :value="$r?->currency ?? 'TZS'" required />
    <x-admin.select name="requires_dual_control" label="Requires dual control" :options="['1'=>'Yes','0'=>'No']" :value="(string)($r?->requires_dual_control ?? '0')" />
    <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
</x-admin.step>
