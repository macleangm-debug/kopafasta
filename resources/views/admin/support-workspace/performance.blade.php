@php
    $range = $performance['range'] ?? 'today';
    $teamView = (bool) ($teamView ?? true);
    $title = $teamView
        ? 'Team performance today'
        : ('My performance'.($agent?->name ? ' · '.$agent->name : ''));
    if ($range !== 'today') {
        $title = ($teamView ? 'Team performance' : 'My performance').' · '.($performance['range_label'] ?? $range);
    }
    $fmtMinutes = function (?int $m): string {
        if ($m === null) {
            return '—';
        }
        if ($m < 60) {
            return $m.'m';
        }

        return intdiv($m, 60).'h '.($m % 60).'m';
    };
@endphp
<x-admin.layout :title="$title" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        :title="$title"
        subtitle="Canonical Support metrics from accepted_at, sla_due_at, resolved_at and ratings. Empty ranges show — not invented numbers.">
        <x-slot:actions>
            <div class="inline-flex rounded-xl bg-white/15 ring-1 ring-white/20 p-1 text-sm font-semibold">
                @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days'] as $key => $label)
                    <a href="{{ route('admin.support.performance', ['range' => $key]) }}"
                       class="px-3 py-1.5 rounded-lg {{ $range === $key ? 'bg-white text-brand' : 'text-white/80 hover:text-white' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </x-slot:actions>
    </x-admin.letterhead>

    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        @foreach ([
            ['Resolved today', $performance['resolved'] ?? 0, 'Conversations + tickets closed in this range.'],
            ['Avg first response', $fmtMinutes($performance['avg_first_response_minutes'] ?? null), 'accepted_at minus waiting_since / created_at.'],
            ['First-contact resolution', isset($performance['first_contact_resolution']) ? $performance['first_contact_resolution'].'%' : '—', 'Closed conversations with no linked ticket.'],
            ['SLA met', isset($performance['sla_met']) ? $performance['sla_met'].'%' : '—', 'Resolved before snapshotted sla_due_at.'],
            ['Customer rating ★', $performance['customer_rating'] ?? '—', 'Average conversation rating in range.'],
        ] as [$label, $value, $hint])
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm px-4 py-4" title="{{ $hint }}">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold flex items-center gap-1">
                    <span>{{ $label }}</span>
                    <span class="inline-flex size-3.5 items-center justify-center rounded-full bg-brand-muted text-[9px] font-bold text-brand" aria-label="{{ $hint }}">ⓘ</span>
                </p>
                <p class="text-2xl font-bold text-gray-900 mt-2 tabular-nums">{{ is_numeric($value) ? format_number($value) : $value }}</p>
            </div>
        @endforeach
    </div>

    @php
        $received = (int) ($performance['conversations_received'] ?? 0);
        $resolvedConv = (int) ($performance['resolved_conversations'] ?? 0);
        $maxBar = max(1, $received, $resolvedConv);
        $slaPct = (int) ($performance['sla_met'] ?? 0);
        $rating = (float) ($performance['customer_rating'] ?? 0);
        $firstResp = (int) ($performance['avg_first_response_minutes'] ?? 0);
    @endphp

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-900">Conversations received / resolved</h2>
            <div class="mt-4 space-y-3">
                <div>
                    <div class="flex justify-between text-xs text-slate-500 mb-1"><span>Received</span><span class="tabular-nums font-semibold text-slate-800">{{ $received }}</span></div>
                    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-brand rounded-full" style="width: {{ ($received / $maxBar) * 100 }}%"></div></div>
                </div>
                <div>
                    <div class="flex justify-between text-xs text-slate-500 mb-1"><span>Resolved</span><span class="tabular-nums font-semibold text-slate-800">{{ $resolvedConv }}</span></div>
                    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-emerald-600 rounded-full" style="width: {{ ($resolvedConv / $maxBar) * 100 }}%"></div></div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-900">Average first-response time</h2>
            <p class="mt-4 text-3xl font-bold text-gray-900 tabular-nums">{{ $fmtMinutes($performance['avg_first_response_minutes'] ?? null) }}</p>
            <p class="text-xs text-slate-500 mt-1">From queue wait to Accept</p>
        </section>

        <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-900">SLA met %</h2>
            <div class="mt-4 flex items-end gap-3">
                <p class="text-3xl font-bold text-gray-900 tabular-nums">{{ isset($performance['sla_met']) ? $slaPct.'%' : '—' }}</p>
                <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden mb-2">
                    <div class="h-full bg-brand-gold rounded-full" style="width: {{ min(100, $slaPct) }}%"></div>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-1">{{ (int) ($performance['sla_met_count'] ?? 0) }} of {{ (int) ($performance['sla_eligible_count'] ?? 0) }} snapshotted tickets</p>
        </section>

        <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-900">Average customer rating</h2>
            <p class="mt-4 text-3xl font-bold text-amber-500 tracking-widest">{{ $rating > 0 ? str_repeat('★', (int) round($rating)).str_repeat('☆', max(0, 5 - (int) round($rating))) : '—' }}</p>
            <p class="text-sm text-slate-600 mt-1 tabular-nums">{{ $performance['customer_rating'] ?? '—' }} / 5</p>
        </section>
    </div>

    <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Top Issues</h2>
            <p class="text-xs text-slate-500">Canonical Issue taxonomy counts for this range</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse (($performance['top_issues'] ?? []) as $row)
                <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                    <span class="font-semibold text-gray-800">{{ str_replace('_', ' ', ucfirst($row['issue'])) }}</span>
                    <span class="tabular-nums font-bold text-brand">{{ format_number($row['count']) }}</span>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-gray-500">No tickets in this range yet.</li>
            @endforelse
        </ul>
    </section>

    @if (! empty($performance['gaps']))
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 text-sm text-amber-950 space-y-2">
            <p class="font-semibold">Notes for this range</p>
            <ul class="list-disc pl-5 space-y-1 text-amber-900/90">
                @foreach ($performance['gaps'] as $note)
                    @if (is_string($note) && $note !== '')
                        <li>{{ $note }}</li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif
</x-admin.layout>
