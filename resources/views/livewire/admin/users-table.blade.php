<div>
<x-admin.table-shell :records="$rows" :statuses="$roles" statusKey="role" statusLabel="All roles"
                     searchPlaceholder="Search name, email, phone…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="name"       label="Name" />
        <x-admin.th :sort="$sort" :direction="$direction" col="email"      label="Email" />
        <x-admin.th :sort="$sort" :direction="$direction" col="phone"      label="Phone" />
        <x-admin.th :sort="$sort" :direction="$direction" col="role"       label="Role" />
        <x-admin.th :sort="$sort" :direction="$direction" col="is_active"  label="Active" />
        <x-admin.th :sort="$sort" :direction="$direction" col="created_at" label="Joined" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium">{{ $r->name }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $r->email }}</td>
                <td class="px-5 py-3">{{ $r->phone ?? '—' }}</td>
                <td class="px-5 py-3"><x-admin.badge :value="$r->role" :map="['admin' => 'bg-amber-100 text-amber-800', 'super_admin' => 'bg-red-100 text-red-800']" /></td>
                <td class="px-5 py-3">
                    @if ($r->is_active)
                        <span class="text-emerald-600 text-xs font-semibold">ACTIVE</span>
                    @else
                        <span class="text-red-600 text-xs font-semibold">INACTIVE</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No users found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
