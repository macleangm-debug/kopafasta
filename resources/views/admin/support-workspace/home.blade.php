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
    $heroSubtitle = $teamView
        ? __('admin.support.home.hero_subtitle_team')
        : __('admin.support.home.hero_subtitle_self');
    $kpiLongestKey = __('admin.support.home.kpi_longest_waiting');
@endphp
<x-admin.layout :title="__('admin.support.home.title')" heading="" subheading="">
    <x-admin.letterhead
        :kicker="__('admin.support.kicker')"
        :title="__('admin.support.home.title')"
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
                            class="rounded-xl border-0 bg-brand-gold text-brand text-sm font-bold px-3 py-2 focus:ring-2 focus:ring-white/40 min-w-[10rem] shadow-sm"
                            title="Filters Support workload and performance. Does not impersonate — actions still record as you."
                            aria-label="{{ __('admin.role_view.viewing') }}: Team">
                        <option value="0" @selected($teamView) class="text-gray-900">Team ▾</option>
                        <optgroup label="Humans" class="text-gray-900">
                            @foreach ($staffOptions as $person)
                                <option value="{{ $person['id'] }}" @selected((int) $selectedStaffId === (int) $person['id']) class="text-gray-900">
                                    {{ $person['name'] }} · Human
                                </option>
                            @endforeach
                        </optgroup>
                    </select>
                </form>
                @php $digitalAssistants = $dashboard['digital_assistants'] ?? []; @endphp
                @if (count($digitalAssistants) > 0)
                    <div class="hidden sm:inline-flex items-center gap-1.5">
                        <span class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Assistants</span>
                        <select
                            onchange="if (this.value) window.location = this.value"
                            class="rounded-xl border-0 bg-white/15 text-white text-sm font-semibold px-3 py-2 focus:ring-2 focus:ring-white/40 min-w-[9rem]"
                            aria-label="Digital Assistants">
                            <option value="">Digital Assistants ▾</option>
                            @foreach ($digitalAssistants as $assistant)
                                <option value="{{ $assistant['url'] }}" class="text-gray-900">
                                    {{ $assistant['name'] }} · Digital Assistant
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="button" @click="staffSheet = true"
                        class="sm:hidden inline-flex items-center gap-1.5 rounded-xl bg-brand-gold text-brand font-bold px-3 py-2 text-sm shadow-sm"
                        aria-label="{{ __('admin.role_view.viewing') }}: {{ $viewingLabel }}">
                    <span>{{ __('admin.role_view.viewing') }}: {{ $viewingLabel }} ▾</span>
                </button>
                <x-site.action-panel :title="__('admin.role_view.viewing')" open="staffSheet">
                    <p class="text-xs text-gray-500 mb-3">Humans filter workload. Digital Assistants open performance profiles — not logins.</p>
                    <form method="POST" action="{{ route('admin.role-view.select-staff') }}" class="space-y-1">
                        @csrf
                        <button type="submit" name="staff_id" value="0"
                                class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold {{ $teamView ? 'bg-brand/10 text-brand' : 'hover:bg-slate-50 text-gray-900' }}">
                            Team
                        </button>
                        <p class="pt-2 pb-1 text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Humans</p>
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
                                    <span class="inline-flex rounded-full bg-brand-muted text-brand text-[10px] font-bold px-2 py-0.5">Digital Assistant</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
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
                            class="inline-flex items-center rounded-xl bg-brand-gold text-brand font-bold px-3.5 py-2 text-sm shadow-sm ring-1 ring-white/30"
                            title="{{ $isOnline ? 'Click to go Offline. Assigned chats stay yours; you cannot Accept new waiting chats.' : 'Click to go Online and Accept waiting chats.' }}">
                        {{ $availLabel }}
                    </button>
                </form>
            @else
                <span class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold text-white">
                    <span class="size-2.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                    {{ __('admin.support.home.agents_online', ['count' => $agentsOnline]) }}
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
                    [__('admin.support.home.kpi_waiting'), $counters['waiting'] ?? 0, route('admin.support.inbox'), __('admin.support.home.kpi_hint_waiting')],
                    [$kpiLongestKey, $longestLabel, route('admin.support.inbox', ['filter' => 'waiting']), __('admin.support.home.kpi_hint_longest_waiting')],
                    [__('admin.support.home.kpi_active'), $counters['active_chats'] ?? 0, route('admin.support.inbox'), __('admin.support.home.kpi_hint_active')],
                    [__('admin.support.home.kpi_open_tickets'), $counters['open_tickets'] ?? 0, route('admin.support.cases'), __('admin.support.home.kpi_hint_open_tickets')],
                    [__('admin.support.home.kpi_sla_at_risk'), $counters['sla_at_risk'] ?? 0, route('admin.support.cases'), __('admin.support.home.kpi_hint_sla_at_risk')],
                    [__('admin.support.home.kpi_overdue'), $counters['overdue'] ?? 0, route('admin.support.cases'), __('admin.support.home.kpi_hint_overdue')],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                @foreach ($kpiStrip as [$label, $value, $url, $hint])
                    <a href="{{ $url }}" class="rounded-2xl bg-gradient-to-br from-white to-brand-muted/30 ring-1 ring-brand/15 shadow-sm px-3.5 py-3.5 hover:ring-brand/35 hover:shadow-md transition" title="{{ $hint }}">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold flex items-center gap-1">
                            <span>{{ $label }}</span>
                            <span class="inline-flex size-3.5 items-center justify-center rounded-full bg-brand-muted text-[9px] font-bold text-brand" aria-label="{{ $hint }}">ⓘ</span>
                        </p>
                        <p class="text-2xl font-extrabold text-gray-900 mt-2 tabular-nums tracking-tight {{ $label === $kpiLongestKey ? 'font-mono text-xl' : '' }}">
                            {{ $label === $kpiLongestKey ? $value : format_number((int) $value) }}
                        </p>
                    </a>
                @endforeach
            </div>
        </x-slot:stats>
    </x-admin.letterhead>

    <div class="grid lg:grid-cols-3 gap-5">
        <section class="lg:col-span-2 space-y-5">
            <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm bg-white">
                <div class="relative overflow-hidden kf-premium-panel px-5 py-4 text-white">
                    <div class="absolute -right-10 -top-10 size-28 rounded-full bg-white/10 pointer-events-none" aria-hidden="true"></div>
                    <div class="relative flex items-center justify-between gap-2">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ $teamView ? __('admin.support.home.queue_kicker_team') : __('admin.support.home.queue_kicker_mine') }}</p>
                            <h2 class="text-base font-bold tracking-tight mt-0.5">{{ __('admin.support.home.queue_title') }}</h2>
                        </div>
                        <a href="{{ route('admin.support.inbox') }}" class="text-xs font-bold text-brand-gold hover:underline shrink-0">{{ __('admin.support.home.inbox_link') }}</a>
                    </div>
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
                                    <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $item['preview'] ?: __('admin.support.inbox.no_messages_yet') }}</p>
                                    <p class="text-[11px] text-gray-400 mt-1">
                                        {{ $item['needs_human'] ? __('admin.support.home.needs_human') : ucfirst($item['status']) }}
                                        @if ($item['waiting_label']) · {{ $item['waiting_label'] }} @endif
                                    </p>
                                </div>
                                <span class="shrink-0 text-xs font-semibold text-brand">{{ __('admin.support.home.open') }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-gray-500">{{ __('admin.support.home.empty_queue') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm bg-white">
                <div class="relative overflow-hidden kf-premium-panel px-5 py-4 text-white">
                    <div class="absolute -right-10 -top-10 size-28 rounded-full bg-white/10 pointer-events-none" aria-hidden="true"></div>
                    <div class="relative flex items-center justify-between gap-2">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ $teamView ? __('admin.support.home.tickets_kicker_team') : __('admin.support.home.tickets_kicker_mine') }}</p>
                            <h2 class="text-base font-bold tracking-tight mt-0.5">{{ __('admin.support.home.tickets_title') }}</h2>
                        </div>
                        <a href="{{ route('admin.support.cases') }}" class="text-xs font-bold text-brand-gold hover:underline shrink-0">{{ __('admin.support.home.all_tickets') }}</a>
                    </div>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($tickets as $ticket)
                        <li>
                            <a href="{{ $ticket['url'] }}" class="flex items-start justify-between gap-3 px-5 py-3.5 hover:bg-brand-muted/20">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $ticket['contact'] }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $ticket['number'] }} · {{ $ticket['subject'] }}</p>
                                    <p class="text-[11px] text-gray-400 mt-1 capitalize">{{ $ticket['status'] }} · {{ $ticket['priority'] }}@if ($ticket['is_guest']) · {{ __('admin.support.home.guest') }} @endif</p>
                                </div>
                                <span class="shrink-0 text-xs font-semibold text-brand">{{ __('admin.support.home.open') }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-gray-500">{{ __('admin.support.home.empty_tickets') }}</li>
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

        <aside class="space-y-5">
            <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm bg-white">
                <div class="relative overflow-hidden kf-premium-panel px-5 py-4 text-white">
                    <div class="absolute -right-8 -top-8 size-24 rounded-full bg-white/10 pointer-events-none" aria-hidden="true"></div>
                    <div class="relative">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ $teamView ? __('admin.support.home.performance_kicker_team') : __('admin.support.home.performance_kicker_mine') }}</p>
                        <h2 class="text-base font-bold tracking-tight mt-0.5">{{ $performance['range_label'] ?? __('admin.support.home.performance_today') }}</h2>
                    </div>
                </div>
                <div class="p-5">
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
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('admin.support.home.resolved_today') }}</dt><dd class="font-semibold tabular-nums">{{ format_number($performance['resolved'] ?? 0) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('admin.support.home.avg_first_response') }}</dt><dd class="font-semibold tabular-nums">{{ $fmtMin($performance['avg_first_response_minutes'] ?? null) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('admin.support.home.first_contact_resolution') }}</dt><dd class="font-semibold tabular-nums">{{ isset($performance['first_contact_resolution']) ? $performance['first_contact_resolution'].'%' : '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('admin.support.home.sla_met') }}</dt><dd class="font-semibold tabular-nums">{{ isset($performance['sla_met']) ? $performance['sla_met'].'%' : '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('admin.support.home.customer_rating') }}</dt><dd class="font-semibold tabular-nums">{{ $performance['customer_rating'] ?? '—' }}</dd></div>
                </dl>
                <a href="{{ route('admin.support.performance') }}" class="mt-4 inline-flex text-xs font-semibold text-brand hover:underline">{{ __('admin.support.home.view_performance') }}</a>
                </div>
            </div>
        </aside>
    </div>
</x-admin.layout>
