<div class="space-y-4">

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 px-5 py-3 flex flex-col md:flex-row md:items-center gap-3">
        <div class="flex-1 relative">
            <svg class="size-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search name, phone, email, customer #, NIDA…"
                   class="w-full pl-9 pr-3 py-2 text-sm bg-white border border-gray-300 rounded-lg shadow-sm placeholder:text-gray-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition">
        </div>

        <div class="relative">
            <select wire:model.live="status"
                    class="appearance-none text-sm bg-white border border-gray-300 rounded-lg shadow-sm pl-3.5 pr-9 py-2 font-medium text-gray-700 cursor-pointer hover:border-gray-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
            </select>
            <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>

        <div class="text-xs text-gray-500 md:ml-auto">{{ $customers->total() }} record{{ $customers->total() === 1 ? '' : 's' }}</div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-2.5">Customer #</th>
                    <th class="px-5 py-2.5">Name</th>
                    <th class="px-5 py-2.5">Phone</th>
                    <th class="px-5 py-2.5">Email</th>
                    <th class="px-5 py-2.5">Status</th>
                    <th class="px-5 py-2.5">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($customers as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $c->customer_number ?? '—' }}</td>
                        <td class="px-5 py-3 font-medium">{{ trim($c->first_name.' '.$c->last_name) ?: '—' }}</td>
                        <td class="px-5 py-3">{{ $c->phone ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $c->email ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <span @class([
                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => $c->status === 'active',
                                'bg-red-100 text-red-800'         => $c->status === 'suspended',
                                'bg-gray-100 text-gray-700'       => ! in_array($c->status, ['active', 'suspended']),
                            ])>{{ $c->status ?? 'unknown' }}</span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $c->created_at?->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-5 py-3 border-t border-gray-200">{{ $customers->links() }}</div>
    </div>
</div>
