@php
    $range = $range ?? '30d';
    $assistants = $assistants ?? [];
    $volume = $volume ?? [];
    $focusKey = $focusKey ?? '';
    $focus = $focusKey !== ''
        ? collect($assistants)->firstWhere('key', $focusKey)
        : null;
@endphp
<x-admin.layout title="Digital Support Assistants" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        title="{{ $focus ? $focus['name'] : 'Digital Support Assistants' }}"
        subtitle="{{ $focus ? 'Digital Assistant 360 — Settings persona, not a Staff login.' : 'Settings-backed personas (not Staff logins). Overview first — open a profile for detail.' }}">
        <x-slot:actions>
            <a href="{{ route('admin.settings.support') }}?tab=msaidizi"
               class="inline-flex rounded-xl bg-white/15 ring-1 ring-white/25 text-white text-sm font-semibold px-3 py-2 hover:bg-white/20">
                Configure
            </a>
            @if ($focus)
                <a href="{{ route('admin.support.assistants', ['range' => $range]) }}"
                   class="inline-flex rounded-xl bg-white/15 ring-1 ring-white/25 text-white text-sm font-semibold px-3 py-2 hover:bg-white/20">
                    ← All assistants
                </a>
            @endif
            <div class="inline-flex rounded-xl bg-white/15 ring-1 ring-white/20 p-1 text-sm font-semibold">
                @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days'] as $key => $label)
                    <a href="{{ route('admin.support.assistants', array_filter(['range' => $key, 'persona' => $focusKey ?: null])) }}"
                       class="px-3 py-1.5 rounded-lg {{ $range === $key ? 'bg-white text-brand' : 'text-white/80 hover:text-white' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </x-slot:actions>
    </x-admin.letterhead>

    @if ($focus)
        {{-- Digital Assistant 360 — same visual language as Human profile cards --}}
        <article class="mb-6 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
            <div class="px-5 py-5 sm:px-6 bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white">
                <div class="flex items-center gap-4">
                    <span class="size-14 rounded-2xl bg-white/15 ring-1 ring-white/25 grid place-items-center text-2xl font-bold">{{ mb_substr($focus['name'], 0, 1) }}</span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold tracking-tight">{{ $focus['name'] }}</h2>
                            <span class="inline-flex rounded-full bg-brand-gold text-brand text-[10px] font-bold px-2.5 py-0.5 uppercase tracking-wide">Digital Assistant</span>
                            <span class="inline-flex rounded-full bg-white/15 text-white text-[10px] font-bold px-2.5 py-0.5">Configured · Active</span>
                        </div>
                        <p class="mt-1 text-sm text-white/75">Settings persona · no Staff account · no login</p>
                    </div>
                </div>
            </div>
            <div class="p-5 sm:p-6 grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ([
                    ['Total handled', $focus['conversations_handled'] ?? 0],
                    ['Guests', $focus['guests_handled'] ?? 0],
                    ['Members / Partners', ($focus['members_handled'] ?? 0) + ($focus['partners_handled'] ?? 0)],
                    ['Auto-resolved', $focus['resolved_without_human'] ?? 0],
                    ['Human handovers', $focus['handed_over'] ?? 0],
                    ['Guest repeats', $focus['guest_repeat_conversations'] ?? 0],
                    ['CTA shown', $focus['registration_cta_shown'] ?? 0],
                    ['CTA clicked', $focus['registration_cta_clicked'] ?? 0],
                ] as [$label, $value])
                    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ $label }}</p>
                        <p class="text-lg font-bold text-slate-900 tabular-nums">{{ format_number($value) }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-3 py-2.5 col-span-2 lg:col-span-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">CSAT ★</p>
                    <p class="text-lg font-bold text-amber-950 tabular-nums">
                        {{ ($focus['avg_rating'] ?? null) !== null ? $focus['avg_rating'].' / 5' : '—' }}
                        <span class="text-xs font-medium text-amber-800/80">({{ format_number($focus['ratings_count'] ?? 0) }} ratings)</span>
                    </p>
                </div>
            </div>
            <div class="px-5 pb-5 sm:px-6 flex flex-wrap gap-2">
                <a href="{{ route('admin.settings.support') }}?tab=msaidizi"
                   class="inline-flex rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Configure</a>
                <a href="{{ route('admin.support.assistants', ['range' => $range]) }}"
                   class="inline-flex rounded-xl ring-1 ring-brand/20 text-brand text-sm font-semibold px-4 py-2.5 hover:bg-brand-muted/40">All assistants</a>
                <a href="{{ route('admin.support.performance') }}"
                   class="inline-flex rounded-xl ring-1 ring-brand/20 text-brand text-sm font-semibold px-4 py-2.5 hover:bg-brand-muted/40">Human performance →</a>
            </div>
        </article>
    @else
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
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($assistants as $row)
            <a href="{{ route('admin.support.assistants', ['persona' => $row['key'], 'range' => $range]) }}"
               id="assistant-{{ $row['key'] }}"
               class="block rounded-2xl bg-white ring-1 shadow-sm p-5 space-y-3 transition hover:ring-brand/30 hover:shadow-md {{ $focusKey === $row['key'] ? 'ring-brand/40 ring-2' : 'ring-brand/10' }}">
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
                        <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Auto-resolved</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ format_number($row['resolved_without_human']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-amber-50 px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wider text-amber-800 font-semibold">CSAT ★</dt>
                        <dd class="font-bold tabular-nums text-amber-950">
                            {{ $row['avg_rating'] !== null ? $row['avg_rating'].' / 5' : '—' }}
                        </dd>
                    </div>
                </dl>
                <p class="text-xs font-semibold text-brand">Open 360 →</p>
            </a>
        @empty
            <p class="text-sm text-slate-500 col-span-full">No digital assistants configured. Add names in Settings → Support → Digital Assistants.</p>
        @endforelse
    </div>
</x-admin.layout>
