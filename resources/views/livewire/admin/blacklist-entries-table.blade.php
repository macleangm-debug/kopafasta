<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search ID value or reason…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="identifier_type"  label="Type" />
        <x-admin.th :sort="$sort" :direction="$direction" col="identifier_value" label="Value" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Reason</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="source"  label="Source" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Expires</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 capitalize"><span class="rounded bg-rose-50 text-rose-700 px-2 py-0.5 text-xs">{{ $r->identifier_type }}</span></td>
                <td class="px-5 py-3 font-mono text-xs"><a href="{{ route('admin.blacklist-entries.show', $r) }}" class="hover:text-brand">{{ $r->identifier_value }}</a></td>
                <td class="px-5 py-3">{{ $r->reason }}</td>
                <td class="px-5 py-3 capitalize">{{ $r->source }}</td>
                <td class="px-5 py-3 text-gray-500">{{ optional($r->expires_on)->format('Y-m-d') ?? '—' }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $r->is_active ? 'bg-rose-50 text-rose-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->is_active ? 'Listed' : 'Cleared' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No blacklist entries.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
