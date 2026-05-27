<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search complaint #, subject…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="complaint_number" label="Complaint #" />
        <x-admin.th :sort="$sort" :direction="$direction" col="subject"          label="Subject" />
        <x-admin.th :sort="$sort" :direction="$direction" col="severity"         label="Severity" />
        <x-admin.th :sort="$sort" :direction="$direction" col="channel"          label="Channel" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"           label="Status" />
        <x-admin.th :sort="$sort" :direction="$direction" col="created_at"       label="Received" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->complaint_number ?? '—' }}</td>
                <td class="px-5 py-3">{{ \Illuminate\Support\Str::limit($r->subject, 50) }}</td>
                <td class="px-5 py-3 text-xs uppercase">{{ $r->severity ?? '—' }}</td>
                <td class="px-5 py-3 text-xs">{{ $r->channel ?? '—' }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'resolved'      => 'bg-emerald-100 text-emerald-800',
                        'investigating' => 'bg-blue-100 text-blue-800',
                        'received'      => 'bg-amber-100 text-amber-800',
                        'escalated'     => 'bg-red-100 text-red-800',
                    ]" />
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No complaints found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
