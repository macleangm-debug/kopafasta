<x-admin.layout title="Customer Report" heading="Customer Report" subheading="Portfolio composition and top exposures">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Total customers</div>
            <div class="mt-2 text-2xl font-bold font-mono">{{ format_number($total) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Active borrowers</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600 font-mono">{{ format_number($active) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Dormant</div>
            <div class="mt-2 text-2xl font-bold text-gray-600 font-mono">{{ format_number($dormant) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">New (this month / year)</div>
            <div class="mt-2 text-2xl font-bold font-mono">{{ format_number($thisMonth) }} / {{ format_number($thisYear) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">PEP flagged</div>
            <div class="mt-2 text-2xl font-bold text-amber-600 font-mono">{{ format_number($pep) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Blacklisted</div>
            <div class="mt-2 text-2xl font-bold text-rose-600 font-mono">{{ format_number($blacklisted) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Risk band distribution</div>
            <div class="mt-2 space-y-1 text-sm">
                @foreach ($byRisk as $band => $count)
                    <div class="flex justify-between"><span class="capitalize">{{ $band }}</span><span class="font-mono">{{ format_number($count) }}</span></div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 font-semibold">Top 20 customers by exposure</div>
        @if ($top->isEmpty())
            <div class="px-6 py-8 text-center text-gray-500 text-sm">No customer data.</div>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 text-left">#</th>
                        <th class="px-6 py-3 text-left">Customer</th>
                        <th class="px-6 py-3 text-left">Risk band</th>
                        <th class="px-6 py-3 text-right">Exposure</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($top as $i => $c)
                        <tr>
                            <td class="px-6 py-3 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-6 py-3">
                                <a href="{{ route('admin.customers.show', $c) }}" class="text-indigo-600 hover:underline">
                                    {{ trim($c->first_name.' '.$c->last_name) }}
                                </a>
                            </td>
                            <td class="px-6 py-3 capitalize">{{ $c->risk_band ?? '—' }}</td>
                            <td class="px-6 py-3 text-right font-mono">{{ format_number((float) $c->exposure, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-admin.layout>
