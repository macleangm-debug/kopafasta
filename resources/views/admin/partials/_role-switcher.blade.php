{{-- Admin Account / Role — internal staff workspace directory --}}
@php
    $staffRoleDirectory = app(\App\Services\AdminRoleViewService::class)->staffRoleDirectory();
@endphp
<div class="relative"
     x-data="{
        open: false,
        filter: '',
        roles: @js($staffRoleDirectory),
        selected: {},
        profileUrl: @js(route('admin.role-view.profile')),
        enterUrl: @js(route('admin.role-view.enter')),
        csrf: @js(csrf_token()),
        init() {
            this.roles.forEach((role) => {
                this.selected[role.key] = role.staff.length === 1 ? role.staff[0].id : (role.staff[0]?.id || null);
            });
        },
        filteredRoles() {
            if (! this.filter) {
                return this.roles;
            }
            return this.roles.filter((role) => role.key === this.filter);
        },
        selectedStaff(role) {
            const id = this.selected[role.key];
            return role.staff.find((person) => person.id === id) || null;
        }
     }">
    <button type="button"
            @click="open = true"
            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 ring-1 ring-transparent hover:ring-gray-200"
            title="{{ __('admin.role_view.title') }}"
            aria-label="{{ __('admin.role_view.title') }}">
        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
        <span class="hidden lg:inline max-w-[7rem] truncate">{{ __('admin.role_view.short') }}</span>
    </button>

    <x-site.action-panel :title="__('admin.role_view.title')" open="open" size="lg">
        <p class="text-xs text-gray-500 mb-3">{{ __('admin.role_view.hint') }}</p>

        <label class="block mb-4">
            <span class="sr-only">{{ __('admin.role_view.filter_label') }}</span>
            <select x-model="filter"
                    class="w-full rounded-xl border-0 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand/40 bg-white">
                <option value="">{{ __('admin.role_view.filter_all') }}</option>
                <template x-for="role in roles" :key="'filter-' + role.key">
                    <option :value="role.key" x-text="role.label"></option>
                </template>
            </select>
        </label>

        <ul class="space-y-3 max-h-[60vh] overflow-y-auto pr-0.5">
            <template x-for="role in filteredRoles()" :key="role.key">
                <li class="rounded-2xl ring-1 ring-gray-200 p-3 space-y-2.5">
                    <div>
                        <p class="text-sm font-bold text-gray-900" x-text="role.label"></p>
                        <p class="text-xs text-gray-500" x-show="role.staff_count === 0">
                            {{ __('admin.role_view.no_staff') }}
                        </p>
                        <p class="text-xs text-gray-500" x-show="role.staff_count === 1"
                           x-text="role.staff[0]?.name + (role.staff[0]?.subtitle ? ' · ' + role.staff[0].subtitle : '')"></p>
                    </div>

                    <div x-show="role.staff_count > 1">
                        <label class="block">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.role_view.assigned_staff') }}</span>
                            <select class="mt-1 w-full rounded-xl border-0 ring-1 ring-gray-200 px-3 py-2 text-sm focus:ring-brand/40 bg-white"
                                    x-model.number="selected[role.key]">
                                <template x-for="person in role.staff" :key="person.id">
                                    <option :value="person.id" x-text="person.name + (person.subtitle ? ' · ' + person.subtitle : '')"></option>
                                </template>
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-2" x-show="role.staff_count > 0 && selectedStaff(role)">
                        <form method="POST" :action="profileUrl">
                            <input type="hidden" name="_token" :value="csrf">
                            <input type="hidden" name="subject_type" value="staff">
                            <input type="hidden" name="subject_id" :value="selectedStaff(role).id">
                            <button type="submit"
                                    class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold ring-1 ring-gray-200 text-gray-800 hover:bg-gray-50">
                                {{ __('admin.role_view.view_profile') }}
                            </button>
                        </form>
                        <form method="POST" :action="enterUrl">
                            <input type="hidden" name="_token" :value="csrf">
                            <input type="hidden" name="subject_type" value="staff">
                            <input type="hidden" name="subject_id" :value="selectedStaff(role).id">
                            <input type="hidden" name="role_key" :value="role.key">
                            <button type="submit"
                                    class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold bg-brand text-white hover:brightness-95">
                                {{ __('admin.role_view.enter_workspace') }}
                            </button>
                        </form>
                    </div>
                </li>
            </template>
        </ul>
    </x-site.action-panel>
</div>
