@php
    $agent = $dashboard['agent'] ?? null;
    $teamView = (bool) ($dashboard['team_view'] ?? true);
    $availability = $dashboard['availability'] ?? null;
    $counters = $dashboard['counters'] ?? [];
    $queue = $dashboard['queue'] ?? [];
    $tickets = $dashboard['tickets'] ?? [];
    $performance = $dashboard['performance'] ?? [];
    $staffOptions = $dashboard['staff_options'] ?? [];
    $selectedStaffId = $dashboard['selected_staff_id'] ?? null;
    $agentsOnline = (int) ($dashboard['agents_online'] ?? 0);
    $recurringIssues = $dashboard['recurring_issues'] ?? [];
    $dot = match ($availability) {
        'online' => 'bg-emerald-400',
        'away' => 'bg-amber-400',
        'offline' => 'bg-gray-400',
        default => 'bg-gray-300',
    };
    $heroSubtitle = $teamView
        ? 'Help members, partners and guests — answer conversations and resolve tickets.'
        : 'Help members, answer conversations and resolve tickets.';
@endphp
<x-admin.layout title="Support" heading="" subheading="">
    <x-admin.letterhead
        kicker="Support"
        title="Support"
        :subtitle="$agent?->name ?: $heroSubtitle"
    >
        <x-slot:meta>{{ $heroSubtitle }}</x-slot:meta>
        <x-slot:actions>
            <div class="relative" x-data="{ staffSheet: false }">
                @php
                    $viewingLabel = $teamView
                        ? 'Team'
                        : (collect($staffOptions)->firstWhere('id', $selectedStaffId)['name'] ?? 'Staff');
                @endphp
                <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="hidden sm:inline-flex items-center gap-2">
                    @csrf
                    <label class="text-[10px] uppercase tracking-widest text-white/70 font-semibold shrink-0">{{ __('admin.role_view.viewing') }}:</label>
                    <select name="staff_id"
                            onchange="this.form.submit()"
                            class="rounded-xl border-0 bg-white/15 text-white text-sm font-semibold px-3 py-2 focus:ring-2 focus:ring-white/40 min-w-[10rem]"
                            title="Filters Support workload and performance. Does not impersonate — actions still record as you."
                            aria-label="{{ __('admin.role_view.viewing') }}: Team">
                        <option value="0" @selected($teamView) class="text-gray-900">Team ▾</option>
                        @foreach ($staffOptions as $person)
                            <option value="{{ $person['id'] }}" @selected((int) $selectedStaffId === (int) $person['id']) class="text-gray-900">
                                {{ $person['name'] }}
                            </option>
                        @endforeach
                    </select>
                </form>
                <button type="button" @click="staffSheet = true"
                        class="sm:hidden inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold text-white"
                        aria-label="{{ __('admin.role_view.viewing') }}: {{ $viewingLabel }}">
                    <span>{{ __('admin.role_view.viewing') }}: {{ $viewingLabel }} ▾</span>
                </button>
                <x-site.action-panel :title="__('admin.role_view.viewing')" open="staffSheet">
                    <p class="text-xs text-gray-500 mb-3">Filters Support workload and performance only. You stay signed in as Admin.</p>
                    <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="space-y-1">
                        @csrf
                        <button type="submit" name="staff_id" value="0"
                                class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ $teamView ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                            Team
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
                <form method="POST" action="{{ route('admin.support.availability') }}" class="inline-flex items-center gap-2">
                    @csrf
                    <span class="inline-flex size-2.5 rounded-full {{ $dot }} ring-2 ring-white/40" aria-hidden="true"></span>
                    <select name="availability"
                            onchange="this.form.submit()"
                            class="rounded-xl border-0 bg-white/15 text-white text-sm font-semibold px-3 py-2 focus:ring-2 focus:ring-white/40">
                        <option value="online" @selected($availability === 'online') class="text-gray-900">Online</option>
                        <option value="away" @selected($availability === 'away') class="text-gray-900">Away</option>
                        <option value="offline" @selected($availability === 'offline') class="text-gray-900">Offline</option>
                    </select>
                </form>
            @else
                <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold text-white">
                    <span class="size-2.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                    {{ $agentsOnline }} online
                </span>
            @endif
        </x-slot:actions>
        <x-slot:stats>
            @php
                $longestSeconds = (int) ($counters['longest_waiting_seconds'] ?? 0);
                $longestLabel = sprintf('%02d:%02d', intdiv($longestSeconds, 60) % 60, $longestSeconds % 60);
                if ($longestSeconds >= 3600) {
                    $longestLabel = sprintf('%02d:%02d:%02d', intdiv($longestSeconds, 3600), intdiv($longestSeconds, 60) % 60, $longestSeconds % 60);
                }
                $kpiStrip = [
                    ['Waiting now', $counters['waiting'] ?? 0, route('admin.support.inbox'), 'Conversations in the queue that no Support person has accepted yet.'],
                    ['Longest waiting', $longestLabel, route('admin.support.inbox', ['filter' => 'waiting']), 'Age of the oldest unassigned waiting conversation (Africa/Dar_es_Salaam clock).'],
                    ['Active chats', $counters['active_chats'] ?? 0, route('admin.support.inbox'), 'Accepted conversations that are still open (assigned or active).'],
                    ['Open tickets', $counters['open_tickets'] ?? 0, route('admin.support.cases'), 'Tickets still open or in progress.'],
                    ['SLA at risk', $counters['sla_at_risk'] ?? 0, route('admin.support.cases'), 'Open tickets approaching their snapshotted due time.'],
                    ['Overdue', $counters['overdue'] ?? 0, route('admin.support.cases'), 'Open tickets past their snapshotted SLA due time.'],
                    ['Resolved today', $counters['resolved_today'] ?? 0, route('admin.support.performance'), 'Conversations and tickets closed today (Africa/Dar_es_Salaam calendar day).'],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-2.5">
                @foreach ($kpiStrip as [$label, $value, $url, $hint])
                    <a href="{{ $url }}" class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-3 py-2.5 hover:ring-brand/30 transition" title="{{ $hint }}">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold flex items-center gap-1">
                            <span>{{ $label }}</span>
                            <span class="inline-flex size-3.5 items-center justify-center rounded-full bg-white/80 text-[9px] font-bold text-brand" aria-label="{{ $hint }}">ⓘ</span>
                        </p>
                        <p class="text-xl font-bold text-gray-900 mt-1 tabular-nums {{ $label === 'Longest waiting' ? 'font-mono text-lg' : '' }}">
                            {{ $label === 'Longest waiting' ? $value : format_number((int) $value) }}
                        </p>
                    </a>
                @endforeach
            </div>
        </x-slot:stats>
    </x-admin.letterhead>

    <div class="grid lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-2">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $teamView ? 'Support queue' : 'My queue' }}</p>
                        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Waiting & open conversations</h2>
                    </div>
                    <a href="{{ route('admin.support.inbox') }}" class="text-xs font-semibold text-brand hover:underline">Inbox →</a>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($queue as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="flex items-start justify-between gap-3 px-5 py-3.5 hover:bg-brand-muted/20">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $item['name'] }}</p>
                                    @if (! empty($item['guest_label']))
                                        <p class="text-[11px] font-semibold text-amber-700">{{ $item['guest_label'] }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $item['preview'] ?: 'No messages yet' }}</p>
                                    <p class="text-[11px] text-gray-400 mt-1">
                                        {{ $item['needs_human'] ? 'Needs human' : ucfirst($item['status']) }}
                                        @if ($item['waiting_label']) · {{ $item['waiting_label'] }} @endif
                                    </p>
                                </div>
                                <span class="shrink-0 text-xs font-semibold text-brand">Open →</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-gray-500">No conversations waiting. Member, partner and guest support requests appear here.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-2">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $teamView ? 'Open tickets' : 'Tickets assigned to me' }}</p>
                        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Tickets needing attention</h2>
                    </div>
                    <a href="{{ route('admin.support.cases') }}" class="text-xs font-semibold text-brand hover:underline">All tickets →</a>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($tickets as $ticket)
                        <li>
                            <a href="{{ $ticket['url'] }}" class="flex items-start justify-between gap-3 px-5 py-3.5 hover:bg-brand-muted/20">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $ticket['contact'] }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $ticket['number'] }} · {{ $ticket['subject'] }}</p>
                                    <p class="text-[11px] text-gray-400 mt-1 capitalize">{{ $ticket['status'] }} · {{ $ticket['priority'] }}@if ($ticket['is_guest']) · Guest @endif</p>
                                </div>
                                <span class="shrink-0 text-xs font-semibold text-brand">Open →</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-gray-500">No open tickets right now.</li>
                    @endforelse
                </ul>
            </div>

            @if (! empty($recurringIssues))
                <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-amber-100">
                        <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Needs attention</p>
                        <h2 class="text-sm font-semibold text-amber-950 mt-0.5">Recurring issues</h2>
                        <p class="text-xs text-amber-900/80 mt-1">Aggregate only — does not merge or alter customer tickets.</p>
                    </div>
                    <ul class="divide-y divide-amber-100">
                        @foreach ($recurringIssues as $row)
                            <li class="px-5 py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-amber-950">{{ $row['label'] }}</p>
                                    <p class="text-[11px] text-amber-800/80">{{ $row['count'] }} tickets · last {{ $row['window_hours'] }}h</p>
                                </div>
                                <a href="{{ route('admin.support.cases') }}" class="shrink-0 text-xs font-semibold text-amber-900 hover:underline">Tickets →</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <aside class="space-y-6">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $teamView ? 'Team performance' : 'My performance' }}</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">{{ $performance['range_label'] ?? 'Today' }}</h2>
                @php
                    $fmtMin = function (?int $m): string {
                        if ($m === null) {
                            return '—';
                        }
                        if ($m < 60) {
                            return $m.'m';
                        }

                        return intdiv($m, 60).'h '.($m % 60).'m';
                    };
                @endphp
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Resolved today</dt><dd class="font-semibold tabular-nums">{{ format_number($performance['resolved'] ?? 0) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Avg first response</dt><dd class="font-semibold tabular-nums">{{ $fmtMin($performance['avg_first_response_minutes'] ?? null) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">First-contact resolution</dt><dd class="font-semibold tabular-nums">{{ isset($performance['first_contact_resolution']) ? $performance['first_contact_resolution'].'%' : '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">SLA met</dt><dd class="font-semibold tabular-nums">{{ isset($performance['sla_met']) ? $performance['sla_met'].'%' : '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Customer rating</dt><dd class="font-semibold tabular-nums">{{ $performance['customer_rating'] ?? '—' }}</dd></div>
                </dl>
                <a href="{{ route('admin.support.performance') }}" class="mt-4 inline-flex text-xs font-semibold text-brand hover:underline">View performance charts →</a>
            </div>
        </aside>
    </div>
</x-admin.layout>
