<x-site.vendor-layout
    :title="$vendor->isInsurance() ? __('site.partner_portal.cover_jobs_title') : __('site.partner_portal.jobs_title')"
    active="tasks">
    @php
        $isInsurance = $vendor->isInsurance();
        $tabs = [
            'all'         => $isInsurance ? __('site.partner_portal.cover_jobs_title') : __('site.partner_portal.jobs_title'),
            'assigned'    => 'Assigned',
            'in_progress' => 'In progress',
            'completed'   => 'Completed',
            'rejected'    => 'Rejected',
            'cancelled'   => 'Cancelled',
        ];
        $current = $status ?: 'all';
        $badge = fn ($s) => match ($s) {
            'assigned'    => 'bg-amber-100 text-amber-700',
            'in_progress' => 'bg-brand-muted text-brand',
            'completed'   => 'bg-emerald-100 text-emerald-700',
            'rejected'    => 'bg-red-100 text-red-700',
            'cancelled'   => 'bg-gray-100 text-gray-600',
            default       => 'bg-gray-100 text-gray-600',
        };
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-extrabold mb-1">
                {{ $isInsurance ? __('site.partner_portal.cover_jobs_title') : __('site.partner_portal.jobs_title') }}
            </h1>
            <p class="text-sm text-gray-500">
                {{ $isInsurance ? __('site.partner_portal.cover_jobs_subtitle') : __('site.partner_portal.jobs_subtitle') }}
            </p>
        </div>
        @unless ($isInsurance)
            <a href="{{ route('site.partner.calendar') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:underline">
                {{ __('site.partner_portal.nav_calendar') }} →
            </a>
        @endunless
    </div>

    {{-- Filter pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($tabs as $k => $label)
            <a href="{{ route('site.partner.tasks', $k === 'all' ? [] : ['status' => $k]) }}"
               class="px-3 py-1.5 rounded-full text-xs font-semibold border
                      {{ $current === $k ? 'bg-brand text-white border-brand' : 'bg-white text-gray-700 border-gray-300 hover:bg-brand-muted/40' }}">
                {{ $k === 'all' ? 'All' : $label }}
            </a>
        @endforeach
    </div>

    @if ($tasks->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">No tasks here.</div>
    @else
        {{-- Table on desktop, cards on mobile --}}
        <div class="hidden lg:block glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-3">Task</th>
                        <th class="text-left px-4 py-3">Priority</th>
                        <th class="text-left px-4 py-3">Customer</th>
                        <th class="text-left px-4 py-3">Loan</th>
                        <th class="text-left px-4 py-3">Due</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($tasks as $t)
                        @php
                            $priority = $t->priorityMeta();
                            $priorityBadge = match ($priority['tone']) {
                                'red'    => 'bg-red-100 text-red-700',
                                'amber'  => 'bg-amber-100 text-amber-700',
                                'indigo' => 'bg-indigo-100 text-brand',
                                default  => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold">{{ ucfirst(str_replace('_',' ', $t->task_type)) }}<div class="text-[11px] text-gray-400">#{{ $t->id }}</div></td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $priorityBadge }}">{{ $priority['label'] }}</span></td>
                            <td class="px-4 py-3">{{ $t->customer_name ?: ($t->loanApplication?->customer?->name ?? '—') }}<div class="text-[11px] text-gray-400">{{ $t->customer_phone ?: ($t->loanApplication?->customer?->phone ?? '') }}</div></td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($t->loan)
                                    <span class="font-mono text-xs">{{ $t->loan->loan_number ?? '#'.$t->loan->id }}</span>
                                    <div class="text-[11px] text-gray-400">{{ str_replace('_', ' ', $t->loan->status) }}</div>
                                @elseif ($t->loanApplication)
                                    <span class="text-xs">App #{{ $t->loanApplication->id }}</span>
                                    <div class="text-[11px] text-gray-400">{{ str_replace('_', ' ', $t->loanApplication->status) }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $t->due_at ? $t->due_at->format('d M Y H:i') : '—' }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge($t->status) }}">{{ str_replace('_',' ', $t->status) }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('site.partner.task', $t) }}" class="text-brand hover:underline text-sm font-semibold">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="lg:hidden space-y-3">
            @foreach ($tasks as $t)
                @php
                    $priority = $t->priorityMeta();
                    $priorityBadge = match ($priority['tone']) {
                        'red'    => 'bg-red-100 text-red-700',
                        'amber'  => 'bg-amber-100 text-amber-700',
                        'indigo' => 'bg-indigo-100 text-brand',
                        default  => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <a href="{{ route('site.partner.task', $t) }}" class="block glass-card rounded-2xl ring-1 ring-brand/10 p-4 hover:shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-sm">{{ ucfirst(str_replace('_',' ', $t->task_type)) }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $t->customer_name ?: ($t->loanApplication?->customer?->name ?? '—') }} · {{ $t->location ?: '—' }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge($t->status) }}">{{ str_replace('_',' ', $t->status) }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $priorityBadge }}">{{ $priority['label'] }}</span>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">Due {{ $t->due_at ? $t->due_at->format('d M H:i') : '—' }}</div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $tasks->links() }}</div>
    @endif
</x-site.vendor-layout>
