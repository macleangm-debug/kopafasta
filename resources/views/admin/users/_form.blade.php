{{-- Shared User form. Expects $record, $roles, $roleDuties, $roleDesks --}}
@php
    $r = $record ?? null;
    $creating = $r === null;
    $rolesService = app(\App\Services\RoleService::class);
    $desks = app(\App\Services\CreditDeskAssignmentService::class);
    $selectedRole = old('role', $r?->role ?? request('role', 'officer'));
    $roleDuties = $roleDuties ?? collect($roles)->mapWithKeys(fn ($label, $code) => [$code => $rolesService->duty($code)])->all();
    $roleDesks = $roleDesks ?? [];
    $selectedTeamIds = old('department_ids');
    if ($selectedTeamIds === null) {
        $selectedTeamIds = $r?->departments
            ? $r->departments->pluck('id')->all()
            : array_values(array_filter([(int) ($r?->department_id ?? 0)]));
    }
    $selectedTeamIds = array_values(array_map('intval', (array) $selectedTeamIds));
    $departmentRows = $departmentRows ?? collect();
    $blockedByRole = [];
    foreach (array_keys($roles ?? []) as $code) {
        $blockedByRole[$code] = $desks->blockedExtraDepartmentCodes((string) $code);
    }
    $homeDeskCodes = [];
    foreach (array_keys($roles ?? []) as $code) {
        $homeDeskCodes[$code] = $rolesService->deskCode((string) $code);
    }
@endphp

<div
    x-data="{
        role: @js($selectedRole),
        duties: @js($roleDuties),
        desks: @js($roleDesks),
        teams: @js($departmentRows->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name, 'code' => strtoupper((string) $d->code)])->values()),
        homeCodes: @js($homeDeskCodes),
        blocked: @js($blockedByRole),
        selected: @js($selectedTeamIds),
        get duty() { return this.duties[this.role] || ''; },
        get desk() { return this.desks[this.role] || 'Assigned from the role'; },
        get extraTeams() {
            const home = this.homeCodes[this.role] || null;
            const blocked = this.blocked[this.role] || [];
            return this.teams.filter((team) => team.code !== home && ! blocked.includes(team.code));
        },
        isChecked(id) { return this.selected.map(Number).includes(Number(id)); },
        toggle(id) {
            id = Number(id);
            if (this.isChecked(id)) {
                this.selected = this.selected.filter((v) => Number(v) !== id);
            } else {
                this.selected = [...this.selected, id];
            }
        },
    }"
    x-init="$watch('role', () => $nextTick(() => window.dispatchEvent(new CustomEvent('admin-wizard-rebuild'))))"
>
    <x-admin.step title="Person">
        <x-admin.input name="name" label="Full name" :value="$r?->name" required autocomplete="name" />
        <x-admin.input name="email" label="Email" :value="$r?->email" type="email" required autocomplete="off" />
        <div class="md:col-span-2">
            <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
        </div>
        <p class="md:col-span-2 text-xs text-gray-500">
            This is a staff console account (email + password), not a borrower or partner PIN.
        </p>
    </x-admin.step>

    <x-admin.step title="Desk">
        <div class="md:col-span-2">
            <x-admin.select name="role" label="Role" :options="$roles" :value="$selectedRole" required x-model="role" />
            <p class="text-xs text-gray-600 mt-2" x-text="duty"></p>
            <p class="text-xs text-gray-500 mt-1">
                Home desk: <span class="font-semibold text-gray-800" x-text="desk"></span>.
                Branch is Head Office, Dar es Salaam — kopafasta is online, not a branch network.
            </p>
        </div>

        <div class="md:col-span-2" x-show="extraTeams.length > 0">
            <label class="block text-sm font-medium text-gray-700 mb-1">Also on these teams <span class="text-gray-400 font-normal">(optional)</span></label>
            <p class="text-xs text-gray-500 mb-2">The home desk above is assigned from the role. Extra teams only add nav — they cannot mix Screening and Committee.</p>
            <div class="grid sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto rounded-xl ring-1 ring-gray-200 p-3 bg-white">
                <template x-for="team in extraTeams" :key="team.id">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox"
                               name="department_ids[]"
                               :value="team.id"
                               :checked="isChecked(team.id)"
                               @change="toggle(team.id)"
                               class="rounded border-gray-300 text-brand focus:ring-brand">
                        <span x-text="team.name"></span>
                    </label>
                </template>
            </div>
        </div>
    </x-admin.step>

    <x-admin.step title="Access">
        <div class="md:col-span-2 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 space-y-4">
            {{-- Absorb browser autofill so the real password stays empty --}}
            <input type="text" name="fake_username" autocomplete="username" class="hidden" tabindex="-1" aria-hidden="true">
            <input type="password" name="fake_password" autocomplete="current-password" class="hidden" tabindex="-1" aria-hidden="true">

            <x-admin.input
                name="password"
                :label="$creating ? 'Password for this person' : 'New password (leave blank to keep)'"
                type="password"
                :required="$creating"
                autocomplete="new-password"
                data-lpignore="true"
                data-1p-ignore="true"
                readonly
                onfocus="this.removeAttribute('readonly')"
                :value="''"
                help="Type a new password here. Your browser may try to fill your own login — ignore that. Staff can change this later under Account security."
            />
            <x-admin.select name="is_active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" :value="(string) ($r?->is_active ?? '1')" required />
        </div>
    </x-admin.step>
</div>
