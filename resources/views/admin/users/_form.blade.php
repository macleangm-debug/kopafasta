{{-- Shared User form. Expects $record, $branches, $roles --}}
@php($r = $record ?? null)

<x-admin.step title="Identity">
    <x-admin.input  name="name"            label="Full name"      :value="$r?->name"  required />
    <x-admin.input  name="email"           label="Email"          :value="$r?->email" type="email" required />
    <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
    <x-admin.input  name="password"        label="{{ $r ? 'New password (leave blank to keep)' : 'Password' }}" type="password" :required="! $r" />
</x-admin.step>

<x-admin.step title="Role & access">
    <x-admin.select name="role"            label="Role"           :options="$roles"   :value="$r?->role ?? 'officer'" required />
    <x-admin.select name="branch_id"       label="Branch"         :options="$branches" :value="$r?->branch_id" placeholder="— None —" />
    <x-admin.select name="department_id"   label="Primary department" :options="$departments" :value="$r?->department_id" placeholder="— None —" />
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Teams <span class="text-gray-400 font-normal">(multi-team)</span></label>
        <p class="text-xs text-gray-500 mb-2">Assign every team this person works in. Nav access is the union of all selected teams. Marketing (MKT) unlocks promotions &amp; offers.</p>
        <div class="grid sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto rounded-xl ring-1 ring-gray-200 p-3 bg-white">
            @php
                $selectedTeamIds = old('department_ids', $r?->departments?->pluck('id')->all() ?? array_filter([(int) ($r?->department_id)]));
                $selectedTeamIds = array_map('intval', (array) $selectedTeamIds);
            @endphp
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
    <x-admin.input  name="approval_limit"  label="Approval limit (TZS)" :value="$r?->approval_limit" money />
    <x-admin.select name="is_active"       label="Status"         :options="['1' => 'Active', '0' => 'Inactive']" :value="(string) ($r?->is_active ?? '1')" required />
</x-admin.step>
