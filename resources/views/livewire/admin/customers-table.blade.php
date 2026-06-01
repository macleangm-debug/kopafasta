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

        <div class="text-xs text-gray-500 md:ml-auto">{{ $customers->total() }} customer{{ $customers->total() === 1 ? '' : 's' }}</div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($customers as $c)
            @php
                $initials = strtoupper(substr($c->first_name ?? '?', 0, 1).substr($c->last_name ?? '', 0, 1));
                $percent = (int) ($c->profile_percent ?? 0);
            @endphp
            <a href="{{ route('admin.customers.show', $c) }}"
               class="group block bg-white rounded-xl shadow-sm ring-1 ring-gray-200 hover:ring-amber-300 hover:shadow-md transition p-5">
                <div class="flex items-start gap-4">
                    <div class="size-12 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 text-gray-900 font-bold text-sm grid place-items-center shrink-0 shadow-sm">
                        {{ $initials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 truncate group-hover:text-amber-800 transition">
                                    {{ trim($c->first_name.' '.$c->last_name) ?: 'Unnamed' }}
                                </p>
                                <p class="text-xs font-mono text-gray-500 mt-0.5">{{ $c->customer_number ?? '—' }}</p>
                            </div>
                            <span @class([
                                'shrink-0 inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide',
                                'bg-emerald-100 text-emerald-800' => $c->status === 'active',
                                'bg-red-100 text-red-800' => $c->status === 'suspended',
                                'bg-gray-100 text-gray-700' => ! in_array($c->status, ['active', 'suspended']),
                            ])>{{ $c->status ?? 'unknown' }}</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">{{ $c->phone ?? '—' }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $c->email ?? 'No email' }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Profile</span>
                            <div class="flex-1 min-w-[80px] h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $percent >= 80 ? 'bg-emerald-500' : ($percent >= 50 ? 'bg-amber-500' : 'bg-red-400') }}"
                                     style="width: {{ $percent }}%"></div>
                            </div>
                            <span class="text-xs font-bold text-gray-700">{{ $percent }}%</span>
                        </div>
                        @if ($c->branch?->name)
                            <p class="text-[11px] text-gray-400 mt-2">{{ $c->branch->name }}</p>
                        @endif
                    </div>
                </div>
                <p class="mt-4 text-xs font-semibold text-amber-700 group-hover:text-amber-800">Open dossier →</p>
            </a>
        @empty
            <div class="sm:col-span-2 xl:col-span-3 bg-white rounded-xl ring-1 ring-gray-200 px-5 py-16 text-center text-gray-500">
                No customers found.
            </div>
        @endforelse
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 px-5 py-3">{{ $customers->links() }}</div>
</div>
