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
            'needle' => mb_strtolower((string) $p['name']),
        ])
        ->values()
        ->all();
    $humanPeople = collect($staffOptions)
        ->map(fn (array $p) => [
            'id' => (int) $p['id'],
            'name' => (string) $p['name'],
            'initial' => mb_strtoupper(mb_substr((string) $p['name'], 0, 1)),
            'needle' => mb_strtolower((string) $p['name']),
        ])
        ->values()
        ->all();
    $digitalOverviewUrl = route('admin.support.assistants');
    $humanOverviewUrl = route('admin.support.home');
    $humanSelectedLabel = $teamView
        ? __('admin.role_view.staff_all')
        : (collect($staffOptions)->firstWhere('id', $selectedStaffId)['name'] ?? 'Human');
    $selectStaffUrl = route('admin.role-view.select-staff');
@endphp
<div class="relative shrink-0 flex items-center gap-1.5 sm:gap-2 ml-auto pl-2 overflow-visible"
     x-data="{
        humanOpen: false,
        digitalOpen: false,
        staffSheet: false,
        humanQ: '',
        digitalQ: '',
        humanStyle: '',
        digitalStyle: '',
        closeAll() { this.humanOpen = false; this.digitalOpen = false; },
        place(btn, which) {
            if (!btn) return;
            const r = btn.getBoundingClientRect();
            const style = 'top:' + Math.round(r.bottom + 8) + 'px;right:' + Math.round(Math.max(8, window.innerWidth - r.right)) + 'px;';
            if (which === 'human') this.humanStyle = style; else this.digitalStyle = style;
        },
        matches(needle, q) {
            const query = String(q || '').trim().toLowerCase();
            if (!query) return true;
            return String(needle || '').includes(query);
        },
        pickHuman(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @js($selectStaffUrl);
            const csrf = document.createElement('input');
            csrf.type = 'hidden'; csrf.name = '_token';
            csrf.value = document.querySelector('meta[name=csrf-token]')?.content || '';
            const field = document.createElement('input');
            field.type = 'hidden'; field.name = 'staff_id'; field.value = String(id);
            form.appendChild(csrf); form.appendChild(field);
            document.body.appendChild(form);
            form.submit();
        },
     }"
     @keydown.escape.window="closeAll()">
    {{-- Desktop Human --}}
    <div class="hidden sm:block shrink-0">
        <button type="button" x-ref="humanBtn"
                @click="digitalOpen = false; humanOpen = !humanOpen; if (humanOpen) { humanQ = ''; $nextTick(() => { place($refs.humanBtn, 'human'); $refs.humanSearch?.focus(); }); }"
                class="inline-flex items-center gap-2 rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-3 py-1.5 focus:ring-2 focus:ring-brand-gold/50 min-w-[9rem] max-w-[14rem]"
                aria-label="Human"
                :aria-expanded="humanOpen.toString()">
            <span class="truncate">Human · {{ $humanSelectedLabel }}</span>
            <span aria-hidden="true">▾</span>
        </button>
        <div x-show="humanOpen" x-cloak x-transition.opacity.duration.100ms
             @click.outside="humanOpen = false"
             :style="humanStyle"
             class="fixed z-[90] w-[20rem] max-w-[calc(100vw-1rem)] rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 overflow-hidden text-gray-900"
             style="display:none">
            <div class="p-2 border-b border-slate-100 bg-white">
                <input x-ref="humanSearch" type="search" x-model="humanQ"
                       placeholder="Search humans…"
                       class="w-full rounded-xl border-0 bg-slate-50 ring-1 ring-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-brand/30">
            </div>
            <div class="max-h-72 overflow-y-auto py-1 bg-white">
                <a href="{{ $humanOverviewUrl }}"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40">
                    <span class="size-8 rounded-xl bg-brand/10 text-brand grid place-items-center text-xs font-bold">◉</span>
                    <span class="flex-1">Overview</span>
                </a>
                <button type="button" @click="pickHuman(0)"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40 {{ $teamView ? 'bg-brand/10 text-brand' : '' }}">
                    <span class="size-8 rounded-xl bg-slate-100 text-slate-700 grid place-items-center text-xs font-bold">T</span>
                    <span class="flex-1 text-left">{{ __('admin.role_view.staff_all') }}</span>
                    @if ($teamView)<span class="text-brand font-bold">✓</span>@endif
                </button>
                @forelse ($humanPeople as $person)
                    <button type="button"
                            x-show="matches(@js($person['needle']), humanQ)"
                            @click="pickHuman({{ (int) $person['id'] }})"
                            class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40 {{ (int) $selectedStaffId === (int) $person['id'] ? 'bg-brand/10 text-brand' : '' }}">
                        <span class="size-8 rounded-xl bg-slate-800 text-white grid place-items-center text-xs font-bold">{{ $person['initial'] }}</span>
                        <span class="flex-1 text-left min-w-0">
                            <span class="block truncate">{{ $person['name'] }}</span>
                            <span class="block text-[10px] uppercase tracking-wide text-slate-500 font-bold">Human Support</span>
                        </span>
                        @if ((int) $selectedStaffId === (int) $person['id'])
                            <span class="text-brand font-bold">✓</span>
                        @endif
                    </button>
                @empty
                    <p class="px-3 py-2 text-xs text-slate-500">No human assistants available.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Desktop Digital --}}
    <div class="hidden sm:block shrink-0">
        <button type="button" x-ref="digitalBtn"
                @click="humanOpen = false; digitalOpen = !digitalOpen; if (digitalOpen) { digitalQ = ''; $nextTick(() => { place($refs.digitalBtn, 'digital'); $refs.digitalSearch?.focus(); }); }"
                class="inline-flex items-center gap-2 rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-3 py-1.5 focus:ring-2 focus:ring-brand-gold/50 min-w-[9rem] max-w-[14rem]"
                aria-label="Digital"
                :aria-expanded="digitalOpen.toString()">
            <span class="truncate">Digital ▾</span>
        </button>
        <div x-show="digitalOpen" x-cloak x-transition.opacity.duration.100ms
             @click.outside="digitalOpen = false"
             :style="digitalStyle"
             class="fixed z-[90] w-[20rem] max-w-[calc(100vw-1rem)] rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 overflow-hidden text-gray-900"
             style="display:none">
            <div class="p-2 border-b border-slate-100 bg-white">
                <input x-ref="digitalSearch" type="search" x-model="digitalQ"
                       placeholder="Search assistants…"
                       class="w-full rounded-xl border-0 bg-slate-50 ring-1 ring-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-brand/30">
            </div>
            <div class="max-h-72 overflow-y-auto py-1 bg-white">
                <a href="{{ $digitalOverviewUrl }}"
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40">
                    <span class="size-8 rounded-xl bg-brand/10 text-brand grid place-items-center text-xs font-bold">◉</span>
                    <span class="flex-1">Digital Overview</span>
                </a>
                @forelse ($digitalAssistants as $assistant)
                    <a href="{{ $assistant['url'] }}"
                       x-show="matches(@js($assistant['needle']), digitalQ)"
                       class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-muted/40">
                        <span class="size-8 rounded-xl bg-brand text-white grid place-items-center text-xs font-bold">{{ $assistant['initial'] }}</span>
                        <span class="flex-1 min-w-0">
                            <span class="block truncate">{{ $assistant['name'] }}</span>
                            <span class="block text-[10px] uppercase tracking-wide text-brand/70 font-bold">Digital Assistant</span>
                        </span>
                    </a>
                @empty
                    <p class="px-3 py-2 text-xs text-slate-500">No digital assistants configured.</p>
                @endforelse
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
        <div class="space-y-1">
            <a href="{{ $humanOverviewUrl }}"
               class="block w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-slate-50 text-gray-900">Overview</a>
            <button type="button" @click="pickHuman(0)"
                    class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ $teamView ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                {{ __('admin.role_view.staff_all') }}
            </button>
            @foreach ($humanPeople as $person)
                <button type="button"
                        x-show="matches(@js($person['needle']), humanQ)"
                        @click="pickHuman({{ (int) $person['id'] }})"
                        class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ (int) $selectedStaffId === (int) $person['id'] ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                    <span>{{ $person['name'] }}</span>
                    <span class="ml-2 inline-flex rounded-full bg-slate-100 text-slate-700 text-[10px] font-bold px-2 py-0.5">Human</span>
                </button>
            @endforeach
        </div>
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
            @foreach ($digitalAssistants as $assistant)
                <a href="{{ $assistant['url'] }}"
                   x-show="matches(@js($assistant['needle']), digitalQ)"
                   class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-900 hover:bg-slate-50">
                    <span>{{ $assistant['name'] }}</span>
                    <span class="inline-flex rounded-full bg-brand-muted text-brand text-[10px] font-bold px-2 py-0.5">AI</span>
                </a>
            @endforeach
        </div>
    </x-site.action-panel>

    @if ($agent)
        @php
            $isOnline = $availability === 'online';
            $nextAvailability = $isOnline ? 'offline' : 'online';
            $availLabel = $isOnline ? '● Online ▾' : '○ Offline ▾';
        @endphp
        <form method="POST" action="{{ route('admin.support.availability') }}" class="inline shrink-0">
            @csrf
            <input type="hidden" name="availability" value="{{ $nextAvailability }}">
            <button type="submit"
                    class="inline-flex items-center rounded-lg bg-brand-gold text-brand font-bold px-2.5 py-1.5 text-xs shadow-sm"
                    title="{{ $isOnline ? 'Click to go Offline.' : 'Click to go Online.' }}">
                {{ $availLabel }}
            </button>
        </form>
    @else
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-2.5 py-1.5 text-xs font-semibold text-white shrink-0" title="Select a human agent to set Online/Offline.">
            ○ Offline
        </span>
    @endif
</div>
