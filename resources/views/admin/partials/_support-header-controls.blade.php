{{-- Persistent Support header: Team selector + Online/Offline on every Support page. --}}
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
@endphp
<div class="flex items-center gap-1.5 sm:gap-2 shrink-0 ml-auto pl-2">
    <div class="relative" x-data="{ staffSheet: false }">
        <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="hidden sm:inline-flex items-center gap-1.5">
            @csrf
            <label class="sr-only">{{ __('admin.role_view.viewing') }}</label>
            <select name="staff_id"
                    onchange="this.form.submit()"
                    class="rounded-lg border-0 bg-white/15 text-white text-xs font-semibold px-2.5 py-1.5 focus:ring-2 focus:ring-brand-gold/50 max-w-[9.5rem]"
                    title="Filters Support workload and performance. Does not impersonate — actions still record as you."
                    aria-label="{{ __('admin.role_view.viewing') }}: {{ __('admin.role_view.staff_all') }}">
                <option value="0" @selected($teamView) class="text-gray-900">{{ __('admin.role_view.staff_all') }} ▾</option>
                @foreach ($staffOptions as $person)
                    <option value="{{ $person['id'] }}" @selected((int) $selectedStaffId === (int) $person['id']) class="text-gray-900">
                        {{ $person['name'] }}
                    </option>
                @endforeach
            </select>
        </form>
        <button type="button" @click="staffSheet = true"
                class="sm:hidden inline-flex items-center gap-1 rounded-lg bg-white/15 text-white font-semibold px-2.5 py-1.5 text-xs"
                aria-label="{{ __('admin.role_view.viewing') }}: {{ $viewingLabel }}">
            <span>{{ $viewingLabel }} ▾</span>
        </button>
        <x-site.action-panel :title="__('admin.role_view.viewing')" open="staffSheet">
            <p class="text-xs text-gray-500 mb-3">Filters Support workload and performance only. You stay signed in as Admin.</p>
            <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="space-y-1">
                @csrf
                <button type="submit" name="staff_id" value="0"
                        class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ $teamView ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                    {{ __('admin.role_view.staff_all') }}
                </button>
                @foreach ($staffOptions as $person)
                    <button type="submit" name="staff_id" value="{{ $person['id'] }}"
                            class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ (int) $selectedStaffId === (int) $person['id'] ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                        {{ $person['name'] }}
                    </button>
                @endforeach
            </form>
        </x-site.action-panel>
    </div>

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
                    title="{{ $isOnline ? 'Click to go Offline. Assigned chats stay yours; you cannot Accept new waiting chats.' : 'Click to go Online and Accept waiting chats.' }}">
                {{ $availLabel }}
            </button>
        </form>
    @else
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-2.5 py-1.5 text-xs font-semibold text-white" title="Select a support staff member to set Online/Offline.">
            ○ Offline
        </span>
    @endif
</div>
