{{-- Shared User form. Expects $record, $branches, $roles, $departments --}}
@php
    $r = $record ?? null;
    $selectedTeamIds = old('department_ids');
    if ($selectedTeamIds === null) {
        $selectedTeamIds = $r?->departments
            ? $r->departments->pluck('id')->all()
            : array_values(array_filter([(int) ($r?->department_id ?? 0)]));
    }
    $selectedTeamIds = array_values(array_map('intval', (array) $selectedTeamIds));
@endphp

<div class="grid sm:grid-cols-2 gap-4">
    <x-admin.input  name="name"            label="Full name"      :value="$r?->name"  required />
    <x-admin.input  name="email"           label="Email"          :value="$r?->email" type="email" required />
    <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
    <x-admin.input  name="password"        label="{{ $r ? 'New password (leave blank to keep)' : 'Password' }}" type="password" :required="! $r" />
    <x-admin.select name="role"            label="Role"           :options="$roles"   :value="$r?->role ?? request('role', 'officer')" required />
    <p class="sm:col-span-2 text-xs text-gray-600 -mt-2">
        <span class="font-semibold">Partner support</span> enrolls partners, adds regional coverage, and activates portal access.
        They do not screen or decide loans. The Partner support (PRT) team is assigned automatically for that role.
    </p>
    <x-admin.select name="branch_id"       label="Branch"         :options="$branches" :value="$r?->branch_id" placeholder="— None —" />
    <x-admin.select name="department_id"   label="Primary department" :options="$departments" :value="$r?->department_id" placeholder="— None —" />
    <x-admin.input  name="approval_limit"  label="Approval limit (TZS)" :value="$r?->approval_limit" money />
    <x-admin.select name="is_active"       label="Status"         :options="['1' => 'Active', '0' => 'Inactive']" :value="(string) ($r?->is_active ?? '1')" required />
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Teams <span class="text-gray-400 font-normal">(multi-team)</span></label>
        <p class="text-xs text-gray-500 mb-2">Assign every team this person works in. Nav access is the union of all selected teams. Marketing (MKT) unlocks promotions &amp; offers.</p>
        <div class="mb-3 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-xs text-amber-950">
            <p class="font-semibold">Credit desk separation</p>
            <p class="mt-1">A user cannot be on both <span class="font-semibold">Screening (UND)</span> and <span class="font-semibold">Committee (CRC)</span>. Committee + Management is allowed. Admin / Super admin may hold any combination.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto rounded-xl ring-1 ring-gray-200 p-3 bg-white">
            @foreach ($departments as $deptId => $deptName)
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="department_ids[]" value="{{ $deptId }}"
                           @checked(in_array((int) $deptId, $selectedTeamIds, true))
                           class="rounded border-gray-300 text-brand focus:ring-brand">
                    <span>{{ $deptName }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>
