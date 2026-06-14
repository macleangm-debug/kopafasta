<x-admin.layout title="Applications Report" heading="Loan Applications" subheading="Application pipeline summary">

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4"><p class="text-[10px] uppercase text-gray-500">Total</p><p class="text-2xl font-bold">{{ $counts['total'] }}</p></div>
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4"><p class="text-[10px] uppercase text-amber-700">Pending review</p><p class="text-2xl font-bold">{{ $counts['pending'] }}</p></div>
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4"><p class="text-[10px] uppercase text-emerald-700">Approved</p><p class="text-2xl font-bold">{{ $counts['approved'] }}</p></div>
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4"><p class="text-[10px] uppercase text-red-700">Rejected</p><p class="text-2xl font-bold">{{ $counts['rejected'] }}</p></div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Application</th>
                    <th class="px-5 py-3">Customer</th>
                    <th class="px-5 py-3">Product</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-5 py-3">Stage</th>
                    <th class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($rows as $app)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">
                            <a href="{{ route('admin.loan-applications.show', $app) }}" class="text-amber-700">{{ $app->application_number }}</a>
                        </td>
                        <td class="px-5 py-3">{{ trim(($app->customer?->first_name ?? '').' '.($app->customer?->last_name ?? '')) }}</td>
                        <td class="px-5 py-3">{{ $app->product?->name }}</td>
                        <td class="px-5 py-3 text-right">{{ format_money($app->requested_amount) }}</td>
                        <td class="px-5 py-3">{{ str_replace('_', ' ', $app->current_stage ?? '—') }}</td>
                        <td class="px-5 py-3">{{ ucfirst($app->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.layout>
