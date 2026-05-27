@php($r = $record ?? null)
<x-admin.step title="Department">
    <x-admin.input  name="code" label="Code" :value="$r?->code" required placeholder="e.g. CR-OPS" />
    <x-admin.input  name="name" label="Name" :value="$r?->name" required />
    <x-admin.select name="branch_id"    label="Branch"   :options="$branches" :value="$r?->branch_id" placeholder="—" />
    <x-admin.select name="head_user_id" label="Head"     :options="$users"    :value="$r?->head_user_id" placeholder="—" />
    <x-admin.select name="is_active"    label="Status"   :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="3" />
    </div>
</x-admin.step>
