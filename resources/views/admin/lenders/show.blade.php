<x-admin.layout
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->code">

    <div class="flex flex-wrap items-center gap-3 mb-6">
        <a href="{{ route('admin.capital-funding.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800">← {{ __('admin.capital_funding.title') }}</a>
        <a href="{{ route('admin.lenders.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">All partners</a>
        <a href="{{ route('admin.lenders.edit', $record) }}" class="ml-auto text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">Edit</a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @foreach ([
            'Capital invested' => $metrics['capital_invested'],
            'Capital utilized' => $metrics['capital_utilized'],
            'Available' => $metrics['capital_available'],
            'Outstanding exposure' => $metrics['outstanding_exposure'],
            'Interest earned' => $metrics['interest_earned_total'],
            'Active loans' => $metrics['active_loans'],
        ] as $label => $value)
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-[10px] uppercase tracking-wider text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900">
                    @if (is_numeric($value) && ! in_array($label, ['Active loans'], true))
                        {{ format_money($value) }}
                    @else
                        {{ format_number($value) }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">Revenue share (interest)</h3>
            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-4">
                    <dt class="text-xs text-emerald-800">Partner share ({{ \App\Services\CapitalPartnerAllocationService::PARTNER_INTEREST_SHARE }}%)</dt>
                    <dd class="mt-1 text-xl font-bold text-emerald-900">{{ format_money($metrics['interest_earned_partner']) }}</dd>
                </div>
                <div class="rounded-lg bg-sky-50 ring-1 ring-sky-200 p-4">
                    <dt class="text-xs text-sky-800">Company share ({{ \App\Services\CapitalPartnerAllocationService::COMPANY_INTEREST_SHARE }}%)</dt>
                    <dd class="mt-1 text-xl font-bold text-sky-900">{{ format_money($metrics['interest_earned_company']) }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-gray-500">Interest from repayments is split proportionally by each partner’s funded share of the loan, then {{ \App\Services\CapitalPartnerAllocationService::PARTNER_INTEREST_SHARE }}% / {{ \App\Services\CapitalPartnerAllocationService::COMPANY_INTEREST_SHARE }}% between partner and company.</p>
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Partner profile</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Type</dt><dd class="font-medium capitalize">{{ $record->type }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Status</dt><dd class="font-medium capitalize">{{ $record->status }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Contact</dt><dd class="font-medium">{{ $record->contact_person ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ $record->phone ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ $record->email ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Credit limit</dt><dd class="font-medium">{{ $record->credit_limit ? format_money($record->credit_limit) : '—' }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-800">Funding pools (proportional allocation)</h3>
            <a href="{{ route('admin.funding-pools.index') }}?lender={{ $record->id }}" class="text-xs text-amber-700 font-medium">View pools →</a>
        </div>
        <p class="text-xs text-gray-500 mb-4">New loans draw from open pools in proportion to each pool’s available capital (industry-standard proportional allocation, not round-robin).</p>
        @if ($pools === [])
            <p class="text-sm text-gray-500">No funding pools configured.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-2 pr-4">Pool</th>
                            <th class="text-left py-2 pr-4">Status</th>
                            <th class="text-right py-2 pr-4">Committed</th>
                            <th class="text-right py-2 pr-4">Deployed</th>
                            <th class="text-right py-2 pr-4">Available</th>
                            <th class="text-right py-2">Pool weight</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pools as $pool)
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $pool['name'] }}</td>
                                <td class="py-2 pr-4 capitalize text-xs">{{ $pool['status'] }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($pool['committed']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($pool['deployed']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($pool['available']) }}</td>
                                <td class="py-2 text-right font-mono">{{ format_number($pool['share_pct'], 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($record->address)
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 text-sm text-gray-700">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Address</h3>
            <p>{{ $record->address }}</p>
        </div>
    @endif
</x-admin.layout>
