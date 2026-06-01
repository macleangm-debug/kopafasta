<div>
<x-admin.table-shell :records="$rows" :statuses="$filterRoles" :statusLabels="$roleLabels" statusKey="role" statusLabel="All roles"
                     searchPlaceholder="Search name, email, phone…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="name"       label="Name" />
        <x-admin.th :sort="$sort" :direction="$direction" col="email"      label="Email" />
        <x-admin.th :sort="$sort" :direction="$direction" col="phone"      label="Phone" />
        <x-admin.th :sort="$sort" :direction="$direction" col="role"       label="Role" />
        <x-admin.th :sort="$sort" :direction="$direction" col="is_active"  label="Account" />
        <x-admin.th :sort="$sort" :direction="$direction" col="created_at" label="Joined" />
        <th class="px-5 py-3"></th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            @php
                $locked = $r->locked_until && $r->locked_until->isFuture();
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium">{{ $r->name }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $r->email }}</td>
                <td class="px-5 py-3">{{ $r->phone ?? '—' }}</td>
                <td class="px-5 py-3"><x-admin.badge :value="$r->role" group="role" :label="$roleLabels[$r->role] ?? null" :map="['admin' => 'bg-amber-100 text-amber-800', 'super_admin' => 'bg-red-100 text-red-800']" /></td>
                <td class="px-5 py-3">
                    <div class="flex flex-col gap-1">
                        @if ($r->is_active)
                            <span class="text-emerald-600 text-xs font-semibold">ACTIVE</span>
                        @else
                            <span class="text-gray-600 text-xs font-semibold">INACTIVE</span>
                        @endif
                        @if ($locked)
                            <span class="text-red-600 text-xs font-semibold">LOCKED</span>
                        @endif
                    </div>
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
                <td class="px-5 py-3 text-right">
                    @perm('users.view')
                        <a href="{{ route('admin.users.show', $r) }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">View →</a>
                    @endperm
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No users found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
