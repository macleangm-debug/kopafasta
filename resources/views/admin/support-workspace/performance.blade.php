@php
    $range = $performance['range'] ?? 'today';
@endphp
<x-admin.layout title="My performance" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        :title="$agent?->name ? 'My performance · '.$agent->name : 'My performance'"
        subtitle="Personal support metrics from existing tickets and conversations. Missing timers and ratings are reported as gaps, not invented.">
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

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        @foreach ([
            ['Conversations handled', $performance['conversations_handled'] ?? 0],
            ['Tickets assigned', $performance['tickets_assigned'] ?? 0],
            ['Tickets resolved', $performance['resolved'] ?? 0],
            ['Open / backlog', $performance['open_backlog'] ?? 0],
            ['Escalations', $performance['escalations'] ?? 0],
            ['Avg first response', null],
            ['Avg resolution time', null],
            ['First-contact resolution', null],
            ['SLA met', null],
            ['Customer rating', null],
        ] as [$label, $value])
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm px-4 py-4">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $label }}</p>
                <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $value === null ? '—' : format_number($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 text-sm text-amber-950 space-y-2">
        <p class="font-semibold">Metric gaps (not invented)</p>
        <ul class="list-disc pl-5 space-y-1 text-amber-900/90">
            @foreach (($performance['gaps'] ?? []) as $key => $note)
                <li>{{ is_string($note) ? $note : $key }}</li>
            @endforeach
        </ul>
    </div>
</x-admin.layout>
