<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search role…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="code" label="Code" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name" label="Role" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Permissions</th>
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">System</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->code }}</td>
                <td class="px-5 py-3 font-medium"><a href="{{ route('admin.roles.show', $r) }}" class="hover:text-brand">{{ $r->name }}</a></td>
                <td class="px-5 py-3 text-gray-500">{{ is_array($r->permissions) ? count($r->permissions).' perms' : '—' }}</td>
                <td class="px-5 py-3">{{ $r->is_system ? 'Yes' : 'No' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-5 py-12 text-center text-gray-500">No roles yet.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
