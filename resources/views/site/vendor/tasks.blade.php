<x-site.vendor-layout title="Assigned tasks" active="tasks">
    @php
        $tabs = [
            'all'         => 'All',
            'assigned'    => 'Assigned',
            'in_progress' => 'In progress',
            'completed'   => 'Completed',
            'rejected'    => 'Rejected',
            'cancelled'   => 'Cancelled',
        ];
        $current = $status ?: 'all';
        $badge = fn ($s) => match ($s) {
            'assigned'    => 'bg-amber-100 text-amber-700',
            'in_progress' => 'bg-indigo-100 text-indigo-700',
            'completed'   => 'bg-emerald-100 text-emerald-700',
            'rejected'    => 'bg-red-100 text-red-700',
            'cancelled'   => 'bg-gray-100 text-gray-600',
            default       => 'bg-gray-100 text-gray-600',
        };
    @endphp

    <h1 class="text-2xl font-extrabold mb-1">Assigned tasks</h1>
    <p class="text-sm text-gray-500 mb-5">All work assigned to you. Tap a task to see details and upload proof.</p>

    {{-- Filter pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($tabs as $k => $label)
            <a href="{{ route('site.partner.tasks', $k === 'all' ? [] : ['status' => $k]) }}"
               class="px-3 py-1.5 rounded-full text-xs font-semibold border
                      {{ $current === $k ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($tasks->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">No tasks here.</div>
    @else
        {{-- Table on desktop, cards on mobile --}}
        <div class="hidden lg:block rounded-2xl border border-gray-200 bg-white overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-3">Task</th>
                        <th class="text-left px-4 py-3">Customer</th>
                        <th class="text-left px-4 py-3">Location</th>
                        <th class="text-left px-4 py-3">Due</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($tasks as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold">{{ ucfirst(str_replace('_',' ', $t->task_type)) }}<div class="text-[11px] text-gray-400">#{{ $t->id }}</div></td>
                            <td class="px-4 py-3">{{ $t->customer_name ?: '—' }}<div class="text-[11px] text-gray-400">{{ $t->customer_phone }}</div></td>
                            <td class="px-4 py-3 text-gray-600">{{ $t->location ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $t->due_at ? $t->due_at->format('d M Y H:i') : '—' }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge($t->status) }}">{{ str_replace('_',' ', $t->status) }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('site.partner.task', $t) }}" class="text-indigo-600 hover:underline text-sm font-semibold">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="lg:hidden space-y-3">
            @foreach ($tasks as $t)
                <a href="{{ route('site.partner.task', $t) }}" class="block rounded-2xl border border-gray-200 bg-white p-4 hover:shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-sm">{{ ucfirst(str_replace('_',' ', $t->task_type)) }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $t->customer_name ?: '—' }} · {{ $t->location ?: '—' }}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge($t->status) }} shrink-0">{{ str_replace('_',' ', $t->status) }}</span>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">Due {{ $t->due_at ? $t->due_at->format('d M H:i') : '—' }}</div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $tasks->links() }}</div>
    @endif
</x-site.vendor-layout>
