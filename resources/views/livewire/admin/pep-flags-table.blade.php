<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search name or org…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="full_name" label="Name" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Position</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Organization</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="category"   label="Category" />
        <x-admin.th :sort="$sort" :direction="$direction" col="risk_level" label="Risk" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            @php($rc = ['low'=>'bg-emerald-50 text-emerald-700','medium'=>'bg-amber-50 text-amber-700','high'=>'bg-rose-50 text-rose-700','extreme'=>'bg-rose-100 text-rose-800'][$r->risk_level] ?? 'bg-gray-100')
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium"><a href="{{ route('admin.pep-flags.show', $r) }}" class="hover:text-indigo-600">{{ $r->full_name }}</a></td>
                <td class="px-5 py-3">{{ $r->position }}</td>
                <td class="px-5 py-3">{{ $r->organization }}</td>
                <td class="px-5 py-3">{{ display_label($r->category, 'pep_category') }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $rc }}">{{ ucfirst($r->risk_level) }}</span></td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $r->is_active ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->is_active ? 'Flagged' : 'Cleared' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No PEP flags.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
