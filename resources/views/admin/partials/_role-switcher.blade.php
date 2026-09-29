{{-- Admin User/Role switcher — View profile vs Enter workspace --}}
<div class="relative"
     x-data="{
        open: false,
        q: '',
        loading: false,
        results: [],
        searchUrl: @js(route('admin.role-view.search')),
        profileUrl: @js(route('admin.role-view.profile')),
        enterUrl: @js(route('admin.role-view.enter')),
        csrf: @js(csrf_token()),
        enterLabel(role) {
            return @js(__('admin.role_view.enter_as')).replace(':role', role.label);
        },
        async run() {
            const q = this.q.trim();
            if (q.length < 2) { this.results = []; return; }
            this.loading = true;
            try {
                const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                this.results = data.results || [];
            } catch (e) {
                this.results = [];
            } finally {
                this.loading = false;
            }
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
        <input type="search"
               x-model="q"
               @input.debounce.250ms="run()"
               placeholder="{{ __('admin.role_view.search_placeholder') }}"
               class="w-full rounded-xl border-0 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand/40 mb-3">

        <p class="px-1 py-4 text-sm text-gray-500" x-show="loading">{{ __('admin.role_view.searching') }}</p>
        <p class="px-1 py-4 text-sm text-gray-500" x-show="!loading && q.trim().length >= 2 && results.length === 0">{{ __('admin.role_view.empty') }}</p>
        <p class="px-1 py-4 text-sm text-gray-500" x-show="!loading && q.trim().length < 2">{{ __('admin.role_view.type_more') }}</p>

        <ul class="space-y-3" x-show="!loading && results.length > 0">
            <template x-for="person in results" :key="person.subject_type + '-' + person.subject_id">
                <li class="rounded-2xl ring-1 ring-gray-200 p-3 space-y-2">
                    <div>
                        <p class="text-sm font-bold text-gray-900" x-text="person.name"></p>
                        <p class="text-xs text-gray-500" x-text="person.subtitle"></p>
                        <p class="mt-1 text-[11px] font-semibold text-brand" x-show="person.roles.length"
                           x-text="person.roles.map(r => r.label).join(' · ')"></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" :action="profileUrl">
                            <input type="hidden" name="_token" :value="csrf">
                            <input type="hidden" name="subject_type" :value="person.subject_type">
                            <input type="hidden" name="subject_id" :value="person.subject_id">
                            <button type="submit"
                                    class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold ring-1 ring-gray-200 text-gray-800 hover:bg-gray-50">
                                {{ __('admin.role_view.view_profile') }}
                            </button>
                        </form>
                        <template x-for="role in person.roles.filter(r => r.enterable)" :key="role.key">
                            <form method="POST" :action="enterUrl">
                                <input type="hidden" name="_token" :value="csrf">
                                <input type="hidden" name="subject_type" :value="person.subject_type">
                                <input type="hidden" name="subject_id" :value="person.subject_id">
                                <input type="hidden" name="role_key" :value="role.key">
                                <button type="submit"
                                        class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold bg-brand text-white hover:brightness-95"
                                        x-text="enterLabel(role)"></button>
                            </form>
                        </template>
                    </div>
                    <p class="text-[11px] text-amber-700" x-show="person.subject_type === 'partner' && person.roles.length && !person.roles.some(r => r.enterable)">
                        {{ __('admin.role_view.partner_no_login') }}
                    </p>
                    <p class="text-[11px] text-gray-500" x-show="person.subject_type === 'borrower'">
                        {{ __('admin.role_view.borrower_profile_only') }}
                    </p>
                </li>
            </template>
        </ul>
    </x-site.action-panel>
</div>
