<x-admin.layout title="CRB Audit & Billing" heading="CRB Audit & Billing" subheading="Credit bureau request log for compliance and invoicing">
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <form method="GET" action="{{ route('admin.compliance.crb-audit') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="rounded-lg border-gray-300 text-sm">
            </div>
            <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Filter</button>
        </form>
        <a href="{{ route('admin.compliance.crb-audit.export', ['from' => $from, 'to' => $to]) }}"
           class="inline-flex bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
            Export CSV
        </a>
    </div>

    <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Period</p>
            <p class="font-semibold text-gray-900">{{ $report['summary']['month'] }}</p>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Requests</p>
            <p class="font-semibold text-gray-900">{{ number_format($report['summary']['requests']) }}</p>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Estimated cost</p>
            <p class="font-semibold text-gray-900">{{ format_money($report['summary']['estimated_cost']) }} <span class="text-xs text-gray-500">@ {{ format_money($cost) }}/req</span></p>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Date / Time</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">NIDA</th>
                        <th class="px-4 py-3">Application</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Provider</th>
                        <th class="px-4 py-3">Request</th>
                        <th class="px-4 py-3">Response</th>
                        <th class="px-4 py-3">Cost</th>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Requested by</th>
                        <th class="px-4 py-3">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p>{{ $row['request_date'] }}</p>
                                <p class="text-xs text-gray-500">{{ $row['request_time'] }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $row['customer_name'] }}</td>
                            <td class="px-4 py-3 text-xs">{{ $row['national_id'] }}</td>
                            <td class="px-4 py-3 text-xs">{{ $row['application_id'] }}</td>
                            <td class="px-4 py-3">{{ $row['application_type'] }}</td>
                            <td class="px-4 py-3">{{ $row['provider'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $row['request_status'] === 'Success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $row['request_status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $row['response_status'] }}</td>
                            <td class="px-4 py-3">{{ format_money($row['cost']) }}</td>
                            <td class="px-4 py-3 text-xs">{{ $row['invoice_status'] }}</td>
                            <td class="px-4 py-3 text-xs">{{ $row['requested_by'] }}</td>
                            <td class="px-4 py-3 text-xs font-mono">{{ $row['reference_number'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-8 text-center text-gray-500">No CRB requests in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin.layout>
