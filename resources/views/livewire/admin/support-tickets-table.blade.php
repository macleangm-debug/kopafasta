<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search ticket #, subject, customer…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="ticket_number" label="Ticket #" />
        <x-admin.th :sort="$sort" :direction="$direction" col="subject"       label="Subject" />
        <x-admin.th :sort="$sort" :direction="$direction" col="customer_id"   label="Customer" />
        <x-admin.th :sort="$sort" :direction="$direction" col="priority"      label="Priority" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"        label="Status" />
        <x-admin.th :sort="$sort" :direction="$direction" col="created_at"    label="Opened" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->ticket_number ?? '—' }}</td>
                <td class="px-5 py-3">{{ \Illuminate\Support\Str::limit($r->subject, 50) }}</td>
                <td class="px-5 py-3 text-xs">
                    {{ trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')) ?: '—' }}
                </td>
                <td class="px-5 py-3 text-xs uppercase">{{ $r->priority ?? '—' }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'resolved'    => 'bg-emerald-100 text-emerald-800',
                        'in_progress' => 'bg-blue-100 text-blue-800',
                        'open'        => 'bg-amber-100 text-amber-800',
                        'closed'      => 'bg-gray-100 text-gray-700',
                    ]" />
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No tickets found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
