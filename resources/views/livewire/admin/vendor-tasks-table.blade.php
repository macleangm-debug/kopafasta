<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search partner or task type…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="task_type"    label="Task" />
        <th class="px-5 py-3">Partner</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="status"       label="Status" />
        <x-admin.th :sort="$sort" :direction="$direction" col="due_at"       label="Due" />
        <x-admin.th :sort="$sort" :direction="$direction" col="completed_at" label="Completed" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium">{{ display_label((string) $r->task_type, 'vendor_task_type') }}</td>
                <td class="px-5 py-3 text-gray-600">
                    @if ($r->vendor)
                        <a href="{{ route('admin.partners.show', $r->vendor) }}" class="text-brand hover:underline">{{ $r->vendor->name }}</a>
                    @else
                        —
                    @endif
                </td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'assigned'    => 'bg-blue-100 text-blue-800',
                        'in_progress' => 'bg-amber-100 text-amber-800',
                        'completed'   => 'bg-emerald-100 text-emerald-800',
                        'failed'      => 'bg-red-100 text-red-800',
                        'cancelled'   => 'bg-gray-100 text-gray-700',
                    ]" />
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->due_at?->format('Y-m-d') ?? '—' }}</td>
                <td class="px-5 py-3 text-gray-500">{{ $r->completed_at?->format('Y-m-d') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-500">No partner tasks found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
