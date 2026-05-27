<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search fee name or code…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="code"  label="Code" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name"  label="Fee" />
        <x-admin.th :sort="$sort" :direction="$direction" col="type"  label="Type" />
        <x-admin.th :sort="$sort" :direction="$direction" col="basis" label="Basis" />
        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Amount</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="charge_when" label="When" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->code }}</td>
                <td class="px-5 py-3 font-medium"><a href="{{ route('admin.charges-fees.show', $r) }}" class="hover:text-indigo-600">{{ $r->name }}</a></td>
                <td class="px-5 py-3 capitalize">{{ str_replace('_',' ', $r->type) }}</td>
                <td class="px-5 py-3 capitalize">{{ str_replace('_',' ', $r->basis) }}</td>
                <td class="px-5 py-3 text-right font-mono text-xs">{{ number_format($r->amount, 4) }}</td>
                <td class="px-5 py-3 capitalize">{{ $r->charge_when }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $r->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No fees configured.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
