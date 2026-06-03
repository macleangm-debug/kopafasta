{{-- Shared User form. Expects $record, $branches, $roles --}}
@php($r = $record ?? null)

<x-admin.step title="Identity">
    <x-admin.input  name="name"            label="Full name"      :value="$r?->name"  required />
    <x-admin.input  name="email"           label="Email"          :value="$r?->email" type="email" required />
    <x-admin.input  name="phone"           label="Phone"          :value="$r?->phone" placeholder="+255…" />
    <x-admin.input  name="password"        label="{{ $r ? 'New password (leave blank to keep)' : 'Password' }}" type="password" :required="! $r" />
</x-admin.step>

<x-admin.step title="Role & access">
    <x-admin.select name="role"            label="Role"           :options="$roles"   :value="$r?->role ?? 'officer'" required />
    <x-admin.select name="branch_id"       label="Branch"         :options="$branches" :value="$r?->branch_id" placeholder="— None —" />
    <x-admin.input  name="approval_limit"  label="Approval limit (TZS)" :value="$r?->approval_limit" money />
    <x-admin.select name="is_active"       label="Status"         :options="['1' => 'Active', '0' => 'Inactive']" :value="(string) ($r?->is_active ?? '1')" required />
</x-admin.step>
