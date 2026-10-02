@php
    $range = $range ?? '30d';
    $assistants = $assistants ?? [];
    $volume = $volume ?? [];
    $focusKey = $focusKey ?? '';
@endphp
<x-admin.layout title="Digital Support Assistants" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        title="Digital Support Assistants"
        subtitle="Settings-backed personas (not Staff logins). Overview first — open a profile for detail.">
        <x-slot:actions>
            <a href="{{ route('admin.settings.support') }}?tab=msaidizi"
               class="inline-flex rounded-xl bg-white/15 ring-1 ring-white/25 text-white text-sm font-semibold px-3 py-2 hover:bg-white/20">
                Settings → Digital Assistants
            </a>
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

    <div class="mb-5 grid grid-cols-2 lg:grid-cols-4 gap-2">
        @foreach ([
            ['Total', $volume['total_conversations'] ?? 0],
            ['Guest', $volume['guest_conversations'] ?? 0],
            ['Member', $volume['member_conversations'] ?? 0],
            ['Partner', $volume['partner_conversations'] ?? 0],
            ['Digital-resolved', $volume['digital_resolved'] ?? 0],
            ['Human handovers', $volume['human_handovers'] ?? 0],
            ['Human-resolved', $volume['human_resolved'] ?? 0],
            ['CSAT', ($volume['avg_csat'] ?? null) !== null ? ($volume['avg_csat'].' ★') : '—'],
        ] as [$label, $value])
            <div class="rounded-xl bg-white ring-1 ring-slate-200 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ $label }}</p>
                <p class="text-lg font-bold text-slate-900 tabular-nums">{{ is_numeric($value) ? format_number($value) : $value }}</p>
            </div>
        @endforeach
    </div>

    <p class="mb-4 text-sm text-slate-600">
        Configured assistants: <span class="font-bold text-brand">{{ count($assistants) }}</span>
        · <a href="{{ route('admin.settings.support') }}?tab=msaidizi" class="text-brand font-semibold hover:underline">Configure names</a>
        · <a href="{{ route('admin.support.performance') }}" class="text-brand font-semibold hover:underline">Human performance →</a>
    </p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($assistants as $row)
            <article id="assistant-{{ $row['key'] }}"
                     class="rounded-2xl bg-white ring-1 shadow-sm p-5 space-y-3 {{ $focusKey === $row['key'] ? 'ring-brand/40 ring-2' : 'ring-brand/10' }}">
                <div class="flex items-center gap-3">
                    <span class="size-11 rounded-xl bg-brand text-white grid place-items-center font-bold">{{ mb_substr($row['name'], 0, 1) }}</span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">{{ $row['name'] }}</h2>
                        <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">Digital Assistant</p>
                    </div>
                </div>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Total handled</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['conversations_handled']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Guests</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['guests_handled'] ?? 0) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Members / Partners</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number(($row['members_handled'] ?? 0) + ($row['partners_handled'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Resolved (auto)</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['resolved_without_human']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Human handovers</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['handed_over']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Guest repeats</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['guest_repeat_conversations'] ?? 0) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">CTA shown</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['registration_cta_shown'] ?? 0) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">CTA clicked</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['registration_cta_clicked'] ?? 0) }}</dd>
                    </div>
                    <div class="rounded-xl bg-amber-50 px-3 py-2 col-span-2">
                        <dt class="text-[10px] uppercase tracking-wider text-amber-800 font-semibold">CSAT ★</dt>
                        <dd class="font-bold tabular-nums text-amber-950">
                            {{ $row['avg_rating'] !== null ? $row['avg_rating'].' / 5' : '—' }}
                            <span class="text-xs font-medium text-amber-800/80">({{ $row['ratings_count'] }})</span>
                        </dd>
                    </div>
                </dl>
            </article>
        @empty
            <p class="text-sm text-slate-500 col-span-full">No digital assistants configured. Add names in Settings → Support → Digital Assistants.</p>
        @endforelse
    </div>
</x-admin.layout>
