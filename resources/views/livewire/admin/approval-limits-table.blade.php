<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search role or action…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="role_code" label="Role" />
        <x-admin.th :sort="$sort" :direction="$direction" col="action"    label="Action" />
        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Min</th>
        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Max</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Dual?</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->role_code }}</td>
                <td class="px-5 py-3"><a href="{{ route('admin.approval-limits.show', $r) }}" class="hover:text-indigo-600">{{ str_replace('_',' ', $r->action) }}</a></td>
                <td class="px-5 py-3 text-right font-mono text-xs">{{ number_format($r->min_amount, 0) }}</td>
                <td class="px-5 py-3 text-right font-mono text-xs">{{ number_format($r->max_amount, 0) }} {{ $r->currency }}</td>
                <td class="px-5 py-3">{{ $r->requires_dual_control ? 'Yes' : 'No' }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $r->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No approval limits yet.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
