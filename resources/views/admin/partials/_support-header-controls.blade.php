{{-- Persistent Support header: Human/Digital selector + Online/Offline on every Support page. --}}
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
@endphp
<div class="flex items-center gap-1.5 sm:gap-2 shrink-0 ml-auto pl-2" x-data="{ staffSheet: false }">
    <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="hidden sm:inline-flex items-center gap-1.5">
        @csrf
        <label class="sr-only">{{ __('admin.role_view.viewing') }}</label>
        <select name="staff_id"
                onchange="this.form.submit()"
                class="rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-2.5 py-1.5 focus:ring-2 focus:ring-brand-gold/50 max-w-[10rem]"
                title="Filters Support workload and performance. Does not impersonate — actions still record as you."
                aria-label="{{ __('admin.role_view.viewing') }}">
            <option value="0" @selected($teamView) class="text-gray-900">{{ __('admin.role_view.staff_all') }} ▾</option>
            <optgroup label="Human" class="text-gray-900">
                @foreach ($staffOptions as $person)
                    <option value="{{ $person['id'] }}" @selected((int) $selectedStaffId === (int) $person['id']) class="text-gray-900">
                        {{ $person['name'] }} · Human
                    </option>
                @endforeach
            </optgroup>
        </select>
    </form>
    @if (count($digitalAssistants) > 0)
        <select
            onchange="if (this.value) window.location = this.value"
            class="hidden sm:inline-flex rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-2.5 py-1.5 focus:ring-2 focus:ring-brand-gold/50 max-w-[9.5rem]"
            aria-label="Digital Assistants">
            <option value="">Digital ▾</option>
            @foreach ($digitalAssistants as $assistant)
                <option value="{{ $assistant['url'] }}" class="text-gray-900">
                    {{ $assistant['name'] }} · AI
                </option>
            @endforeach
        </select>
    @endif
    <button type="button" @click="staffSheet = true"
            class="sm:hidden inline-flex items-center gap-1 rounded-lg bg-white/15 text-white font-semibold px-2.5 py-1.5 text-xs"
            aria-label="{{ __('admin.role_view.viewing') }}: {{ $viewingLabel }}">
        <span>{{ $viewingLabel }} ▾</span>
    </button>
    <x-site.action-panel :title="__('admin.role_view.viewing')" open="staffSheet">
        <p class="text-xs text-gray-500 mb-3">Humans filter workload. Digital Assistants open performance profiles — not logins.</p>
        <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="space-y-1">
            @csrf
            <button type="submit" name="staff_id" value="0"
                    class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ $teamView ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                {{ __('admin.role_view.staff_all') }}
            </button>
            <p class="pt-2 pb-1 text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Human</p>
            @foreach ($staffOptions as $person)
                <button type="submit" name="staff_id" value="{{ $person['id'] }}"
                        class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ (int) $selectedStaffId === (int) $person['id'] ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                    <span>{{ $person['name'] }}</span>
                    <span class="ml-2 inline-flex rounded-full bg-slate-100 text-slate-700 text-[10px] font-bold px-2 py-0.5">Human</span>
                </button>
            @endforeach
        </form>
        @if (count($digitalAssistants) > 0)
            <p class="pt-3 pb-1 text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Digital Assistants</p>
            <div class="space-y-1">
                @foreach ($digitalAssistants as $assistant)
                    <a href="{{ $assistant['url'] }}"
                       class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-900 hover:bg-slate-50">
                        <span>{{ $assistant['name'] }}</span>
                        <span class="inline-flex rounded-full bg-brand-muted text-brand text-[10px] font-bold px-2 py-0.5">Digital</span>
                    </a>
                @endforeach
            </div>
        @endif
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
                    title="{{ $isOnline ? 'Click to go Offline. Assigned chats stay yours; you cannot Accept new waiting chats.' : 'Click to go Online and Accept new waiting chats.' }}">
                {{ $availLabel }}
            </button>
        </form>
    @else
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-2.5 py-1.5 text-xs font-semibold text-white" title="Select a support staff member to set Online/Offline.">
            ○ Offline
        </span>
    @endif
</div>
