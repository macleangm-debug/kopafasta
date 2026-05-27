@php($r = $record ?? null)
@php($perms = is_array($r?->permissions) ? implode("\n", $r->permissions) : '')
<x-admin.step title="Role">
    <x-admin.input  name="code" label="Code" :value="$r?->code" required placeholder="e.g. loan_officer" />
    <x-admin.input  name="name" label="Name" :value="$r?->name" required />
    <x-admin.select name="is_system" label="System role" :options="['1'=>'Yes','0'=>'No']" :value="(string)($r?->is_system ?? '0')" />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="2" />
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Permissions (one per line)</label>
        <textarea name="permissions_text" rows="6" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 font-mono" placeholder="loans.view&#10;loans.approve&#10;customers.create">{{ old('permissions_text', $perms) }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Each line is one permission slug.</p>
    </div>
</x-admin.step>
