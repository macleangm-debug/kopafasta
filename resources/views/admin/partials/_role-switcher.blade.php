{{-- Admin Account / Role — role-first internal workspace directory --}}
@php
    $staffRoleDirectory = app(\App\Services\AdminRoleViewService::class)->staffRoleDirectory();
@endphp
<div class="relative"
     x-data="{
        open: false,
        filter: '',
        roles: @js($staffRoleDirectory),
        enterUrl: @js(route('admin.role-view.enter')),
        csrf: @js(csrf_token()),
        filteredRoles() {
            if (! this.filter) {
                return this.roles;
            }
            return this.roles.filter((role) => role.key === this.filter);
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

        <ul class="space-y-2 max-h-[60vh] overflow-y-auto pr-0.5">
            <template x-for="role in filteredRoles()" :key="role.key">
                <li>
                    <form method="POST" :action="enterUrl" class="block">
                        <input type="hidden" name="_token" :value="csrf">
                        <input type="hidden" name="subject_type" value="workspace">
                        <input type="hidden" name="workspace_key" :value="role.key">
                        <button type="submit"
                                class="w-full text-left rounded-2xl ring-1 ring-gray-200 px-4 py-3 hover:ring-brand/40 hover:bg-brand-muted/20 transition">
                            <span class="flex items-center justify-between gap-3">
                                <span>
                                    <span class="block text-sm font-bold text-gray-900" x-text="role.label"></span>
                                    <span class="block text-xs text-gray-500 mt-0.5"
                                          x-text="role.staff_count === 0
                                            ? @js(__('admin.role_view.no_staff'))
                                            : (role.staff_count + ' ' + @js(__('admin.role_view.staff_assigned')))"></span>
                                </span>
                                <span class="shrink-0 text-xs font-semibold text-brand">{{ __('admin.role_view.enter_workspace') }} →</span>
                            </span>
                        </button>
                    </form>
                </li>
            </template>
        </ul>
    </x-site.action-panel>
</div>
