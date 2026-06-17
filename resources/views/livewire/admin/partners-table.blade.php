<div>
    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="role" class="rounded-lg border-gray-300 text-sm">
            <option value="">All partner roles</option>
            @foreach ($roleOptions as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search partners…" class="rounded-lg border-gray-300 text-sm min-w-[16rem]">
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3 text-left">Partner #</th>
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Roles</th>
                    <th class="px-5 py-3 text-left">Phone</th>
                    <th class="px-5 py-3 text-left">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $row->vendor_number }}</td>
                        <td class="px-5 py-3 font-medium">
                            <a href="{{ route('admin.partners.show', $row) }}" class="text-amber-700 hover:underline">{{ $row->name }}</a>
                        </td>
                        <td class="px-5 py-3 text-xs">
                            @foreach (($row->roles ?? [$row->category]) as $role)
                                <span class="inline-flex mr-1 mb-1 rounded-full bg-gray-100 px-2 py-0.5">{{ $roleOptions[$role] ?? $role }}</span>
                            @endforeach
                        </td>
                        <td class="px-5 py-3">{{ $row->phone ?? '—' }}</td>
                        <td class="px-5 py-3 capitalize">{{ $row->status }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.partners.edit', $row) }}" class="text-gray-600 hover:text-amber-700">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No partners found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</div>
