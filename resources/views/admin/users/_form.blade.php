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
    <x-admin.step title="Personal">
        <x-admin.input name="name" label="Full name" :value="$r?->name" required autocomplete="name" />
        <x-admin.input name="email" label="Email (optional)" :value="$emailValue" type="email" autocomplete="off" />
        <div class="md:col-span-2">
            <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
        </div>
        <p class="md:col-span-2 text-xs text-gray-500">
            Staff sign-in uses email + password when email is set, or phone + password when email is blank. Never invent placeholder emails.
        </p>
    </x-admin.step>

    <x-admin.step title="Capabilities">
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
    </x-admin.step>

    <x-admin.step title="Work / Team">
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
        <div class="md:col-span-2" x-show="extraTeams.length === 0">
            <p class="text-sm text-gray-600">Home desk is assigned automatically from the selected capabilities. No extra teams available for this primary role.</p>
        </div>
    </x-admin.step>

    <x-admin.step title="Security">
        <div class="md:col-span-2 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 space-y-4">
            <input type="text" name="fake_username" autocomplete="username" class="hidden" tabindex="-1" aria-hidden="true">
            <input type="password" name="fake_password" autocomplete="current-password" class="hidden" tabindex="-1" aria-hidden="true">

            @if ($creating)
                <div x-data="{ show: false, show2: false }" class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700">Password for this person</label>
                    <div class="relative">
                        <input
                            :type="show ? 'text' : 'password'"
                            name="password"
                            autocomplete="new-password"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            class="w-full rounded-xl border-gray-200 text-sm pr-11"
                            placeholder="Temporary password"
                        >
                        <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-brand"
                                :aria-label="show ? 'Hide password' : 'Show password'">
                            <span x-text="show ? 'Hide' : 'Show'" class="text-xs font-semibold"></span>
                        </button>
                    </div>
                    <div class="relative">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Confirm password</label>
                        <input
                            :type="show2 ? 'text' : 'password'"
                            name="password_confirmation"
                            autocomplete="new-password"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            class="w-full rounded-xl border-gray-200 text-sm pr-11"
                            placeholder="Confirm temporary password"
                        >
                        <button type="button" @click="show2 = !show2"
                                class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-brand"
                                :aria-label="show2 ? 'Hide password' : 'Show password'">
                            <span x-text="show2 ? 'Hide' : 'Show'" class="text-xs font-semibold"></span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500">Or create without password and issue a setup link from the user profile.</p>
                </div>
            @else
                <p class="text-sm text-gray-600">Password changes and setup links are managed on the <a href="{{ route('admin.users.show', $r) }}" class="font-semibold text-brand hover:underline">user profile</a> (avoids nested forms).</p>
            @endif
            <x-admin.select name="is_active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" :value="(string) ($r?->is_active ?? '1')" required />
        </div>
    </x-admin.step>
</div>
