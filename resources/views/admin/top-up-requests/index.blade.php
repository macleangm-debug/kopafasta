<x-admin.layout
    title="Top-up requests"
    heading="Top-up requests"
    subheading="Review borrower loan top-up requests">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ([
            'pending' => $counts['pending'].' pending',
            'approved' => 'Approved (awaiting disbursement)',
            'disbursed' => 'Disbursed',
            'rejected' => 'Rejected',
            'all' => 'All',
        ] as $key => $label)
            <a href="{{ route('admin.top-up-requests.index', ['status' => $key]) }}"
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
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Submitted</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($requests as $request)
                        @php
                            $loan = $request->loan;
                            $customer = $request->customer ?? $loan?->customer;
                            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 font-mono text-xs font-semibold">
                                <a href="{{ route('admin.top-up-requests.show', $request) }}" class="text-indigo-600 hover:text-indigo-700">
                                    {{ $loan?->loan_number ?? '—' }}
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $name ?: '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $loan?->product?->name }}</div>
                            </td>
                            <td class="px-5 py-3 font-semibold">{{ format_money($request->requested_amount) }}</td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1
                                    {{ $request->status === 'pending' ? 'bg-amber-100 text-amber-800' : ($request->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800') }}">
                                    {{ ucfirst($request->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500">{{ $request->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.top-up-requests.show', $request) }}" class="text-indigo-600 font-semibold text-xs hover:underline">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-gray-500">No top-up requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">{{ $requests->links() }}</div>
        @endif
    </div>
</x-admin.layout>
