@php
    $agent = $dashboard['agent'] ?? null;
    $availability = $dashboard['availability'] ?? 'offline';
    $counters = $dashboard['counters'] ?? [];
    $queue = $dashboard['queue'] ?? [];
    $tickets = $dashboard['tickets'] ?? [];
    $performance = $dashboard['performance'] ?? [];
    $dot = match ($availability) {
        'online' => 'bg-emerald-400',
        'away' => 'bg-amber-400',
        default => 'bg-gray-400',
    };
@endphp
<x-admin.layout title="Customer Support" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        title="Customer Support"
        :subtitle="$agent?->name ?: 'Help members, answer conversations and resolve cases.'"
    >
        <x-slot:meta>Help members, answer conversations and resolve cases.</x-slot:meta>
        <x-slot:actions>
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
        </x-slot:actions>
        <x-slot:stats>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                @foreach ([
                    ['Unread', $counters['unread'] ?? 0, route('admin.support.inbox')],
                    ['Waiting', $counters['waiting'] ?? 0, route('admin.support.inbox')],
                    ['Assigned to me', $counters['assigned_to_me'] ?? 0, route('admin.support.inbox')],
                    ['Open tickets', $counters['open_tickets'] ?? 0, route('admin.support.cases')],
                    ['Overdue', $counters['overdue'], route('admin.support.performance')],
                ] as [$label, $value, $url])
                    <a href="{{ $url }}" class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-3 py-3 hover:ring-brand/30 transition">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $label }}</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">
                            {{ $value === null ? '—' : format_number($value) }}
                        </p>
                        @if ($label === 'Overdue' && $value === null)
                            <p class="text-[11px] text-gray-500 mt-1">SLA clock not recorded yet</p>
                        @endif
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
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">My queue</p>
                        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Waiting & assigned conversations</h2>
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
                        <li class="px-5 py-10 text-center text-sm text-gray-500">No conversations waiting. When a member asks to speak to a person, they appear here.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-2">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Tickets assigned to me</p>
                        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Cases needing attention</h2>
                    </div>
                    <a href="{{ route('admin.support.cases') }}" class="text-xs font-semibold text-brand hover:underline">All cases →</a>
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
                        <li class="px-5 py-10 text-center text-sm text-gray-500">No open cases assigned right now.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <aside class="space-y-6">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">My performance</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">{{ $performance['range_label'] ?? 'Today' }}</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Resolved today</dt><dd class="font-semibold tabular-nums">{{ format_number($performance['resolved'] ?? 0) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Avg first response</dt><dd class="font-semibold text-gray-400">—</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">First-contact resolution</dt><dd class="font-semibold text-gray-400">—</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">SLA met</dt><dd class="font-semibold text-gray-400">—</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Customer rating</dt><dd class="font-semibold text-gray-400">—</dd></div>
                </dl>
                <p class="text-[11px] text-gray-400 mt-3">Timers and CSAT show once those events are recorded.</p>
                <a href="{{ route('admin.support.performance') }}" class="mt-4 inline-flex text-xs font-semibold text-brand hover:underline">View performance →</a>
            </div>
        </aside>
    </div>
</x-admin.layout>
