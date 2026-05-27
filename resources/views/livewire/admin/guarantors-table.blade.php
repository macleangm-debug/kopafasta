<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search name, phone, national ID…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="first_name"   label="Name" />
        <x-admin.th :sort="$sort" :direction="$direction" col="phone"        label="Phone" />
        <x-admin.th :sort="$sort" :direction="$direction" col="national_id"  label="National ID" />
        <x-admin.th :sort="$sort" :direction="$direction" col="relationship" label="Relationship" />
        <x-admin.th :sort="$sort" :direction="$direction" col="created_at"   label="Added" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium">{{ trim(($r->first_name ?? '').' '.($r->last_name ?? '')) ?: '—' }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $r->phone ?? '—' }}</td>
                <td class="px-5 py-3 font-mono text-xs">{{ $r->national_id ?? '—' }}</td>
                <td class="px-5 py-3 text-xs">{{ $r->relationship ?? '—' }}</td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-500">No guarantors found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
