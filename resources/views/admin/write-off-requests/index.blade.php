<x-admin.layout
    title="Write-off requests"
    heading="Write-off requests"
    subheading="Collections recommendations awaiting manager and finance approval">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-wider text-gray-500">Pending</p>
            <p class="text-2xl font-bold text-gray-900">{{ $counts['pending'] }}</p>
        </div>
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
            <p class="text-[10px] uppercase tracking-wider text-amber-700">Awaiting manager</p>
            <p class="text-2xl font-bold text-amber-900">{{ $counts['recommended'] }}</p>
        </div>
        <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 p-4">
            <p class="text-[10px] uppercase tracking-wider text-sky-700">Awaiting finance</p>
            <p class="text-2xl font-bold text-sky-900">{{ $counts['manager_approved'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-wider text-gray-500">Completed</p>
            <p class="text-2xl font-bold text-gray-900">{{ $counts['completed'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-wider text-gray-500">Rejected</p>
            <p class="text-2xl font-bold text-gray-900">{{ $counts['rejected'] }}</p>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ([
            'pending' => 'Pending',
            'recommended' => 'Awaiting manager',
            'manager_approved' => 'Awaiting finance',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'all' => 'All',
        ] as $key => $label)
            <a href="{{ route('admin.write-off-requests.index', ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $key ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Loan</th>
                        <th class="px-5 py-3">Customer</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Recommended</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($requests as $request)
                        @php
                            $loan = $request->loan;
                            $customer = $loan?->customer;
                            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 font-mono text-xs font-semibold">
                                @if ($loan)
                                    <a href="{{ route('admin.loans.show', $loan) }}" class="text-amber-700">{{ $loan->loan_number }}</a>
                                @else — @endif
                                @if ($request->auto_proposed)
                                    <span class="block text-[10px] text-gray-400 font-normal">Auto-proposed</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $name ?: '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $loan?->product?->name }}</div>
                            </td>
                            <td class="px-5 py-3 text-right font-semibold">{{ format_money($request->amount) }}</td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-semibold rounded-full px-2 py-1 bg-gray-100 text-gray-700">
                                    {{ $service->statusLabel($request->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500">
                                {{ $request->recommender?->name ?? ($request->auto_proposed ? 'System rule' : '—') }}
                                <div>{{ optional($request->recommended_at)->format('d M Y') }}</div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.write-off-requests.show', $request) }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-500">No write-off requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $requests->links() }}</div>
        @endif
    </div>
</x-admin.layout>
