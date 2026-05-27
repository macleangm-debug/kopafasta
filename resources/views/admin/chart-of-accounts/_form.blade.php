@php($r = $record ?? null)
<x-admin.step title="GL account">
    <x-admin.input  name="code" label="Code" :value="$r?->code" required placeholder="e.g. 1100" />
    <x-admin.input  name="name" label="Name" :value="$r?->name" required />
    <x-admin.select name="type" label="Type" :options="$types" :value="$r?->type" required placeholder="—" />
    <x-admin.select name="parent_id" label="Parent account" :options="$parents" :value="$r?->parent_id" placeholder="— (top-level)" />
    <x-admin.input  name="opening_balance" label="Opening balance" type="number" step="0.01" :value="$r?->opening_balance ?? '0'" required />
    <x-admin.input  name="currency" label="Currency" :value="$r?->currency ?? 'TZS'" required />
    <x-admin.input  name="category" label="Category" :value="$r?->category" placeholder="current_asset, long_term, etc." />
    <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="2" />
    </div>
</x-admin.step>
