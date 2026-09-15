{{-- Shared User form. Expects $record, $roles, $roleDuties, $roleDesks --}}
@php
    $r = $record ?? null;
    $creating = $r === null;
    $rolesService = app(\App\Services\RoleService::class);
    $desks = app(\App\Services\CreditDeskAssignmentService::class);
    $selectedRoles = old('roles');
    if ($selectedRoles === null) {
        $selectedRoles = $r ? $r->roleCodes() : array_values(array_filter([(string) request('role', 'officer')]));
    }
    $selectedRoles = array_values(array_unique(array_map('strval', (array) $selectedRoles)));
    if ($selectedRoles === []) {
        $selectedRoles = ['officer'];
    }
    $selectedRole = $rolesService->resolvePrimaryRole($selectedRoles);
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
    $emailValue = operator_email_display($r?->email);
    $emailValue = $emailValue === '—' ? '' : $emailValue;
    $emailValue = old('email', $emailValue);
@endphp

<div
    x-data="{
        selected: @js($selectedRoles),
        duties: @js($roleDuties),
        desks: @js($roleDesks),
        labels: @js($roles),
        priority: @js(['admin','super_admin','manager','credit_committee','credit_analyst','officer','partner_support','asset_manager','marketer','agent']),
        teams: @js($departmentRows->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name, 'code' => strtoupper((string) $d->code)])->values()),
        homeCodes: @js($homeDeskCodes),
        blocked: @js($blockedByRole),
        teamSelected: @js($selectedTeamIds),
        get role() {
            for (const code of this.priority) {
                if (this.selected.includes(code)) return code;
            }
            return this.selected[0] || 'officer';
        },
        get duty() { return this.duties[this.role] || ''; },
        get desk() { return this.desks[this.role] || 'Assigned from the role'; },
        get extraTeams() {
            const home = this.homeCodes[this.role] || null;
            const blocked = this.blocked[this.role] || [];
            return this.teams.filter((team) => team.code !== home && ! blocked.includes(team.code));
        },
        isChecked(code) { return this.selected.includes(code); },
        toggleRole(code) {
            if (this.isChecked(code)) {
                if (this.selected.length <= 1) return;
                this.selected = this.selected.filter((v) => v !== code);
            } else {
                this.selected = [...this.selected, code];
            }
            $nextTick(() => window.dispatchEvent(new CustomEvent('admin-wizard-rebuild')));
        },
        isTeamChecked(id) { return this.teamSelected.map(Number).includes(Number(id)); },
        toggleTeam(id) {
            id = Number(id);
            if (this.isTeamChecked(id)) {
                this.teamSelected = this.teamSelected.filter((v) => Number(v) !== id);
            } else {
                this.teamSelected = [...this.teamSelected, id];
            }
        },
    }"
>
    <x-admin.step title="Person">
        <x-admin.input name="name" label="Full name" :value="$r?->name" required autocomplete="name" />
        <x-admin.input name="email" label="Email" :value="$emailValue" type="email" required autocomplete="off" />
        <div class="md:col-span-2">
            <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
        </div>
        <p class="md:col-span-2 text-xs text-gray-500">
            This is a staff console account (email + password), not a borrower or partner PIN.
        </p>
    </x-admin.step>

    <x-admin.step title="Desk">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Capabilities <span class="text-red-500">*</span></label>
            <p class="text-xs text-gray-500 mb-2">Select one or more. Home desk follows the primary credit/ops capability; Customer Support adds ticket access without approval authority.</p>
            <div class="grid sm:grid-cols-2 gap-2 rounded-xl ring-1 ring-gray-200 p-3 bg-white">
                @foreach ($roles as $code => $label)
                    <label class="inline-flex items-start gap-2 text-sm text-gray-800">
                        <input type="checkbox"
                               name="roles[]"
                               value="{{ $code }}"
                               x-bind:checked="isChecked(@js($code))"
                               @change="toggleRole(@js($code))"
                               class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                        <span>
                            <span class="font-medium">{{ $label }}</span>
                            @if (! empty($roleDuties[$code] ?? null))
                                <span class="block text-xs text-gray-500 mt-0.5">{{ $roleDuties[$code] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            @error('roles')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
            @error('roles.*')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-600 mt-3" x-text="duty"></p>
            <p class="text-xs text-gray-500 mt-1">
                Home desk: <span class="font-semibold text-gray-800" x-text="desk"></span>.
                Branch is Head Office, Dar es Salaam — kopafasta is online, not a branch network.
            </p>
            @if (! empty($approvalAuthority))
                <p class="text-xs text-gray-500 mt-2">
                    Effective approval authority (from Settings Hub matrix): <span class="font-semibold text-gray-800">{{ $approvalAuthority }}</span>
                </p>
            @endif
        </div>

        <div class="md:col-span-2" x-show="extraTeams.length > 0">
            <label class="block text-sm font-medium text-gray-700 mb-1">Also on these teams <span class="text-gray-400 font-normal">(optional)</span></label>
            <p class="text-xs text-gray-500 mb-2">The home desk above is assigned from the primary capability. Extra teams only add nav — they cannot mix Screening and Committee.</p>
            <div class="grid sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto rounded-xl ring-1 ring-gray-200 p-3 bg-white">
                <template x-for="team in extraTeams" :key="team.id">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox"
                               name="department_ids[]"
                               :value="team.id"
                               :checked="isTeamChecked(team.id)"
                               @change="toggleTeam(team.id)"
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
