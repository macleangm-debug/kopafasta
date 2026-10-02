{{-- Persistent Support header: Human ▾ / Digital ▾ (Overview + people/personas). --}}
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
        ])
        ->values()
        ->all();
    $digitalOverviewUrl = route('admin.support.assistants');
    $humanOverviewUrl = route('admin.support.home');
@endphp
<div class="flex items-center gap-1.5 sm:gap-2 shrink-0 ml-auto pl-2" x-data="{ staffSheet: false }">
    <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="hidden sm:inline-flex items-center gap-1.5">
        @csrf
        <label class="sr-only">Human</label>
        <select name="staff_id"
                onchange="if (this.value === 'overview') { window.location = @js($humanOverviewUrl); return; } this.form.submit()"
                class="rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-2.5 py-1.5 focus:ring-2 focus:ring-brand-gold/50 max-w-[10rem]"
                title="Human Support — Overview or filter by agent. Does not impersonate."
                aria-label="Human">
            <option value="" disabled selected class="text-gray-900">Human ▾</option>
            <option value="overview" class="text-gray-900">Overview</option>
            <option value="0" @selected($teamView) class="text-gray-900">{{ __('admin.role_view.staff_all') }}</option>
            @foreach ($staffOptions as $person)
                <option value="{{ $person['id'] }}" @selected((int) $selectedStaffId === (int) $person['id']) class="text-gray-900">
                    {{ $person['name'] }}
                </option>
            @endforeach
        </select>
    </form>
    <select
        onchange="if (this.value) window.location = this.value"
        class="hidden sm:inline-flex rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-2.5 py-1.5 focus:ring-2 focus:ring-brand-gold/50 max-w-[10rem]"
        aria-label="Digital">
        <option value="" disabled selected>Digital ▾</option>
        <option value="{{ $digitalOverviewUrl }}" class="text-gray-900">Overview</option>
        @foreach ($digitalAssistants as $assistant)
            <option value="{{ $assistant['url'] }}" class="text-gray-900">
                {{ $assistant['name'] }}
            </option>
        @endforeach
    </select>
    <button type="button" @click="staffSheet = true"
            class="sm:hidden inline-flex items-center gap-1 rounded-lg bg-white/15 text-white font-semibold px-2.5 py-1.5 text-xs"
            aria-label="{{ __('admin.role_view.viewing') }}: {{ $viewingLabel }}">
        <span>{{ $viewingLabel }} ▾</span>
    </button>
    <x-site.action-panel :title="__('admin.role_view.viewing')" open="staffSheet">
        <p class="text-xs text-gray-500 mb-3">Human filters workload. Digital opens performance profiles — not logins.</p>
        <p class="pt-1 pb-1 text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Human</p>
        <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="space-y-1">
            @csrf
            <a href="{{ $humanOverviewUrl }}"
               class="block w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-slate-50 text-gray-900">Overview</a>
            <button type="submit" name="staff_id" value="0"
                    class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ $teamView ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                {{ __('admin.role_view.staff_all') }}
            </button>
            @foreach ($staffOptions as $person)
                <button type="submit" name="staff_id" value="{{ $person['id'] }}"
                        class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ (int) $selectedStaffId === (int) $person['id'] ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                    <span>{{ $person['name'] }}</span>
                    <span class="ml-2 inline-flex rounded-full bg-slate-100 text-slate-700 text-[10px] font-bold px-2 py-0.5">Human</span>
                </button>
            @endforeach
        </form>
        <p class="pt-3 pb-1 text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Digital</p>
        <div class="space-y-1">
            <a href="{{ $digitalOverviewUrl }}"
               class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-900 hover:bg-slate-50">
                <span>Overview</span>
                <span class="inline-flex rounded-full bg-brand-muted text-brand text-[10px] font-bold px-2 py-0.5">Digital</span>
            </a>
            @foreach ($digitalAssistants as $assistant)
                <a href="{{ $assistant['url'] }}"
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
