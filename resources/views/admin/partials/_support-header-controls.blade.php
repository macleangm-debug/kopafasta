{{-- Persistent Support header: Human ▾ / Digital ▾ — searchable premium selectors. --}}
@php
    $supportWorkspace = $supportWorkspace ?? app(\App\Services\Support\CustomerSupportWorkspaceService::class);
    $roleView = app(\App\Services\AdminRoleViewService::class);
    $ctx = $roleView->active();
    $staffOptions = $supportWorkspace->staffOptions();
    $teamView = $supportWorkspace->isTeamView();
    $agent = $supportWorkspace->actingAgent(auth()->user());
    $availability = $supportWorkspace->availability($agent);
    $selectedStaffId = (! $teamView && ! empty($ctx['subject_id'])) ? (int) $ctx['subject_id'] : null;
    $viewingLabel = $teamView
        ? __('admin.role_view.staff_all')
        : (collect($staffOptions)->firstWhere('id', $selectedStaffId)['name'] ?? ($agent?->name ?? 'Staff'));
    $digitalAssistants = collect(app(\App\Services\Support\SupportAutomationService::class)->personas())
        ->map(fn (array $p) => [
            'key' => (string) $p['key'],
            'name' => (string) $p['name'],
            'url' => route('admin.support.assistants', ['persona' => $p['key']]),
            'initial' => mb_strtoupper(mb_substr((string) $p['name'], 0, 1)),
        ])
        ->values()
        ->all();
    $humanPeople = collect($staffOptions)
        ->map(fn (array $p) => [
            'id' => (int) $p['id'],
            'name' => (string) $p['name'],
            'initial' => mb_strtoupper(mb_substr((string) $p['name'], 0, 1)),
        ])
        ->values()
        ->all();
    $digitalOverviewUrl = route('admin.support.assistants');
    $humanOverviewUrl = route('admin.support.home');
    $humanSelectedLabel = $teamView
        ? __('admin.role_view.staff_all')
        : (collect($staffOptions)->firstWhere('id', $selectedStaffId)['name'] ?? 'Human');
