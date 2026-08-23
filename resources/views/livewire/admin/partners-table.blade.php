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
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, phone, partner #, TIN, email…" class="rounded-lg border-gray-300 text-sm min-w-[16rem]">
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
                        <th class="px-5 py-3 text-left">Performance</th>
                        <th class="px-5 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-brand-muted/20 cursor-pointer"
                            role="link"
                            tabindex="0"
                            onclick="window.location='{{ route('admin.partners.show', $row) }}'"
                            onkeydown="if(event.key==='Enter'){ window.location='{{ route('admin.partners.show', $row) }}'; }">
                            <td class="px-5 py-3 font-mono text-xs">{{ $row->vendor_number }}</td>
                            <td class="px-5 py-3 font-medium">
                                <span class="text-brand">{{ $row->name }}</span>
                            </td>
                            <td class="px-5 py-3 text-xs">
                                @foreach (($row->roles ?? [$row->category]) as $role)
                                    <span class="inline-flex mr-1 mb-1 rounded-full bg-brand-muted px-2 py-0.5 text-brand">{{ $roleOptions[$role] ?? $role }}</span>
                                @endforeach
                            </td>
                            <td class="px-5 py-3">
                                @php $perf = $performance[$row->id] ?? null; @endphp
                                @if ($perf)
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1',
                                        'bg-emerald-50 text-emerald-800 ring-emerald-100' => in_array($perf['band'], ['strong', 'active'], true),
                                        'bg-amber-50 text-amber-800 ring-amber-100' => in_array($perf['band'], ['watch', 'watchlist', 'pending_kyc'], true),
                                        'bg-rose-50 text-rose-800 ring-rose-100' => in_array($perf['band'], ['at_risk', 'suspended', 'terminated'], true),
                                        'bg-gray-100 text-gray-700 ring-gray-200' => in_array($perf['band'], ['new'], true),
                                    ])>{{ $perf['label'] }}</span>
                                    @if ($perf['score'] !== null)
                                        <span class="block text-[10px] text-gray-500 tabular-nums mt-0.5">{{ $perf['score'] }}</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'capitalize font-semibold',
                                    'text-emerald-700' => $row->status === 'active',
                                    'text-red-700' => $row->status === 'inactive',
                                    'text-amber-800' => $row->status === 'suspended',
                                ])>{{ $row->status }}</span>
                                @if ($row->activated_at)
                                    <span class="block text-[10px] text-emerald-700 font-semibold">Activated</span>
                                @elseif ($row->status === 'inactive')
                                    <span class="block text-[10px] text-amber-700 font-semibold">Awaiting activation</span>
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
