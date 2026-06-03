<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search account code or name…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="code" label="Code" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name" label="Account" />
        <x-admin.th :sort="$sort" :direction="$direction" col="type" label="Type" />
        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Opening</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->code }}</td>
                <td class="px-5 py-3 font-medium"><a href="{{ route('admin.chart-of-accounts.show', $r) }}" class="hover:text-indigo-600">{{ $r->name }}</a></td>
                <td class="px-5 py-3 capitalize">{{ $r->type }}</td>
                <td class="px-5 py-3 text-right font-mono text-xs">{{ format_number($r->opening_balance, 2) }} {{ $r->currency }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $r->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-500">No GL accounts yet.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
