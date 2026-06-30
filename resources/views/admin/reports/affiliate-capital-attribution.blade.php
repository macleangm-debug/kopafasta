<x-admin.layout title="Capital attribution" heading="Affiliate → capital partner attribution" subheading="Loans referred by affiliates and funded by capital partners">

    <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</button>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        @foreach ([
            ['Referred loans', $report['totals']['loans']],
            ['Affiliates', $report['totals']['affiliates']],
            ['Capital partners', $report['totals']['capital_partners']],
            ['Allocations', $report['totals']['allocations']],
            ['Principal allocated', format_money($report['totals']['allocated_principal'])],
            ['Outstanding exposure', format_money($report['totals']['outstanding_exposure'])],
        ] as [$label, $value])
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
                <p class="text-[10px] uppercase text-gray-500">{{ $label }}</p>
                <p class="text-xl font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if ($summary->isNotEmpty())
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">By affiliate</h3>
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500 text-left">
                    <tr>
                        <th class="py-2">Affiliate</th>
                        <th class="py-2">Code</th>
                        <th class="py-2">Loans</th>
                        <th class="py-2 text-right">Allocated</th>
                        <th class="py-2 text-right">Exposure</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($summary as $row)
                        <tr>
                            <td class="py-2">{{ $row->affiliate_name }}</td>
                            <td class="py-2 font-mono text-xs">{{ $row->affiliate_code }}</td>
                            <td class="py-2">{{ $row->loans }}</td>
                            <td class="py-2 text-right">{{ format_money($row->allocated) }}</td>
                            <td class="py-2 text-right">{{ format_money($row->exposure) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold">Attribution chain detail</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Affiliate</th>
                    <th class="px-5 py-3">Borrower</th>
                    <th class="px-5 py-3">Loan</th>
                    <th class="px-5 py-3">Capital partner</th>
                    <th class="px-5 py-3 text-right">Allocated</th>
                    <th class="px-5 py-3 text-right">Exposure</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($report['rows'] as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-medium">{{ $row['affiliate_name'] ?? '—' }}</p>
                            <p class="text-xs text-gray-500 font-mono">{{ $row['affiliate_code'] ?? '' }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <p>{{ $row['customer_name'] }}</p>
                            <p class="text-xs text-gray-500">{{ $row['customer_number'] }}</p>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $row['loan_number'] }}</td>
                        <td class="px-5 py-3">{{ $row['lender_name'] }}</td>
                        <td class="px-5 py-3 text-right">{{ format_money($row['allocated_principal']) }}</td>
                        <td class="px-5 py-3 text-right">{{ format_money($row['outstanding_exposure']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No affiliate-funded capital allocations in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
