<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search rule…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="name" label="Rule" />
        <x-admin.th :sort="$sort" :direction="$direction" col="days_past_due" label="DPD ≥" />
        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Min outstanding</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Auto-propose</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Committee?</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium"><a href="{{ route('admin.write-off-rules.show', $r) }}" class="hover:text-brand">{{ $r->name }}</a></td>
                <td class="px-5 py-3">{{ $r->days_past_due }} days</td>
                <td class="px-5 py-3 text-right font-mono text-xs">{{ $r->min_outstanding ? format_number($r->min_outstanding, 0) : '—' }}</td>
                <td class="px-5 py-3">{{ $r->auto_propose ? 'Yes' : 'No' }}</td>
                <td class="px-5 py-3">{{ $r->require_committee_approval ? 'Required' : 'Not required' }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $r->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No write-off rules yet.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