@endphp
<div class="flex items-center gap-1.5 sm:gap-2 shrink-0 ml-auto pl-2"
     x-data="{
        humanOpen: false,
        digitalOpen: false,
        staffSheet: false,
        humanQ: '',
        digitalQ: '',
        humanPeople: @js($humanPeople),
        digitalPeople: @js($digitalAssistants),
        get filteredHumans() {
            const q = String(this.humanQ || '').trim().toLowerCase();
            if (!q) return this.humanPeople;
            return this.humanPeople.filter((p) => String(p.name || '').toLowerCase().includes(q));
        },
        get filteredDigital() {
            const q = String(this.digitalQ || '').trim().toLowerCase();
            if (!q) return this.digitalPeople;
            return this.digitalPeople.filter((p) => String(p.name || '').toLowerCase().includes(q));
        },
     }">
    {{-- Desktop Human searchable selector --}}
    <div class="hidden sm:block relative">
        <button type="button"
                @click="humanOpen = !humanOpen; digitalOpen = false; $nextTick(() => $refs.humanSearch?.focus())"
                class="inline-flex items-center gap-2 rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-3 py-1.5 focus:ring-2 focus:ring-brand-gold/50 min-w-[11rem] max-w-[16rem]"
                aria-label="Human"
                :aria-expanded="humanOpen.toString()">
            <span class="truncate">Human · {{ $humanSelectedLabel }}</span>
            <span aria-hidden="true">▾</span>
        </button>
        <div x-show="humanOpen" x-cloak @click.outside="humanOpen = false"
             class="absolute right-0 mt-2 z-50 w-[20rem] rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 overflow-hidden text-gray-900">
            <div class="p-2 border-b border-slate-100">
                <input x-ref="humanSearch" type="search" x-model="humanQ"
                       placeholder="Search humans…"
                       class="w-full rounded-xl border-0 bg-slate-50 ring-1 ring-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-brand/30">
            </div>
            <div class="max-h-72 overflow-y-auto py-1">
                <a href="{{ $humanOverviewUrl }}"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40">
                    <span class="size-8 rounded-xl bg-brand/10 text-brand grid place-items-center text-xs font-bold">◉</span>
                    <span class="flex-1">Overview</span>
                </a>
                <form method="POST" action="{{ route('admin.role-view.select-staff') }}">
                    @csrf
                    <button type="submit" name="staff_id" value="0"
                            class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40 {{ $teamView ? 'bg-brand/10 text-brand' : '' }}">
                        <span class="size-8 rounded-xl bg-slate-100 text-slate-700 grid place-items-center text-xs font-bold">T</span>
                        <span class="flex-1 text-left">{{ __('admin.role_view.staff_all') }}</span>
                        @if ($teamView)<span class="text-brand font-bold">✓</span>@endif
                    </button>
                    <template x-for="person in filteredHumans" :key="person.id">
                        <button type="submit" name="staff_id" :value="person.id"
                                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40"
                                :class="{{ (int) ($selectedStaffId ?? 0) }} === person.id ? 'bg-brand/10 text-brand' : ''">
                            <span class="size-8 rounded-xl bg-slate-800 text-white grid place-items-center text-xs font-bold" x-text="person.initial"></span>
                            <span class="flex-1 text-left min-w-0">
                                <span class="block truncate" x-text="person.name"></span>
                                <span class="block text-[10px] uppercase tracking-wide text-slate-500 font-bold">Human Support</span>
                            </span>
                            <span x-show="{{ (int) ($selectedStaffId ?? 0) }} === person.id" class="text-brand font-bold">✓</span>
                        </button>
                    </template>
                </form>
            </div>
        </div>
    </div>

    {{-- Desktop Digital searchable selector --}}
    <div class="hidden sm:block relative">
        <button type="button"
                @click="digitalOpen = !digitalOpen; humanOpen = false; $nextTick(() => $refs.digitalSearch?.focus())"
                class="inline-flex items-center gap-2 rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-3 py-1.5 focus:ring-2 focus:ring-brand-gold/50 min-w-[11rem] max-w-[16rem]"
                aria-label="Digital"
                :aria-expanded="digitalOpen.toString()">
            <span class="truncate">Digital ▾</span>
        </button>
        <div x-show="digitalOpen" x-cloak @click.outside="digitalOpen = false"
             class="absolute right-0 mt-2 z-50 w-[20rem] rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 overflow-hidden text-gray-900">
            <div class="p-2 border-b border-slate-100">
                <input x-ref="digitalSearch" type="search" x-model="digitalQ"
                       placeholder="Search assistants…"
                       class="w-full rounded-xl border-0 bg-slate-50 ring-1 ring-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-brand/30">
            </div>
            <div class="max-h-72 overflow-y-auto py-1">
                <a href="{{ $digitalOverviewUrl }}"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40">
                    <span class="size-8 rounded-xl bg-brand/10 text-brand grid place-items-center text-xs font-bold">◉</span>
                    <span class="flex-1">Digital Overview</span>
                </a>
                <template x-for="person in filteredDigital" :key="person.key">
                    <a :href="person.url"
                       class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40">
                        <span class="size-8 rounded-xl bg-brand text-white grid place-items-center text-xs font-bold" x-text="person.initial"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block truncate" x-text="person.name"></span>
                            <span class="block text-[10px] uppercase tracking-wide text-brand/70 font-bold">Digital Assistant</span>
                        </span>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <button type="button" @click="staffSheet = true"
            class="sm:hidden inline-flex items-center gap-1 rounded-lg bg-white/15 text-white font-semibold px-2.5 py-1.5 text-xs"
            aria-label="{{ __('admin.role_view.viewing') }}: {{ $viewingLabel }}">
        <span>{{ $viewingLabel }} ▾</span>
    </button>
    <x-site.action-panel :title="__('admin.role_view.viewing')" open="staffSheet">
        <p class="text-xs text-gray-500 mb-3">Human filters workload. Digital opens performance profiles — not logins.</p>
        <div class="mb-2">
            <input type="search" x-model="humanQ" placeholder="Search humans…"
                   class="w-full rounded-xl border-0 bg-slate-50 ring-1 ring-slate-200 px-3 py-2 text-sm">
        </div>
        <p class="pt-1 pb-1 text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Human</p>
        <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="space-y-1">
            @csrf
            <a href="{{ $humanOverviewUrl }}"
               class="block w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-slate-50 text-gray-900">Overview</a>
            <button type="submit" name="staff_id" value="0"
                    class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ $teamView ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                {{ __('admin.role_view.staff_all') }}
            </button>
            <template x-for="person in filteredHumans" :key="'m-'+person.id">
                <button type="submit" name="staff_id" :value="person.id"
                        class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-slate-50 text-gray-900"
                        :class="{{ (int) ($selectedStaffId ?? 0) }} === person.id ? 'bg-brand/10 text-brand' : ''">
                    <span x-text="person.name"></span>
                    <span class="ml-2 inline-flex rounded-full bg-slate-100 text-slate-700 text-[10px] font-bold px-2 py-0.5">Human</span>
                </button>
            </template>
        </form>
        <div class="mt-3 mb-2">
            <input type="search" x-model="digitalQ" placeholder="Search assistants…"
                   class="w-full rounded-xl border-0 bg-slate-50 ring-1 ring-slate-200 px-3 py-2 text-sm">
        </div>
        <p class="pt-1 pb-1 text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Digital</p>
        <div class="space-y-1">
            <a href="{{ $digitalOverviewUrl }}"
               class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-900 hover:bg-slate-50">
                <span>Overview</span>
                <span class="inline-flex rounded-full bg-brand-muted text-brand text-[10px] font-bold px-2 py-0.5">Digital</span>
            </a>
            <template x-for="person in filteredDigital" :key="'d-'+person.key">
                <a :href="person.url"
                   class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-900 hover:bg-slate-50">
                    <span x-text="person.name"></span>
                    <span class="inline-flex rounded-full bg-brand-muted text-brand text-[10px] font-bold px-2 py-0.5">AI</span>
                </a>
            </template>
        </div>
    </x-site.action-panel>

    @if ($agent)
        @php
            $isOnline = $availability === 'online';
            $nextAvailability = $isOnline ? 'offline' : 'online';
            $availLabel = $isOnline ? '● Online ▾' : '○ Offline ▾';
        @endphp
        <form method="POST" action="{{ route('admin.support.availability') }}" class="inline">
            @csrf
            <input type="hidden" name="availability" value="{{ $nextAvailability }}">
            <button type="submit"
                    class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-2.5 py-1.5 text-xs shadow-sm"
                    title="{{ $isOnline ? 'Click to go Offline.' : 'Click to go Online.' }}">
                {{ $availLabel }}
            </button>
        </form>
    @else
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-2.5 py-1.5 text-xs font-semibold text-white" title="Select a human agent to set Online/Offline.">
            ○ Offline
        </span>
    @endif
</div>
