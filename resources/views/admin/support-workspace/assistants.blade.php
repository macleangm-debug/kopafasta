@php
    $range = $range ?? '30d';
    $assistants = $assistants ?? [];
@endphp
<x-admin.layout title="Digital Support Assistants" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        title="Digital Support Assistants"
        subtitle="Settings-backed personas (not Staff logins). Metrics from real SupportConversation and CSAT data only.">
        <x-slot:actions>
            <div class="inline-flex rounded-xl bg-white/15 ring-1 ring-white/20 p-1 text-sm font-semibold">
                @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days'] as $key => $label)
                    <a href="{{ route('admin.support.assistants', ['range' => $key]) }}"
                       class="px-3 py-1.5 rounded-lg {{ $range === $key ? 'bg-white text-brand' : 'text-white/80 hover:text-white' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </x-slot:actions>
    </x-admin.letterhead>

    <p class="mb-4 text-sm text-slate-600">
        Configured assistants: <span class="font-bold text-brand">{{ count($assistants) }}</span>
        · Manage names in Settings → Support → Msaidizi (max 5).
        <a href="{{ route('admin.support.performance') }}" class="text-brand font-semibold hover:underline ml-2">Human agent performance →</a>
    </p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($assistants as $row)
            <article class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-3">
                <div class="flex items-center gap-3">
                    <span class="size-11 rounded-xl bg-brand text-white grid place-items-center font-bold">{{ mb_substr($row['name'], 0, 1) }}</span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">{{ $row['name'] }}</h2>
                        <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">Digital assistant</p>
                    </div>
                </div>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Handled</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['conversations_handled']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Resolved (auto)</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['resolved_without_human']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Handed over</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['handed_over']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Resolution rate</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ $row['resolution_rate'] !== null ? $row['resolution_rate'].'%' : '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Handover rate</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ $row['handover_rate'] !== null ? $row['handover_rate'].'%' : '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-amber-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-amber-800 font-semibold">CSAT ★</dt>
                        <dd class="font-bold tabular-nums text-amber-950">
                            {{ $row['avg_rating'] !== null ? $row['avg_rating'].' / 5' : '—' }}
                            <span class="text-xs font-medium text-amber-800/80">({{ $row['ratings_count'] }})</span>
                        </dd>
                    </div>
                </dl>
            </article>
        @empty
            <p class="text-sm text-slate-500 col-span-full">No digital assistants configured. Add up to 5 names in Settings → Support.</p>
        @endforelse
    </div>
</x-admin.layout>
