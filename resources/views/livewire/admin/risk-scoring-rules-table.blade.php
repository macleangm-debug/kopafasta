<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search factor…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="factor" label="Factor" />
        <x-admin.th :sort="$sort" :direction="$direction" col="operator" label="Operator" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Value</th>
        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Weight</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="category" label="Category" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium"><a href="{{ route('admin.risk-scoring-rules.show', $r) }}" class="hover:text-indigo-600">{{ $r->factor }}</a></td>
                <td class="px-5 py-3 font-mono text-xs">{{ $r->operator }}</td>
                <td class="px-5 py-3">{{ $r->value }}</td>
                <td class="px-5 py-3 text-right">{{ $r->weight }}</td>
                <td class="px-5 py-3 capitalize">{{ $r->category }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $r->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No risk rules yet.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
