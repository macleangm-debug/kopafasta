<div class="space-y-4">

    {{-- Toolbar --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 px-5 py-3 flex flex-col md:flex-row md:items-center gap-3">
        <div class="flex-1 relative">
            <svg class="size-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search loan number, customer, phone…"
                   class="w-full pl-9 pr-3 py-2 text-sm bg-white border border-gray-300 rounded-lg shadow-sm placeholder:text-gray-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition">
        </div>

        <div class="relative">
            <select wire:model.live="status" @if($lockStatus) disabled @endif
                    class="appearance-none text-sm bg-white border border-gray-300 rounded-lg shadow-sm pl-3.5 pr-9 py-2 font-medium text-gray-700 cursor-pointer hover:border-gray-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition disabled:bg-gray-100 disabled:cursor-not-allowed">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>

        <div class="text-xs text-gray-500 md:ml-auto">
            {{ $loans->total() }} record{{ $loans->total() === 1 ? '' : 's' }}
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    @php
                        $cols = [
                            ['loan_number',   'Loan #'],
                            ['customer_id',   'Customer'],
                            ['principal_amount', 'Principal'],
                            ['outstanding_balance', 'Outstanding'],
                            ['status',        'Status'],
                            ['disbursement_date', 'Disbursed'],
                        ];
                    @endphp
                    @foreach ($cols as [$col, $label])
                        <th class="px-5 py-2.5 cursor-pointer select-none" wire:click="sortBy('{{ $col }}')">
                            <span class="inline-flex items-center gap-1">
                                {{ $label }}
                                @if ($sort === $col)
                                    <span class="text-amber-600">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </span>
                        </th>
                    @endforeach
                    <th class="px-5 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($loans as $loan)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $loan->loan_number ?? '—' }}</td>
                        <td class="px-5 py-3">
                            {{ trim(($loan->customer?->first_name ?? '').' '.($loan->customer?->last_name ?? '')) ?: '—' }}
                            <div class="text-xs text-gray-500">{{ $loan->customer?->phone }}</div>
                        </td>
                        <td class="px-5 py-3">TZS {{ number_format((float) $loan->principal_amount) }}</td>
                        <td class="px-5 py-3 font-semibold">TZS {{ number_format((float) $loan->outstanding_balance) }}</td>
                        <td class="px-5 py-3">
                            <span @class([
                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => $loan->status === 'active',
                                'bg-red-100 text-red-800'         => in_array($loan->status, ['defaulted', 'written_off']),
                                'bg-amber-100 text-amber-800'     => $loan->status === 'pending',
                                'bg-gray-100 text-gray-700'       => $loan->status === 'closed',
                            ])>
                                {{ str_replace('_', ' ', $loan->status ?? 'unknown') }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $loan->disbursement_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('admin.loans.show', $loan) }}"
                                   title="View"
                                   class="inline-flex items-center justify-center size-8 rounded-lg text-gray-500 hover:text-amber-600 hover:bg-amber-50 transition">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('admin.loans.edit', $loan) }}"
                                   title="Edit"
                                   class="inline-flex items-center justify-center size-8 rounded-lg text-gray-500 hover:text-amber-600 hover:bg-amber-50 transition">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('admin.loans.destroy', $loan) }}"
                                      class="inline"
                                      onsubmit="return confirm('Delete loan {{ $loan->loan_number }}? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete"
                                            class="inline-flex items-center justify-center size-8 rounded-lg text-gray-500 hover:text-red-600 hover:bg-red-50 transition">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No loans found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="px-5 py-3 border-t border-gray-200">
        {{ $loans->links() }}
    </div>
    </div>
</div>
