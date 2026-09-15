<div>
    <div class="mb-3 flex flex-wrap gap-2">
        @foreach ($desks as $key => $label)
            <button type="button"
                    wire:click="setDesk(@js($key))"
                    @class([
                        'rounded-xl px-3.5 py-2 text-xs font-semibold transition ring-1',
                        'bg-brand text-white ring-brand' => $desk === $key,
                        'bg-white text-gray-700 ring-brand/15 hover:border-brand/30' => $desk !== $key,
                    ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search ticket #, subject, customer…">
        <x-slot:headers>
            <x-admin.th :sort="$sort" :direction="$direction" col="ticket_number" label="Ticket #" />
            <x-admin.th :sort="$sort" :direction="$direction" col="subject"       label="Subject" />
            <x-admin.th :sort="$sort" :direction="$direction" col="customer_id"   label="Contact" />
            <x-admin.th :sort="$sort" :direction="$direction" col="assigned_to"   label="Assignee" />
            <x-admin.th :sort="$sort" :direction="$direction" col="priority"      label="Priority" />
            <x-admin.th :sort="$sort" :direction="$direction" col="status"        label="Status" />
            <x-admin.th :sort="$sort" :direction="$direction" col="created_at"    label="Opened" />
            <th class="px-5 py-3"></th>
        </x-slot:headers>
        <x-slot:rows>
            @forelse ($rows as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-mono text-xs">
                        <a href="{{ route('admin.support-tickets.show', $r) }}" class="hover:text-brand">{{ $r->ticket_number ?? '—' }}</a>
                    </td>
                    <td class="px-5 py-3">{{ \Illuminate\Support\Str::limit($r->subject, 50) }}</td>
                    <td class="px-5 py-3 text-xs">{{ $r->contactLabel() }}</td>
                    <td class="px-5 py-3 text-xs">{{ $r->assignee?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-xs uppercase">{{ $r->priority ?? '—' }}</td>
                    <td class="px-5 py-3">
                        <x-admin.badge :value="$r->status" :map="[
                            'resolved'    => 'bg-emerald-100 text-emerald-800',
                            'in_progress' => 'bg-blue-100 text-blue-800',
                            'waiting'     => 'bg-violet-100 text-violet-800',
                            'open'        => 'bg-amber-100 text-amber-800',
                            'closed'      => 'bg-gray-100 text-gray-700',
                        ]" />
                    </td>
                    <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.support-tickets.show', $r) }}" class="text-xs font-medium text-brand hover:text-brand-light">View →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-12 text-center text-gray-500">No tickets found.</td></tr>
            @endforelse
        </x-slot:rows>
    </x-admin.table-shell>
</div>
