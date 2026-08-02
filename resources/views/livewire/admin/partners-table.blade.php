<div>
    <div class="mb-4 flex flex-wrap gap-3">
        @if (! ($lockCategory ?? false))
            <select wire:model.live="role" class="rounded-lg border-gray-300 text-sm">
                <option value="">All partner roles</option>
                @foreach ($roleOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        @else
            <span class="inline-flex items-center rounded-lg bg-brand-muted text-brand text-sm font-semibold px-3 py-2 ring-1 ring-brand/15">
                {{ $roleOptions[$lockedRole] ?? ucfirst((string) $lockedRole) }}
            </span>
        @endif
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search partners…" class="rounded-lg border-gray-300 text-sm min-w-[16rem]">
    </div>

    @if ($rows->isEmpty())
        <x-site.empty-state
            icon="🤝"
            title="No partners found"
            :description="($reviewOnly ?? false) ? 'No partners are awaiting onboarding right now.' : 'Add a partner or adjust your filters.'"
            :action-label="($reviewOnly ?? false) ? null : 'Add partner'"
            :action-url="($reviewOnly ?? false) ? null : route('admin.partners.create')"
        />
    @else
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-muted/40 text-xs uppercase text-brand">
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
                    @foreach ($rows as $row)
                        <tr class="hover:bg-brand-muted/20">
                            <td class="px-5 py-3 font-mono text-xs">{{ $row->vendor_number }}</td>
                            <td class="px-5 py-3 font-medium">
                                <a href="{{ route('admin.partners.show', $row) }}" class="text-brand hover:underline">{{ $row->name }}</a>
                            </td>
                            <td class="px-5 py-3 text-xs">
                                @foreach (($row->roles ?? [$row->category]) as $role)
                                    <span class="inline-flex mr-1 mb-1 rounded-full bg-brand-muted px-2 py-0.5 text-brand">{{ $roleOptions[$role] ?? $role }}</span>
                                @endforeach
                            </td>
                            <td class="px-5 py-3">{{ $row->phone ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="capitalize">{{ $row->status }}</span>
                                @if ($row->activated_at)
                                    <span class="block text-[10px] text-emerald-700 font-semibold">Activated</span>
                                @elseif ($row->status === 'inactive')
                                    <span class="block text-[10px] text-amber-700 font-semibold">Awaiting activation</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($reviewOnly ?? false)
                                    <a href="{{ route('admin.partners.show', $row) }}" class="font-semibold text-brand hover:underline">Review</a>
                                @else
                                    <a href="{{ route('admin.partners.edit', $row) }}" class="text-gray-600 hover:text-brand">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @endif
</div>
