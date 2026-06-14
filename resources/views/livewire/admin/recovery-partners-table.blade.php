<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search {{ strtolower($label) }} partners…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="vendor_number" label="Partner #" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name" label="Name" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Portal</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Active cases</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Commission %</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Markup %</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Earned</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="status" label="Status" />
        <th class="px-5 py-3"></th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            @php($s = $stats[$r->id] ?? [])
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $r->vendor_number }}</td>
                <td class="px-5 py-3 font-medium">
                    <a href="{{ route('admin.vendors.show', $r) }}" class="text-amber-700 hover:underline">{{ $r->name }}</a>
                </td>
                <td class="px-5 py-3 text-xs">
                    @if ($r->user_id)
                        <span class="text-emerald-700 font-semibold">Active login</span>
                    @else
                        <span class="text-gray-400">No login</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-sm">{{ $s['active_cases'] ?? 0 }}</td>
                <td class="px-5 py-3 text-sm">{{ format_number((float) ($r->recovery_commission_percent ?? 0), 1) }}%</td>
                <td class="px-5 py-3 text-sm">{{ format_number((float) ($r->recovery_markup_percent ?? 0), 1) }}%</td>
                <td class="px-5 py-3 text-sm font-semibold">{{ format_money($s['commission_earned'] ?? 0) }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'active'    => 'bg-emerald-100 text-emerald-800',
                        'inactive'  => 'bg-amber-100 text-amber-800',
                        'suspended' => 'bg-red-100 text-red-800',
                    ]" />
                </td>
                <td class="px-5 py-3 text-right text-sm">
                    <a href="{{ route('admin.vendors.edit', $r) }}" class="text-gray-600 hover:text-amber-700">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="px-5 py-12 text-center text-gray-500">No {{ strtolower($label) }} partners yet.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
